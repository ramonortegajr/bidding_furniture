<?php
session_start();
require_once 'config/database.php';

// Get user information if logged in
$username = '';
if (isset($_SESSION['user_id'])) {
    $user_sql = "SELECT username FROM users WHERE user_id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $_SESSION['user_id']);
    $user_stmt->execute();
    $username = $user_stmt->get_result()->fetch_assoc()['username'];
}

// Fetch latest active furniture items with seller information
$latest_sql = "SELECT f.*, u.username as seller_name, c.name as category_name,
              (SELECT COUNT(*) FROM bids WHERE item_id = f.item_id) as bid_count 
              FROM furniture_items f 
              JOIN users u ON f.seller_id = u.user_id 
              JOIN categories c ON f.category_id = c.category_id 
              WHERE f.status = 'active' 
              AND f.end_time > NOW()  /* Only get items that haven't ended yet */
              ORDER BY f.created_at DESC 
              LIMIT 3";
$latest_result = $conn->query($latest_sql);

// Update status of ended auctions
$update_status_sql = "UPDATE furniture_items 
                     SET status = 'expired' 
                     WHERE end_time <= NOW() 
                     AND status = 'active'";
$conn->query($update_status_sql);

// Fetch newly expired auctions (ended within last 7 days)
$expired_sql = "SELECT f.*, u.username as seller_name, c.name as category_name,
               (SELECT COUNT(*) FROM bids WHERE item_id = f.item_id) as bid_count,
               (SELECT username FROM users WHERE user_id = 
                   (SELECT user_id FROM bids WHERE item_id = f.item_id ORDER BY bid_amount DESC LIMIT 1)
               ) as winner_name,
               (SELECT MAX(bid_amount) FROM bids WHERE item_id = f.item_id) as final_price
               FROM furniture_items f 
               JOIN users u ON f.seller_id = u.user_id 
               JOIN categories c ON f.category_id = c.category_id 
               WHERE (f.status = 'expired' OR (f.status = 'active' AND f.end_time <= NOW()))
               AND f.end_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               AND f.end_time <= NOW()
               ORDER BY f.end_time DESC 
               LIMIT 3";
$expired_result = $conn->query($expired_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniture Bidding System - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-couch me-2"></i>Furniture Bidding
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#latest">
                            <i class="fas fa-gavel me-1"></i>Latest Auctions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">
                            <i class="fas fa-info-circle me-1"></i>How It Works
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#footer">
                            <i class="fas fa-envelope me-1"></i>Contact
                        </a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php include 'includes/notifications.php'; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($username); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-gavel me-2"></i>My Bids</a></li>
                                <li><a class="dropdown-item" href="dashboard.php?tab=watchlist"><i class="fas fa-heart me-2"></i>Watchlist</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-primary btn-sm px-3" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="container">
                <h1>Welcome to Furniture Bidding</h1>
                <p class="lead">Discover unique furniture pieces and bid on your favorite items</p>
                <div class="mt-4">
                    <a href="furniture_list.php" class="btn btn-primary btn-lg me-2">Browse Auctions</a>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="furniture_list.php" class="btn btn-outline-light btn-lg">Start Bid</a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-outline-light btn-lg">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Latest Auctions -->
    <section id="latest" class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">Latest Auctions Added</h2>
            <div class="row">
                <?php if ($latest_result->num_rows > 0): ?>
                    <?php while ($item = $latest_result->fetch_assoc()): ?>
                        <div class="col-md-4">
                            <div class="card furniture-card h-100">
                                <div class="furniture-image-container">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="furniture-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                    <?php else: ?>
                                        <img src="assets/images/no-image.jpg" class="furniture-image" alt="No image available">
                                    <?php endif; ?>
                                    <div class="bid-count-badge position-absolute top-0 end-0 m-2">
                                        <span class="badge bg-primary">
                                            <i class="fas fa-gavel me-1"></i><?php echo $item['bid_count']; ?> bid<?php echo $item['bid_count'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                    <p class="card-text">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                        <small class="text-muted d-block mt-2">
                                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($item['seller_name']); ?>
                                        </small>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="current-price">₱<?php echo number_format($item['current_price'], 2); ?></div>
                                        <small class="text-danger">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php 
                                            $end_time = strtotime($item['end_time']);
                                            $now = time();
                                            $time_left = $end_time - $now;
                                            
                                            if ($time_left > 0) {
                                                $days = floor($time_left / (60 * 60 * 24));
                                                $hours = floor(($time_left % (60 * 60 * 24)) / (60 * 60));
                                                if ($days > 0) {
                                                    echo $days . "d " . $hours . "h left";
                                                } else {
                                                    $minutes = floor(($time_left % (60 * 60)) / 60);
                                                    echo $hours . "h " . $minutes . "m left";
                                                }
                                            } else {
                                                echo "Ended";
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top-0">
                                    <a href="item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-primary w-100">
                                        <i class="fas fa-arrow-right me-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <p class="text-center">No new auctions available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Newly Expired Auctions -->
    <section id="expired" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Recently Ended Auctions</h2>
            <div class="row">
                <?php if ($expired_result->num_rows > 0): ?>
                    <?php while ($item = $expired_result->fetch_assoc()): ?>
                        <div class="col-md-4">
                            <div class="card furniture-card h-100">
                                <div class="furniture-image-container">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="furniture-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                    <?php else: ?>
                                        <img src="assets/images/no-image.jpg" class="furniture-image" alt="No image available">
                                    <?php endif; ?>
                                    <div class="bid-count-badge position-absolute top-0 end-0 m-2">
                                        <span class="badge bg-danger">Auction Ended</span>
                                        <span class="badge bg-primary mt-2">
                                            <i class="fas fa-gavel me-1"></i><?php echo $item['bid_count']; ?> bid<?php echo $item['bid_count'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                    <p class="card-text">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                        <small class="text-muted d-block mt-2">
                                            <i class="fas fa-user me-1"></i>Seller: <?php echo htmlspecialchars($item['seller_name']); ?>
                                        </small>
                                    </p>
                                    <div class="auction-details">
                                        <div class="mb-2">
                                            <strong>Final Price:</strong> 
                                            <span class="text-success">₱<?php echo number_format($item['final_price'] ?? $item['current_price'], 2); ?></span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Total Bids:</strong> 
                                            <span class="text-primary"><?php echo $item['bid_count']; ?></span>
                                        </div>
                                        <?php if ($item['winner_name']): ?>
                                            <div class="mb-2">
                                                <strong>Winner:</strong> 
                                                <span class="text-success"><?php echo htmlspecialchars($item['winner_name']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>Ended: <?php echo date('M d, Y h:i A', strtotime($item['end_time'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top-0">
                                    <a href="item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-history me-1"></i>View Auction History
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <p class="text-center">No recently ended auctions.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <h2 class="text-center mb-5">How It Works</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="step-card">
                        <i class="fas fa-user-plus"></i>
                        <h4>Create Account</h4>
                        <p>Register as a bidder or seller to start participating in auctions</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <i class="fas fa-search"></i>
                        <h4>Find Furniture</h4>
                        <p>Browse through our collection of quality furniture pieces</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <i class="fas fa-gavel"></i>
                        <h4>Start Bidding</h4>
                        <p>Place your bids and win your favorite furniture items</p>
                    </div>
                </div>
            </div>
            </div>
        </section>

    <!-- Call to Action -->
    <section class="cta-section">
        <div class="container">
            <h2 class="mb-4">Ready to Start Bidding?</h2>
            <p class="lead mb-4">Join and start bidding today!</p>
            <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="furniture_list.php" class="btn btn-outline-light btn-lg">Start Bid</a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-outline-light btn-lg">Sign Up</a>
                    <?php endif; ?>
    </div>
    </section>

    <!-- Footer -->
    <footer id="footer" class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>About Furniture Bidding</h5>
                    <p>Your trusted platform for buying and selling quality furniture through online auctions.</p>
                </div>
                <div class="col-md-3">
                </div>
                <div class="col-md-3">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i> info@furniturebidding.com</li>
                        <li><i class="fas fa-phone me-2"></i> (123) 456-7890</li>
                    </ul>
                </div>
            </div>
            <div class="text-center mt-4">
                <p class="mb-0">&copy; 2024 Furniture Bidding System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 