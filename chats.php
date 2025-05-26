<?php
session_start();
require_once 'config/database.php';
require_once 'includes/nav_helpers.php';

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

// Get all chats
$chats = getAllChats($conn, $user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Chats - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .chat-preview {
            transition: all 0.2s ease;
        }
        .chat-preview:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .unread-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            background-color: #dc3545;
            color: white;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
        }
        .chat-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0.5rem;
        }
        .last-message {
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation_common.php'; ?>

    <div class="container" style="margin-top: 80px;">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">My Chats</li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-comments me-2"></i>My Chats
                </h2>

                <?php if ($chats->num_rows > 0): ?>
                    <div class="row">
                        <?php while ($chat = $chats->fetch_assoc()): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card chat-preview h-100">
                                    <div class="row g-0">
                                        <div class="col-md-4 p-3">
                                            <img src="<?php echo htmlspecialchars($chat['image_url'] ?: 'assets/images/no-image.jpg'); ?>" 
                                                 class="chat-image" 
                                                 alt="<?php echo htmlspecialchars($chat['item_title']); ?>">
                                            <?php if ($chat['unread_count'] > 0): ?>
                                                <div class="unread-badge">
                                                    <?php echo $chat['unread_count']; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="card-body">
                                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($chat['item_title']); ?></h5>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        Chat with <?php echo htmlspecialchars($chat['other_user_name']); ?>
                                                    </small>
                                                </p>
                                                
                                                <?php
                                                // Get last message
                                                $last_msg_sql = "SELECT m.*, u.username 
                                                                FROM chat_messages m
                                                                JOIN users u ON m.sender_id = u.user_id
                                                                WHERE m.conversation_id = ?
                                                                ORDER BY m.created_at DESC
                                                                LIMIT 1";
                                                $last_msg_stmt = $conn->prepare($last_msg_sql);
                                                $last_msg_stmt->bind_param("i", $chat['conversation_id']);
                                                $last_msg_stmt->execute();
                                                $last_message = $last_msg_stmt->get_result()->fetch_assoc();
                                                ?>
                                                
                                                <?php if ($last_message): ?>
                                                    <div class="last-message mb-3">
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($last_message['username']); ?>:
                                                            <?php echo htmlspecialchars($last_message['message_text']); ?>
                                                        </small>
                                                    </div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('M d, g:i A', strtotime($last_message['created_at'])); ?>
                                                    </small>
                                                <?php endif; ?>

                                                <div class="mt-3">
                                                    <a href="chat.php?conversation_id=<?php echo $chat['conversation_id']; ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-comments me-1"></i>Open Chat
                                                    </a>
                                                    <a href="item.php?id=<?php echo $chat['item_id']; ?>" 
                                                       class="btn btn-outline-secondary btn-sm">
                                                        <i class="fas fa-eye me-1"></i>View Item
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>You have no active chats
                        <div class="mt-3">
                            <a href="furniture_list.php" class="btn btn-primary">Browse Auctions</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 