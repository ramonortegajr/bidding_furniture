<?php
session_start();
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    header("Location: furniture_list.php");
    exit();
}

$item_id = intval($_GET['id']);

// Get item details
$sql = "SELECT f.*, c.name as category_name, u.username as seller_name, u.user_id as seller_id 
        FROM furniture_items f 
        JOIN categories c ON f.category_id = c.category_id 
        JOIN users u ON f.seller_id = u.user_id 
        WHERE f.item_id = ? AND f.status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: furniture_list.php");
    exit();
}

// Get bid history
$bid_sql = "SELECT b.*, u.username 
            FROM bids b 
            JOIN users u ON b.user_id = u.user_id 
            WHERE b.item_id = ? 
            GROUP BY b.user_id
            ORDER BY b.bid_amount DESC 
            LIMIT 10";
$bid_stmt = $conn->prepare($bid_sql);
$bid_stmt->bind_param("i", $item_id);
$bid_stmt->execute();
$bid_history = $bid_stmt->get_result();

// Handle new bid
$bid_error = '';
$bid_success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    if (isset($_POST['bid_amount'])) {
        $bid_amount = floatval($_POST['bid_amount']);
        
        // Validate bid amount
        if ($bid_amount <= $item['current_price']) {
            $bid_error = "Bid must be higher than current price";
        } else if ($item['seller_id'] == $_SESSION['user_id']) {
            $bid_error = "You cannot bid on your own item";
        } else if (strtotime($item['end_time']) < time()) {
            $bid_error = "This auction has ended";
        } else {
            // Place bid
            $bid_sql = "INSERT INTO bids (item_id, user_id, bid_amount, bid_time) VALUES (?, ?, ?, NOW())";
            $bid_stmt = $conn->prepare($bid_sql);
            $bid_stmt->bind_param("iid", $item_id, $_SESSION['user_id'], $bid_amount);
            
            if ($bid_stmt->execute()) {
                // Update current price
                $update_sql = "UPDATE furniture_items SET current_price = ? WHERE item_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("di", $bid_amount, $item_id);
                $update_stmt->execute();
                
                $bid_success = "Bid placed successfully!";
                
                // Refresh item details
                $stmt->execute();
                $item = $stmt->get_result()->fetch_assoc();
                
                // Refresh bid history
                $bid_stmt->execute();
                $bid_history = $bid_stmt->get_result();
            } else {
                $bid_error = "Error placing bid. Please try again.";
            }
        }
    }
}

// Check if item is in user's watchlist
$in_watchlist = false;
if (isset($_SESSION['user_id'])) {
    $watch_sql = "SELECT * FROM watchlist WHERE user_id = ? AND item_id = ?";
    $watch_stmt = $conn->prepare($watch_sql);
    $watch_stmt->bind_param("ii", $_SESSION['user_id'], $item_id);
    $watch_stmt->execute();
    $in_watchlist = $watch_stmt->get_result()->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['title']); ?> - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .item-image {
            max-height: 400px;
            object-fit: contain;
        }
        .bid-history {
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
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
                            <a class="nav-link" href="login.php">Login/Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container" style="margin-top: 80px;">
        <div class="mb-3">
            <a href="furniture_list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Listings
            </a>
        </div>
        <?php if ($bid_error): ?>
            <div class="alert alert-danger"><?php echo $bid_error; ?></div>
        <?php endif; ?>
        <?php if ($bid_success): ?>
            <div class="alert alert-success"><?php echo $bid_success; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Item Details -->
            <div class="col-md-8">
                <div class="card">
                    <?php if ($item['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="card-img-top item-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                    <?php else: ?>
                        <img src="assets/images/no-image.jpg" class="card-img-top item-image" alt="No image available">
                    <?php endif; ?>
                    <div class="card-body">
                        <h2 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h2>
                        <p class="text-muted">
                            Listed by: <?php echo htmlspecialchars($item['seller_name']); ?><br>
                            Category: <?php echo htmlspecialchars($item['category_name']); ?><br>
                            Condition: <?php echo htmlspecialchars($item['condition_status']); ?>
                        </p>
                        <h4>Description</h4>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Bidding Section -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="card-title">Current Bid</h3>
                        <h2 class="text-primary mb-3">₱<?php echo number_format($item['current_price'], 2); ?></h2>
                        <p class="text-muted">
                            Starting Price: ₱<?php echo number_format($item['starting_price'], 2); ?><br>
                            Auction Ends: <?php echo date('M d, Y h:i A', strtotime($item['end_time'])); ?>
                        </p>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if (strtotime($item['end_time']) > time()): ?>
                                <form method="POST" class="mb-3">
                                    <div class="input-group mb-3">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" name="bid_amount" class="form-control" step="0.01" min="<?php echo $item['current_price'] + 0.01; ?>" required>
                                        <button type="submit" class="btn btn-primary">Place Bid</button>
                                    </div>
                                </form>
                                
                                <!-- Watchlist Toggle -->
                                <form method="POST" action="<?php echo $in_watchlist ? 'remove_watchlist.php' : 'add_watchlist.php'; ?>" class="mb-3">
                                    <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="fas <?php echo $in_watchlist ? 'fa-heart' : 'fa-heart-o'; ?>"></i>
                                        <?php echo $in_watchlist ? 'Remove from Watchlist' : 'Add to Watchlist'; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning">This auction has ended</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary w-100">Login to Bid</a>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Bid History -->
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Bid History</h4>
                        <div class="bid-history">
                            <?php if ($bid_history->num_rows > 0): ?>
                                <ul class="list-group list-group-flush">
                                    <?php while ($bid = $bid_history->fetch_assoc()): ?>
                                        <li class="list-group-item">
                                            <strong>₱<?php echo number_format($bid['bid_amount'], 2); ?></strong>
                                            by <?php echo htmlspecialchars($bid['username']); ?><br>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y h:i A', strtotime($bid['bid_time'])); ?>
                                            </small>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">No bids yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 