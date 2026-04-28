<?php
require 'db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$user = current_session_user();

echo json_encode([
    'success' => true,
    'authenticated' => !empty($user),
    'user' => $user,
]);
?>
