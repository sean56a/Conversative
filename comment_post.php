<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$post_id = $data['post_id'] ?? null;
$comment = trim($data['comment'] ?? '');

if (!$post_id || !$comment) {
    echo json_encode(['success' => false, 'error' => 'Missing post ID or comment']);
    exit;
}

// Insert comment
$stmt = $pdo->prepare("INSERT INTO post_comments (post_id, user_id, comment) VALUES (?, ?, ?)");
$stmt->execute([$post_id, $user_id, $comment]);

// Update post comment count
$pdo->prepare("UPDATE posts SET comments_count = comments_count + 1 WHERE id=?")->execute([$post_id]);

$comments_count = $pdo->query("SELECT comments_count FROM posts WHERE id=$post_id")->fetchColumn();
echo json_encode(['success' => true, 'comments_count' => $comments_count]);
