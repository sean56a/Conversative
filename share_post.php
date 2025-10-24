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

if (!$post_id) {
    echo json_encode(['success' => false, 'error' => 'Post ID missing']);
    exit;
}

// Duplicate post for sharing
$stmt = $pdo->prepare("INSERT INTO posts (user_id, content, image, original_post_id) SELECT ?, content, image, id FROM posts WHERE id=?");
$stmt->execute([$user_id, $post_id]);

// Update share count
$pdo->prepare("UPDATE posts SET shares_count = shares_count + 1 WHERE id=?")->execute([$post_id]);

$shares_count = $pdo->query("SELECT shares_count FROM posts WHERE id=$post_id")->fetchColumn();
echo json_encode(['success' => true, 'shares_count' => $shares_count]);
