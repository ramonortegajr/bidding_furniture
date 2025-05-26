<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get username
$user_sql = "SELECT username FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$username = $user_stmt->get_result()->fetch_assoc()['username'];

$conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Get or create conversation
if ($conversation_id === 0 && $item_id > 0 && $other_user_id > 0) {
    // Check if conversation exists
    $check_sql = "SELECT conversation_id FROM chat_conversations 
                  WHERE item_id = ? AND (
                      (buyer_id = ? AND seller_id = ?) OR 
                      (buyer_id = ? AND seller_id = ?)
                  )";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iiiii", $item_id, $user_id, $other_user_id, $other_user_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $conversation_id = $result->fetch_assoc()['conversation_id'];
    } else {
        // Get item details to determine buyer and seller
        $item_sql = "SELECT seller_id FROM furniture_items WHERE item_id = ?";
        $item_stmt = $conn->prepare($item_sql);
        $item_stmt->bind_param("i", $item_id);
        $item_stmt->execute();
        $item_result = $item_stmt->get_result();
        $item = $item_result->fetch_assoc();
        
        // Determine buyer and seller
        $seller_id = $item['seller_id'];
        $buyer_id = ($user_id == $seller_id) ? $other_user_id : $user_id;
        
        // Create new conversation
        $create_sql = "INSERT INTO chat_conversations (item_id, buyer_id, seller_id) VALUES (?, ?, ?)";
        $create_stmt = $conn->prepare($create_sql);
        $create_stmt->bind_param("iii", $item_id, $buyer_id, $seller_id);
        $create_stmt->execute();
        $conversation_id = $conn->insert_id;
    }
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $insert_sql = "INSERT INTO chat_messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iis", $conversation_id, $user_id, $message);
        $insert_stmt->execute();
        
        // Update conversation timestamp
        $update_sql = "UPDATE chat_conversations SET updated_at = CURRENT_TIMESTAMP WHERE conversation_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $conversation_id);
        $update_stmt->execute();
    }
}

// Get conversation details
$conv_sql = "SELECT c.*, 
             f.title as item_title, f.image_url, f.current_price,
             b.username as buyer_name,
             s.username as seller_name
             FROM chat_conversations c
             JOIN furniture_items f ON c.item_id = f.item_id
             JOIN users b ON c.buyer_id = b.user_id
             JOIN users s ON c.seller_id = s.user_id
             WHERE c.conversation_id = ?";
$conv_stmt = $conn->prepare($conv_sql);
$conv_stmt->bind_param("i", $conversation_id);
$conv_stmt->execute();
$conversation = $conv_stmt->get_result()->fetch_assoc();

// Get messages
$msg_sql = "SELECT m.*, u.username 
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC";
$msg_stmt = $conn->prepare($msg_sql);
$msg_stmt->bind_param("i", $conversation_id);
$msg_stmt->execute();
$messages = $msg_stmt->get_result();

// Mark messages as read
$read_sql = "UPDATE chat_messages 
             SET is_read = 1 
             WHERE conversation_id = ? AND sender_id != ? AND is_read = 0";
$read_stmt = $conn->prepare($read_sql);
$read_stmt->bind_param("ii", $conversation_id, $user_id);
$read_stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .chat-container {
            height: calc(100vh - 300px);
            display: flex;
            flex-direction: column;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
        }
        .message {
            margin-bottom: 1rem;
            max-width: 80%;
        }
        .message.sent {
            margin-left: auto;
        }
        .message.received {
            margin-right: auto;
        }
        .message-content {
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            display: inline-block;
        }
        .sent .message-content {
            background: #007bff;
            color: white;
            border-bottom-right-radius: 0.25rem;
        }
        .received .message-content {
            background: #e9ecef;
            color: #212529;
            border-bottom-left-radius: 0.25rem;
        }
        .message-meta {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        .chat-input {
            padding: 1rem;
            background: white;
            border-top: 1px solid #dee2e6;
        }
        .item-details {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation_common.php'; ?>

    <div class="container" style="margin-top: 80px;">
        <?php if ($conversation): ?>
            <!-- Item Details -->
            <div class="item-details">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <img src="<?php echo htmlspecialchars($conversation['image_url'] ?: 'assets/images/no-image.jpg'); ?>" 
                             class="item-image" alt="<?php echo htmlspecialchars($conversation['item_title']); ?>">
                    </div>
                    <div class="col">
                        <h5 class="mb-1"><?php echo htmlspecialchars($conversation['item_title']); ?></h5>
                        <p class="mb-1">
                            <strong>Price:</strong> ₱<?php echo number_format($conversation['current_price'], 2); ?>
                        </p>
                        <p class="mb-0">
                            <small class="text-muted">
                                Conversation between 
                                <strong><?php echo htmlspecialchars($conversation['buyer_name']); ?></strong> (Buyer) and 
                                <strong><?php echo htmlspecialchars($conversation['seller_name']); ?></strong> (Seller)
                            </small>
                        </p>
                    </div>
                    <div class="col-auto">
                        <a href="item.php?id=<?php echo $conversation['item_id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-1"></i>View Item
                        </a>
                    </div>
                </div>
            </div>

            <!-- Chat Container -->
            <div class="chat-container">
                <!-- Messages -->
                <div class="chat-messages" id="chatMessages">
                    <?php while ($message = $messages->fetch_assoc()): ?>
                        <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>" 
                             data-message-id="<?php echo $message['message_id']; ?>">
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($message['message_text'])); ?>
                            </div>
                            <div class="message-meta">
                                <?php echo htmlspecialchars($message['username']); ?> • 
                                <?php echo date('M d, g:i A', strtotime($message['created_at'])); ?>
                                <?php if ($message['sender_id'] == $user_id): ?>
                                    <span class="read-status">
                                        <?php if ($message['is_read']): ?>
                                            <i class="fas fa-check-double text-primary" title="Read"></i>
                                        <?php else: ?>
                                            <i class="fas fa-check text-muted" title="Sent"></i>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Typing Indicator -->
                <div class="typing-indicator mb-2 ps-3" style="display: none;">
                    <small class="text-muted">
                        <i class="fas fa-ellipsis-h me-1"></i>
                        <span class="typing-text"></span>
                    </small>
                </div>

                <!-- Chat Input Form -->
                <div class="chat-input">
                    <form method="POST" id="messageForm">
                        <div class="input-group">
                            <textarea class="form-control" name="message" placeholder="Type your message..." rows="1" required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i>Send
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>Invalid conversation.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const conversationId = <?php echo $conversation_id; ?>;
        let typingTimeout;
        let isTyping = false;
        let lastMessageId = null;

        // Auto-scroll to bottom of messages
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Get last message ID
        function getLastMessageId() {
            const messages = document.querySelectorAll('.message');
            if (messages.length > 0) {
                const lastMessage = messages[messages.length - 1];
                return lastMessage.getAttribute('data-message-id');
            }
            return '0';
        }

        // Check if message already exists
        function messageExists(messageId) {
            return document.querySelector(`.message[data-message-id="${messageId}"]`) !== null;
        }

        // Add new message
        function addNewMessage(message) {
            if (messageExists(message.message_id)) {
                return false;
            }

            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${message.sender_id == <?php echo $user_id; ?> ? 'sent' : 'received'}`;
            messageDiv.setAttribute('data-message-id', message.message_id);
            
            const messageContent = document.createElement('div');
            messageContent.className = 'message-content';
            messageContent.innerHTML = message.message_text;
            messageDiv.appendChild(messageContent);
            
            const messageMeta = document.createElement('div');
            messageMeta.className = 'message-meta';
            messageMeta.innerHTML = `
                ${message.username} • ${message.created_at}
                ${message.sender_id == <?php echo $user_id; ?> ? `
                    <span class="read-status">
                        <i class="fas fa-check text-muted" title="Sent"></i>
                    </span>
                ` : ''}
            `;
            messageDiv.appendChild(messageMeta);
            
            chatMessages.appendChild(messageDiv);
            return true;
        }

        // Check for new messages
        function checkNewMessages() {
            const currentLastMessageId = getLastMessageId();
            fetch(`check_new_messages.php?conversation_id=${conversationId}&last_message_id=${currentLastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    let hasNewMessages = false;
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(message => {
                            if (addNewMessage(message)) {
                                hasNewMessages = true;
                            }
                        });
                        if (hasNewMessages) {
                            scrollToBottom();
                        }
                    }
                });
        }

        // Submit form without page reload
        const messageForm = document.getElementById('messageForm');
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    setTimeout(checkNewMessages, 100); // Check for new messages after a short delay
                    this.reset();
                    textarea.style.height = 'auto';
                }
            });

            isTyping = false;
            updateTypingStatus(false);
        });

        // Initialize
        window.onload = () => {
            scrollToBottom();
            lastMessageId = getLastMessageId();
        };

        // Auto-resize textarea
        const textarea = document.querySelector('textarea');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            
            // Handle typing indicator
            if (!isTyping) {
                isTyping = true;
                updateTypingStatus(true);
            }
            
            // Clear previous timeout
            clearTimeout(typingTimeout);
            
            // Set new timeout
            typingTimeout = setTimeout(() => {
                isTyping = false;
                updateTypingStatus(false);
            }, 2000);
        });

        // Update typing status
        function updateTypingStatus(isTyping) {
            fetch('api/chat_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=set_typing&conversation_id=${conversationId}&is_typing=${isTyping ? 1 : 0}`
            });
        }

        // Get chat status (typing indicators and unread messages)
        function getChatStatus() {
            fetch('api/chat_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_status&conversation_id=${conversationId}`
            })
            .then(response => response.json())
            .then(data => {
                const typingIndicator = document.querySelector('.typing-indicator');
                const typingText = document.querySelector('.typing-text');
                
                if (data.typing_users && data.typing_users.length > 0) {
                    typingText.textContent = `${data.typing_users.join(', ')} is typing...`;
                    typingIndicator.style.display = 'block';
                } else {
                    typingIndicator.style.display = 'none';
                }
            });
        }

        // Mark messages as read
        function markMessagesAsRead() {
            fetch('api/chat_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_read&conversation_id=${conversationId}`
            });
        }

        // Check chat status every 3 seconds
        setInterval(getChatStatus, 3000);

        // Check for new messages every 5 seconds
        setInterval(checkNewMessages, 5000);

        // Mark messages as read when chat is visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                markMessagesAsRead();
            }
        });

        // Mark messages as read on page load
        markMessagesAsRead();
    </script>
</body>
</html> 