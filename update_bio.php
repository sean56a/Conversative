<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    echo json_encode(['success'=>false,'error'=>'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$bio = trim($_POST['bio'] ?? '');

try {
    $stmt = $pdo->prepare("UPDATE users SET bio=? WHERE id=?");
    $stmt->execute([$bio, $user_id]);
    echo json_encode(['success'=>true,'bio'=>$bio]);
} catch(PDOException $e){
    echo json_encode(['success'=>false,'error'=>'Failed to update bio: '.$e->getMessage()]);
}
