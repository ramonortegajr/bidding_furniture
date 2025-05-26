<?php
session_start();
require_once 'config/database.php';
require_once 'includes/nav_helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['new_count' => 0]);
    exit();
}

$result = checkWinnersNavVisibility($conn, $_SESSION['user_id']);
echo json_encode(['new_count' => $result['new_count']]);
?> 