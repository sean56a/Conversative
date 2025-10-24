<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_FILES['cover_photo']) || $_FILES['cover_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$fileTmpPath = $_FILES['cover_photo']['tmp_name'];
$fileName = basename($_FILES['cover_photo']['name']);
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','gif','jfif'];

if (!in_array($ext, $allowed)) {
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

$newFileName = 'cover_'.$user_id.'_'.time().'.'.$ext;
$uploadDir = 'uploads/covers/';

if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$destPath = $uploadDir.$newFileName;

if (!move_uploaded_file($fileTmpPath, $destPath)) {
    echo json_encode(['error' => 'Failed to move uploaded file']);
    exit;
}

// Update DB
try {
    $stmt = $pdo->prepare("UPDATE users SET cover_photo=? WHERE id=?");
    $stmt->execute([$destPath, $user_id]);
    echo json_encode(['success'=>true, 'cover'=>$destPath]);
} catch (PDOException $e) {
    echo json_encode(['error'=>'DB error: '.$e->getMessage()]);
}
