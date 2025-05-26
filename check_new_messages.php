<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

if ($conversation_id === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid conversation ID']);
    exit();
}

// Get new messages
$sql = "SELECT m.*, u.username 
        FROM chat_messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.conversation_id = ? 
        AND m.message_id > ?
        ORDER BY m.created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $conversation_id, $last_message_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'message_id' => $row['message_id'],
        'sender_id' => $row['sender_id'],
        'message_text' => htmlspecialchars($row['message_text']),
        'username' => htmlspecialchars($row['username']),
        'created_at' => date('M d, g:i A', strtotime($row['created_at'])),
        'is_read' => (bool)$row['is_read']
    ];
}

echo json_encode(['messages' => $messages]); 