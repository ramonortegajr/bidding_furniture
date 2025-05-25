<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to place a bid";
    header("Location: login.php");
    exit();
}

// Check if item ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid item ID";
    header("Location: furniture_list.php");
    exit();
}

$item_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Get item details
$item_sql = "SELECT f.*, u.username as seller_name, 
            (SELECT MAX(bid_amount) FROM bids WHERE item_id = f.item_id) as highest_bid
            FROM furniture_items f 
            JOIN users u ON f.seller_id = u.user_id 
            WHERE f.item_id = ?";
$stmt = $conn->prepare($item_sql);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

// Validate item exists and is active
if (!$item) {
    $_SESSION['error'] = "Item not found";
    header("Location: furniture_list.php");
    exit();
}

if ($item['status'] !== 'active') {
    $_SESSION['error'] = "This auction has ended";
    header("Location: furniture_list.php");
    exit();
}

if ($item['seller_id'] == $user_id) {
    $_SESSION['error'] = "You cannot bid on your own item";
    header("Location: furniture_list.php");
    exit();
}

// Check if auction has ended
$now = new DateTime();
$end_time = new DateTime($item['end_time']);
if ($now > $end_time) {
    $_SESSION['error'] = "This auction has ended";
    header("Location: furniture_list.php");
    exit();
}

// Process bid submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bid_amount = filter_input(INPUT_POST, 'bid_amount', FILTER_VALIDATE_FLOAT);
    
    if (!$bid_amount) {
        $_SESSION['error'] = "Please enter a valid bid amount";
    } else {
        // Calculate minimum bid
        $min_bid = $item['highest_bid'] ? $item['highest_bid'] + 100 : $item['starting_price'];
        
        if ($bid_amount < $min_bid) {
            $_SESSION['error'] = "Minimum bid amount is ₱" . number_format($min_bid, 2);
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert new bid
                $insert_bid = "INSERT INTO bids (item_id, user_id, bid_amount, bid_time) VALUES (?, ?, ?, NOW())";
                $stmt = $conn->prepare($insert_bid);
                $stmt->bind_param("iid", $item_id, $user_id, $bid_amount);
                $stmt->execute();
                
                // Update current price in furniture_items
                $update_price = "UPDATE furniture_items SET current_price = ? WHERE item_id = ?";
                $stmt = $conn->prepare($update_price);
                $stmt->bind_param("di", $bid_amount, $item_id);
                $stmt->execute();

                // Create notification for seller
                $notification_message = "New bid of ₱" . number_format($bid_amount, 2) . " placed on your item: " . $item['title'];
                $insert_notification = "INSERT INTO notifications (user_id, item_id, message, type, created_at, is_read) 
                                     VALUES (?, ?, ?, 'new_bid', NOW(), 0)";
                $stmt = $conn->prepare($insert_notification);
                $stmt->bind_param("iis", $item['seller_id'], $item_id, $notification_message);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['success'] = "Your bid has been placed successfully!";
                header("Location: item.php?id=" . $item_id);
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Error placing bid. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Bid - <?php echo htmlspecialchars($item['title']); ?></title>
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
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Place Bid</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['error'];
                                unset($_SESSION['error']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <img src="<?php echo htmlspecialchars($item['image_url'] ?: 'assets/images/no-image.jpg'); ?>" 
                                     class="img-fluid rounded" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>">
                            </div>
                            <div class="col-md-8">
                                <h5><?php echo htmlspecialchars($item['title']); ?></h5>
                                <p class="text-muted">Seller: <?php echo htmlspecialchars($item['seller_name']); ?></p>
                                <p>Current Price: <span class="text-success fw-bold">₱<?php echo number_format($item['current_price'], 2); ?></span></p>
                                <p>Minimum Bid: <span class="text-primary fw-bold">₱<?php echo number_format(($item['highest_bid'] ?: $item['starting_price']) + 100, 2); ?></span></p>
                                <p>
                                    Time Remaining: 
                                    <span class="text-danger">
                                        <?php
                                        $time_left = $end_time->getTimestamp() - $now->getTimestamp();
                                        $days = floor($time_left / (60 * 60 * 24));
                                        $hours = floor(($time_left % (60 * 60 * 24)) / (60 * 60));
                                        $minutes = floor(($time_left % (60 * 60)) / 60);
                                        
                                        if ($days > 0) {
                                            echo $days . "d " . $hours . "h left";
                                        } else {
                                            echo $hours . "h " . $minutes . "m left";
                                        }
                                        ?>
                                    </span>
                                </p>
                            </div>
                        </div>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="bid_amount" class="form-label">Your Bid Amount (₱)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="bid_amount" 
                                           name="bid_amount" 
                                           min="<?php echo ($item['highest_bid'] ?: $item['starting_price']) + 100; ?>"
                                           step="100"
                                           required>
                                </div>
                                <div class="form-text">Minimum increment: ₱100</div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-gavel me-2"></i>Place Bid
                                </button>
                                <a href="item.php?id=<?php echo $item_id; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Item
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
    </script>
</body>
</html> 