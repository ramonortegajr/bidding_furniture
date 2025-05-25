<?php
session_start();
require_once 'config/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get all notifications for the user
$notifications_sql = "SELECT n.*, f.title as item_title, f.image_url,
                     (SELECT MAX(bid_amount) FROM bids WHERE item_id = f.item_id) as current_bid
                     FROM notifications n 
                     JOIN furniture_items f ON n.item_id = f.item_id 
                     WHERE n.user_id = ? 
                     ORDER BY n.created_at DESC";
$stmt = $conn->prepare($notifications_sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$notifications = $stmt->get_result();

// Mark all as read
$mark_read_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
$stmt = $conn->prepare($mark_read_sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">Furniture Bidding</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container" style="margin-top: 80px;">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">Your Notifications</h2>
                
                <?php if ($notifications->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($notification = $notifications->fetch_assoc()): ?>
                            <a href="item.php?id=<?php echo $notification['item_id']; ?>" 
                               class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($notification['image_url'] ?: 'assets/images/no-image.jpg'); ?>" 
                                             class="rounded me-3" 
                                             alt="<?php echo htmlspecialchars($notification['item_title']); ?>"
                                             style="width: 60px; height: 60px; object-fit: cover;">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                            </small>
                                            <?php if ($notification['current_bid']): ?>
                                                <div class="text-success">
                                                    Current Bid: ₱<?php echo number_format($notification['current_bid'], 2); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="ms-2">
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </span>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>You have no notifications
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 