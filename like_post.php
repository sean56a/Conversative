<?php
session_start();
require 'db.php'; // PDO connection

header("Content-Type: application/json");

// --- Check if user is logged in ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// --- Use $_POST instead of JSON since JS sends FormData ---
$action  = $_POST['action'] ?? '';
$post_id = $_POST['post_id'] ?? 0;
$content = trim($_POST['content'] ?? '');

if (!$post_id || !in_array($action, ['like', 'share', 'comment'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // --- LIKE ---
    if ($action === 'like') {
        $stmt = $pdo->prepare("SELECT * FROM post_likes WHERE post_id=? AND user_id=?");
        $stmt->execute([$post_id, $user_id]);
        $alreadyLiked = $stmt->fetch();

        if ($alreadyLiked) {
            // Unlike
            $stmt = $pdo->prepare("DELETE FROM post_likes WHERE post_id=? AND user_id=?");
            $stmt->execute([$post_id, $user_id]);

            $stmt = $pdo->prepare("UPDATE posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id=?");
            $stmt->execute([$post_id]);

            $count = $pdo->query("SELECT likes_count FROM posts WHERE id=$post_id")->fetchColumn();

            echo json_encode(['success' => true, 'liked' => false, 'likes_count' => $count]);
            exit;
        } else {
            // Like
            $stmt = $pdo->prepare("INSERT INTO post_likes (post_id, user_id, liked_at) VALUES (?, ?, NOW())");
            $stmt->execute([$post_id, $user_id]);

            $stmt = $pdo->prepare("UPDATE posts SET likes_count = likes_count + 1 WHERE id=?");
            $stmt->execute([$post_id]);

            $count = $pdo->query("SELECT likes_count FROM posts WHERE id=$post_id")->fetchColumn();

            echo json_encode(['success' => true, 'liked' => true, 'likes_count' => $count]);
            exit;
        }
    }

    // --- SHARE ---
    if ($action === 'share') {
        $stmt = $pdo->prepare("SELECT * FROM post_shares WHERE post_id=? AND user_id=?");
        $stmt->execute([$post_id, $user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You already shared this post']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO post_shares (post_id, user_id, shared_at) VALUES (?, ?, NOW())");
        $stmt->execute([$post_id, $user_id]);

        $stmt = $pdo->prepare("UPDATE posts SET shares_count = shares_count + 1 WHERE id=?");
        $stmt->execute([$post_id]);

        $count = $pdo->query("SELECT shares_count FROM posts WHERE id=$post_id")->fetchColumn();

        echo json_encode(['success' => true, 'shares_count' => $count]);
        exit;
    }

    // --- COMMENT ---
if ($action === 'comment' && $content) {
    // Insert comment
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$post_id, $user_id, $content]);
    $comment_id = $pdo->lastInsertId();

    // Update count
    $countStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM comments WHERE post_id = ?");
    $countStmt->execute([$post_id]);
    $comments_count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Return the new comment
    $stmt = $pdo->prepare("
        SELECT c.comment, c.created_at, u.first_name, u.last_name, u.avatar AS user_avatar
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'comment' => $comment, 'comments_count' => $comments_count]);
    exit;
}


    echo json_encode(['success' => false, 'error' => 'Unknown error']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
