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

// Fetch ended auctions
$expired_sql = "SELECT 
    f.*, 
    u.username as seller_name, 
    c.name as category_name,
               (SELECT COUNT(*) FROM bids WHERE item_id = f.item_id) as bid_count,
    (
        SELECT u2.username 
        FROM bids b2 
        JOIN users u2 ON b2.user_id = u2.user_id 
        WHERE b2.item_id = f.item_id 
        AND b2.bid_amount = (
            SELECT MAX(bid_amount) 
            FROM bids 
            WHERE item_id = f.item_id
        )
        ORDER BY b2.bid_time ASC 
        LIMIT 1
               ) as winner_name,
    (
        SELECT MAX(bid_amount) 
        FROM bids 
        WHERE item_id = f.item_id
    ) as final_price
               FROM furniture_items f 
               JOIN users u ON f.seller_id = u.user_id 
               JOIN categories c ON f.category_id = c.category_id 
    WHERE f.end_time <= NOW()
    AND f.status IN ('completed', 'expired')
               ORDER BY f.end_time DESC 
               LIMIT 3";
$expired_result = $conn->query($expired_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .winner-badge {
            transform: rotate(-10deg);
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .winner-name {
            position: relative;
            padding: 1rem;
            border-radius: 8px;
            background: rgba(25, 135, 84, 0.1);
            margin-bottom: 1rem;
        }
        
        .trophy-icon {
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        
        .auction-result {
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        @keyframes confetti {
            0% { transform: translateY(0) rotate(0); opacity: 1; }
            100% { transform: translateY(-100px) rotate(360deg); opacity: 0; }
        }
        
        .confetti-animation::before,
        .confetti-animation::after {
            content: '🎉';
            position: absolute;
            top: 0;
            left: 10px;
            animation: confetti 1s ease-out infinite;
        }
        
        .confetti-animation::after {
            content: '🎊';
            left: auto;
            right: 10px;
            animation-delay: 0.25s;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation_common.php'; ?>

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

    <!-- Ended Auctions -->
    <section id="expired" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">
                <i class="fas fa-gavel text-primary me-2"></i>Ended Auctions
            </h2>
            <p class="text-center text-muted mb-4">Check out the results of our recently ended furniture auctions</p>
            <div class="row justify-content-center">
                <?php if ($expired_result->num_rows > 0): ?>
                    <?php while ($item = $expired_result->fetch_assoc()): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card furniture-card h-100 border-0 shadow-sm">
                                <div class="furniture-image-container">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             class="furniture-image" 
                                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                                             style="height: 250px; object-fit: cover; width: 100%;">
                                    <?php else: ?>
                                        <img src="assets/images/no-image.jpg" 
                                             class="furniture-image" 
                                             alt="No image available"
                                             style="height: 250px; object-fit: cover; width: 100%;">
                                    <?php endif; ?>
                                    <div class="bid-count-badge position-absolute top-0 end-0 m-2">
                                        <span class="badge bg-danger">
                                            <i class="fas fa-flag-checkered me-1"></i>Ended
                                        </span>
                                        <span class="badge bg-primary mt-2">
                                            <i class="fas fa-gavel me-1"></i><?php echo $item['bid_count']; ?> bid<?php echo $item['bid_count'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                    <div class="mb-3">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                        <small class="text-muted d-block mt-2">
                                            <i class="fas fa-user me-1"></i>Seller: <?php echo htmlspecialchars($item['seller_name']); ?>
                                        </small>
                                        </div>
                                    <div class="auction-result p-3 bg-light rounded">
                                        <?php if ($item['winner_name']): ?>
                                            <div class="winner-info text-center mb-3 confetti-animation">
                                                <div class="winner-badge">
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-trophy me-1"></i>Winner
                                                    </span>
                                                </div>
                                                <div class="winner-name">
                                                    <h5 class="mb-0 text-success"><?php echo htmlspecialchars($item['winner_name']); ?></h5>
                                                </div>
                                                <div class="final-price text-center">
                                                    <small class="text-muted">Final Price</small>
                                                    <h4 class="text-success mb-0">₱<?php echo number_format($item['final_price'], 2); ?></h4>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center text-muted">
                                                <i class="fas fa-info-circle me-2"></i>No bids were placed
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-3 text-center">
                                            <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>Ended <?php echo date('M d, Y h:i A', strtotime($item['end_time'])); ?>
                                            </small>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top-0 text-center">
                                    <a href="item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-info-circle me-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>No ended auctions found.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-center mt-4">
                <a href="auction_winners.php" class="btn btn-primary">
                    <i class="fas fa-trophy me-1"></i>View All Ended Auctions
                </a>
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
    <script>
        // Function to create trophy animation
        function initWinnerAnimations() {
            const winnerBadges = document.querySelectorAll('.winner-badge');
            winnerBadges.forEach(badge => {
                badge.addEventListener('mouseover', function() {
                    this.style.transform = 'rotate(0deg) scale(1.1)';
                });
                badge.addEventListener('mouseout', function() {
                    this.style.transform = 'rotate(-10deg) scale(1)';
                });
            });
        }

        // Initialize animations when the page loads
        document.addEventListener('DOMContentLoaded', initWinnerAnimations);
    </script>
</body>
</html> 