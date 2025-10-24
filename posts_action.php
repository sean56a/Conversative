<?php
session_start();
require 'db.php'; // PDO connection

header("Content-Type: application/json");

// --- SESSION CHECK ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$content = trim($_POST['content'] ?? '');

// --- BASIC VALIDATION ---
if (!$action || $post_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // -----------------------------
    // --- EDIT POST ---
    // -----------------------------
    if ($action === 'edit_post') {
        if (!$content) {
            echo json_encode(['success' => false, 'error' => 'Post cannot be empty']);
            exit;
        }

        // Check ownership
        $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$post_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized or post not found']);
            exit;
        }

        // Sanitize content
        $safeContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        // Update post
        $update = $pdo->prepare("UPDATE posts SET content = ?, updated_at = NOW() WHERE id = ?");
        $update->execute([$safeContent, $post_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Post updated',
            'content' => $safeContent
        ]);
        exit;
    }

    // -----------------------------
    // --- DELETE POST ---
    // -----------------------------
    if ($action === 'delete_post') {
        // Check ownership
        $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$post_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized or post not found']);
            exit;
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Delete post
        $delPost = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $delPost->execute([$post_id]);

        // Delete related comments
        $delComments = $pdo->prepare("DELETE FROM comments WHERE post_id = ?");
        $delComments->execute([$post_id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Post deleted'
        ]);
        exit;
    }

    // --- INVALID ACTION ---
    echo json_encode(['success' => false, 'error' => 'Invalid action']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
