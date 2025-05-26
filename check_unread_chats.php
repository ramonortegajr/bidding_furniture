<?php
session_start();
require_once 'config/database.php';
require_once 'includes/nav_helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$unread_count = getUnreadChatsCount($conn, $user_id);

echo json_encode([
    'unread_count' => $unread_count
]); 