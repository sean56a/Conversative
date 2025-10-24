<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$content = trim($_POST['content'] ?? '');
$imagePath = null;

if (!$content) {
    echo json_encode(['success' => false, 'message' => 'Content required']);
    exit;
}

if (!empty($_FILES['image']['name'])) {
    $uploadDir = 'uploads/posts/';
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0777, true);
    $fileName = time() . '_' . basename($_FILES['image']['name']);
    $targetPath = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        $imagePath = $targetPath;
    }
}

$stmt = $pdo->prepare("INSERT INTO posts (user_id, content, image, created_at) VALUES (?, ?, ?, NOW())");
$success = $stmt->execute([$user_id, $content, $imagePath]);

echo json_encode(['success' => $success]);
?>