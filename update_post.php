<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

// Creating a new post
if (isset($data['content']) && !isset($data['post_id'])) {
    $content = trim($data['content']);
    if (!$content) {
        echo json_encode(['success' => false, 'error' => 'Post cannot be empty']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $content]);
        $post_id = $pdo->lastInsertId();

        echo json_encode(['success' => true, 'post_id' => $post_id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Updating an existing post (like, share, comment)
$post_id = $data['post_id'] ?? 0;
$action = $data['action'] ?? '';

if (!$post_id || !in_array($action, ['like', 'share', 'comment'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    switch ($action) {
        case 'like':
            // Check if user already liked
            $stmt = $pdo->prepare("SELECT * FROM post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $user_id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'error' => 'You already liked this post']);
                exit;
            }

            // Insert like
            $stmt = $pdo->prepare("INSERT INTO post_likes (post_id, user_id, liked_at) VALUES (?, ?, NOW())");
            $stmt->execute([$post_id, $user_id]);

            // Update posts table count
            $stmt = $pdo->prepare("UPDATE posts SET likes_count = likes_count + 1 WHERE id = ?");
            $stmt->execute([$post_id]);

            // Return updated count
            $stmt = $pdo->prepare("SELECT likes_count FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $count = $stmt->fetchColumn();

            echo json_encode(['success' => true, 'likes_count' => $count]);
            break;

        case 'share':
            // Check if user already shared
            $stmt = $pdo->prepare("SELECT * FROM post_shares WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $user_id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'error' => 'You already shared this post']);
                exit;
            }

            // Insert share
            $stmt = $pdo->prepare("INSERT INTO post_shares (post_id, user_id, shared_at) VALUES (?, ?, NOW())");
            $stmt->execute([$post_id, $user_id]);

            // Update posts table count
            $stmt = $pdo->prepare("UPDATE posts SET shares_count = shares_count + 1 WHERE id = ?");
            $stmt->execute([$post_id]);

            $stmt = $pdo->prepare("SELECT shares_count FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $count = $stmt->fetchColumn();

            echo json_encode(['success' => true, 'shares_count' => $count]);
            break;

        case 'comment':
            $content = trim($data['content'] ?? '');
            if (!$content) {
                echo json_encode(['success' => false, 'error' => 'Comment cannot be empty']);
                exit;
            }

            // Insert comment
            $stmt = $pdo->prepare("INSERT INTO post_comments (post_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$post_id, $user_id, $content]);

            // Update posts table count
            $stmt = $pdo->prepare("UPDATE posts SET comments_count = comments_count + 1 WHERE id = ?");
            $stmt->execute([$post_id]);

            $stmt = $pdo->prepare("SELECT comments_count FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $count = $stmt->fetchColumn();

            echo json_encode(['success' => true, 'comments_count' => $count]);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
