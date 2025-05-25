<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user's active bids
$bids_sql = "SELECT b.*, f.title, f.current_price, f.end_time 
             FROM bids b 
             JOIN furniture_items f ON b.item_id = f.item_id 
             WHERE b.bidder_id = ? AND b.status = 'active'
             ORDER BY b.bid_time DESC";
$bids_stmt = $conn->prepare($bids_sql);
$bids_stmt->bind_param("i", $user_id);
$bids_stmt->execute();
$active_bids = $bids_stmt->get_result();

// Get user's listed items (if any)
$listings_sql = "SELECT * FROM furniture_items WHERE seller_id = ? ORDER BY created_at DESC";
$listings_stmt = $conn->prepare($listings_sql);
$listings_stmt->bind_param("i", $user_id);
$listings_stmt->execute();
$my_listings = $listings_stmt->get_result();

// Get user's watchlist
$watchlist_sql = "SELECT f.* FROM watchlist w 
                  JOIN furniture_items f ON w.item_id = f.item_id 
                  WHERE w.user_id = ?";
$watchlist_stmt = $conn->prepare($watchlist_sql);
$watchlist_stmt->bind_param("i", $user_id);
$watchlist_stmt->execute();
$watchlist_items = $watchlist_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Furniture Bidding System</title>
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
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="furniture_list.php">Browse Furniture</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container" style="margin-top: 80px;">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
                <p class="text-muted">Here's your bidding activity overview</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row dashboard-stats">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-gavel fa-2x text-primary mb-2"></i>
                    <h3><?php echo $active_bids->num_rows; ?></h3>
                    <p class="text-muted">Active Bids</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-list fa-2x text-success mb-2"></i>
                    <h3><?php echo $my_listings->num_rows; ?></h3>
                    <p class="text-muted">My Listings</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-heart fa-2x text-danger mb-2"></i>
                    <h3><?php echo $watchlist_items->num_rows; ?></h3>
                    <p class="text-muted">Watchlist Items</p>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="row">
            <div class="col-12">
                <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pills-bids-tab" data-bs-toggle="pill" data-bs-target="#pills-bids" type="button">My Bids</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pills-listings-tab" data-bs-toggle="pill" data-bs-target="#pills-listings" type="button">My Listings</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pills-watchlist-tab" data-bs-toggle="pill" data-bs-target="#pills-watchlist" type="button">Watchlist</button>
                    </li>
                </ul>

                <div class="tab-content" id="pills-tabContent">
                    <!-- Active Bids Tab -->
                    <div class="tab-pane fade show active" id="pills-bids">
                        <div class="row">
                            <?php if ($active_bids->num_rows > 0): ?>
                                <?php while ($bid = $active_bids->fetch_assoc()): ?>
                                    <div class="col-md-6 col-lg-4 card-wrapper">
                                        <div class="card listing-card">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($bid['title']); ?></h5>
                                                <p class="card-text">
                                                    <strong>Your Bid:</strong> ₱<?php echo number_format($bid['bid_amount'], 2); ?><br>
                                                    <strong>Current Price:</strong> ₱<?php echo number_format($bid['current_price'], 2); ?><br>
                                                    <strong>Ends:</strong> <?php echo date('M d, Y H:i', strtotime($bid['end_time'])); ?>
                                                </p>
                                                <div class="mt-auto">
                                                    <a href="item.php?id=<?php echo $bid['item_id']; ?>" class="btn btn-primary">View Item</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <p class="text-center">You haven't placed any bids yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- My Listings Tab -->
                    <div class="tab-pane fade" id="pills-listings">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <a href="add_item.php" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Add New Listing
                                </a>
                            </div>
                            <?php if ($my_listings->num_rows > 0): ?>
                                <?php while ($item = $my_listings->fetch_assoc()): ?>
                                    <div class="col-md-6 col-lg-4 card-wrapper">
                                        <div class="card listing-card">
                                            <?php if ($item['image_url']): ?>
                                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="listing-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                            <?php else: ?>
                                                <img src="assets/images/no-image.jpg" class="listing-image" alt="No image available">
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                                <p class="card-text">
                                                    <strong>Current Price:</strong> ₱<?php echo number_format($item['current_price'], 2); ?><br>
                                                    <strong>Status:</strong> <?php echo ucfirst($item['status']); ?><br>
                                                    <strong>End Time:</strong> <?php echo date('M d, Y H:i', strtotime($item['end_time'])); ?>
                                                </p>
                                                <div class="mt-auto">
                                                    <a href="edit_item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-primary">Edit</a>
                                                    <a href="item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-info">View</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <p class="text-center">You haven't listed any items yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Watchlist Tab -->
                    <div class="tab-pane fade" id="pills-watchlist">
                        <div class="row">
                            <?php if ($watchlist_items->num_rows > 0): ?>
                                <?php while ($item = $watchlist_items->fetch_assoc()): ?>
                                    <div class="col-md-6 col-lg-4 card-wrapper">
                                        <div class="card listing-card">
                                            <?php if ($item['image_url']): ?>
                                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="listing-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                            <?php else: ?>
                                                <img src="assets/images/no-image.jpg" class="listing-image" alt="No image available">
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                                <p class="card-text">
                                                    <strong>Current Price:</strong> ₱<?php echo number_format($item['current_price'], 2); ?><br>
                                                    <strong>Ends:</strong> <?php echo date('M d, Y H:i', strtotime($item['end_time'])); ?>
                                                </p>
                                                <div class="mt-auto">
                                                    <a href="item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-primary">View Item</a>
                                                    <button class="btn btn-danger remove-watchlist" data-item-id="<?php echo $item['item_id']; ?>">
                                                        <i class="fas fa-heart-broken"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <p class="text-center">Your watchlist is empty.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle watchlist removal
        document.querySelectorAll('.remove-watchlist').forEach(button => {
            button.addEventListener('click', function() {
                const itemId = this.dataset.itemId;
                if (confirm('Remove this item from your watchlist?')) {
                    fetch('remove_watchlist.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'item_id=' + itemId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.closest('.listing-card').remove();
                        }
                    });
                }
            });
        });
    </script>
</body>
</html> 