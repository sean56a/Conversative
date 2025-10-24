<?php
session_start();
$data = json_decode(file_get_contents('php://input'), true);
if(isset($data['theme'])){
    $_SESSION['theme'] = $data['theme'];
    echo json_encode(['success'=>true]);
    exit;
}
echo json_encode(['success'=>false, 'error'=>'No theme provided']);
