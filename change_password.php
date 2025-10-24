<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = trim($_POST['current_password'] ?? '');
    $new = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');
    $userId = $_SESSION['user_id'];

    // Basic validation
    if ($current === '' || $new === '' || $confirm === '') {
        echo "<script>alert('All fields are required.'); history.back();</script>";
        exit;
    }

    if ($new !== $confirm) {
        echo "<script>alert('New passwords do not match.'); history.back();</script>";
        exit;
    }

    // Fetch the user's current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "<script>alert('User not found.'); history.back();</script>";
        exit;
    }

    if (!password_verify($current, $user['password'])) {
        echo "<script>alert('Current password is incorrect.'); history.back();</script>";
        exit;
    }

    // Prevent reusing the same password
    if (password_verify($new, $user['password'])) {
        echo "<script>alert('New password cannot be the same as your current password.'); history.back();</script>";
        exit;
    }

    // Hash and update new password
    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->execute([$newHash, $userId]);

    echo "<script>alert('Password changed successfully!'); window.location='settings.php';</script>";
    exit;
}
?>
