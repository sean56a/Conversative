<?php
session_start();
require 'db.php';

$response = ['status'=>'error','message'=>'Something went wrong'];

if(!isset($_SESSION['user_id'])){
    $response['message'] = 'Not logged in';
    echo json_encode($response);
    exit;
}

if(isset($_FILES['avatar'])){
    $file = $_FILES['avatar'];
    $allowed = ['jpg','jpeg','png','gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if(in_array($ext, $allowed)){
        $newName = uniqid() . "." . $ext;
        $uploadDir = 'uploads/';
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $uploadPath = $uploadDir.$newName;

        if(move_uploaded_file($file['tmp_name'], $uploadPath)){
            // Save path in database
            $stmt = $pdo->prepare("UPDATE users SET avatar=? WHERE id=?");
            $stmt->execute([$uploadPath, $_SESSION['user_id']]);
            $response['status'] = 'success';
            $response['message'] = 'Avatar updated!';
        } else {
            $response['message'] = 'Upload failed';
        }
    } else {
        $response['message'] = 'Invalid file type';
    }
}

echo json_encode($response);
