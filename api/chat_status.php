<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$conversation_id = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;

switch ($action) {
    case 'set_typing':
        $is_typing = isset($_POST['is_typing']) ? (bool)$_POST['is_typing'] : false;
        
        // Update or insert typing status
        $sql = "INSERT INTO chat_typing_status (conversation_id, user_id, is_typing) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), last_updated = CURRENT_TIMESTAMP";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $conversation_id, $user_id, $is_typing);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success]);
        break;

    case 'get_status':
        // Get typing status
        $typing_sql = "SELECT u.username, cts.is_typing 
                      FROM chat_typing_status cts
                      JOIN users u ON cts.user_id = u.user_id
                      WHERE cts.conversation_id = ? 
                      AND cts.user_id != ? 
                      AND cts.is_typing = 1 
                      AND cts.last_updated >= DATE_SUB(NOW(), INTERVAL 5 SECOND)";
        $typing_stmt = $conn->prepare($typing_sql);
        $typing_stmt->bind_param("ii", $conversation_id, $user_id);
        $typing_stmt->execute();
        $typing_result = $typing_stmt->get_result();
        
        // Get unread message count
        $unread_sql = "SELECT COUNT(*) as unread_count 
                      FROM chat_messages 
                      WHERE conversation_id = ? 
                      AND sender_id != ? 
                      AND is_read = 0";
        $unread_stmt = $conn->prepare($unread_sql);
        $unread_stmt->bind_param("ii", $conversation_id, $user_id);
        $unread_stmt->execute();
        $unread_result = $unread_stmt->get_result();
        $unread_count = $unread_result->fetch_assoc()['unread_count'];
        
        $typing_users = [];
        while ($row = $typing_result->fetch_assoc()) {
            $typing_users[] = $row['username'];
        }
        
        echo json_encode([
            'typing_users' => $typing_users,
            'unread_count' => $unread_count
        ]);
        break;

    case 'mark_read':
        $sql = "UPDATE chat_messages 
                SET is_read = 1 
                WHERE conversation_id = ? 
                AND sender_id != ? 
                AND is_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $conversation_id, $user_id);
        $success = $stmt->execute();
        
        echo json_encode(['success' => $success]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
} 