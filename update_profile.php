<?php
session_start();
require 'db.php'; // PDO connection
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$bio = trim($_POST['bio'] ?? '');

// Validate required fields
if (empty($username) || empty($email)) {
    echo json_encode(['error' => 'Username and email are required']);
    exit;
}

// Initialize cover photo path
$cover_photo_path = null;

// Handle cover photo upload if exists
if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['cover_photo']['tmp_name'];
    $fileName = basename($_FILES['cover_photo']['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'jfif'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['error' => 'Invalid file type for cover photo']);
        exit;
    }

    $newFileName = 'cover_' . $user_id . '_' . time() . '.' . $ext;
    $uploadDir = 'uploads/covers/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $destPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($fileTmpPath, $destPath)) {
        echo json_encode(['error' => 'Failed to move uploaded file']);
        exit;
    }

    $cover_photo_path = $destPath;
}

try {
    if ($cover_photo_path) {
        // Update including cover photo
        $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, bio=?, cover_photo=? WHERE id=?");
        $stmt->execute([$username, $email, $bio, $cover_photo_path, $user_id]);
    } else {
        // Update without changing cover photo
        $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, bio=? WHERE id=?");
        $stmt->execute([$username, $email, $bio, $user_id]);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Failed to update profile: ' . $e->getMessage()]);
}
