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
$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
$content = trim($_POST['content'] ?? '');

// --- BASIC VALIDATION ---
if (!$action || $comment_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // -----------------------------
    // --- EDIT COMMENT ---
    // -----------------------------
    if ($action === 'edit_comment') {
        if (!$content) {
            echo json_encode(['success' => false, 'error' => 'Comment cannot be empty']);
            exit;
        }

        // Check ownership
        $stmt = $pdo->prepare("SELECT id FROM comments WHERE id = ? AND user_id = ?");
        $stmt->execute([$comment_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized or comment not found']);
            exit;
        }

        // Sanitize content (basic, adjust if allowing HTML)
        $safeContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        // Update comment
        $update = $pdo->prepare("UPDATE comments SET comment = ?, updated_at = NOW() WHERE id = ?");
        $update->execute([$safeContent, $comment_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Comment updated',
            'comment' => $safeContent
        ]);
        exit;
    }

    // -----------------------------
    // --- DELETE COMMENT ---
    // -----------------------------
    if ($action === 'delete_comment') {
        // Check ownership & get post_id
        $stmt = $pdo->prepare("SELECT post_id FROM comments WHERE id = ? AND user_id = ?");
        $stmt->execute([$comment_id, $user_id]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized or comment not found']);
            exit;
        }

        // Begin transaction for atomic delete + update
        $pdo->beginTransaction();

        // Delete comment
        $del = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $del->execute([$comment_id]);

        // Recalculate comments count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ?");
        $countStmt->execute([$comment['post_id']]);
        $newCount = (int)$countStmt->fetchColumn();

        // Update post
        $updatePost = $pdo->prepare("UPDATE posts SET comments_count = ? WHERE id = ?");
        $updatePost->execute([$newCount, $comment['post_id']]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Comment deleted',
            'comments_count' => $newCount
        ]);
        exit;
    }

    // --- INVALID ACTION ---
    echo json_encode(['success' => false, 'error' => 'Invalid action']);

} catch (PDOException $e) {
    // Rollback if in transaction
    if ($pdo->inTransaction()) $pdo->rollBack();

    // Log the error and send generic message
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
