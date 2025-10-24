<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// Get logged-in user
if(!isset($_SESSION['user_id'])){
    echo json_encode(['status'=>'error', 'message'=>'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$action = $_GET['action'] ?? '';

if ($action === 'fetch') {
    // Fetch messages with username and avatar
    $stmt = $pdo->prepare("
        SELECT 
            messages.id, 
            messages.message, 
            messages.timestamp, 
            users.username, 
            users.avatar
        FROM messages
        JOIN users ON messages.sender_id = users.id
        ORDER BY messages.id ASC
    ");
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Replace missing avatars with placeholder
    foreach ($messages as &$msg) {
        if (empty($msg['avatar'])) {
            $msg['avatar'] = 'https://via.placeholder.com/40';
        }
    }

    echo json_encode($messages);
    exit;
}

if ($action === 'send') {
    $message = trim($_POST['message'] ?? '');

    if ($message !== '') {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, message) VALUES (?, ?)");
        $stmt->execute([$userId, $message]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'empty']);
    }
    exit;
}
?>
