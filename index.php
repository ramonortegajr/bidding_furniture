<?php
session_start();
require_once 'config/database.php';

// Fetch latest furniture items with seller information
$sql = "SELECT f.*, u.username as seller_name, c.name as category_name 
        FROM furniture_items f 
        JOIN users u ON f.seller_id = u.user_id 
        JOIN categories c ON f.category_id = c.category_id 
        WHERE f.status = 'active' 
        ORDER BY f.created_at DESC 
        LIMIT 6";
$result = $conn->query($sql);
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
            <a class="navbar-brand" href="index.php">Furniture Bidding</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#featured">Latest Auctions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">How It Works</a>
                    </li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white px-3 mx-2" href="login.php">Login/Register</a>
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
                    <?php if(!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="btn btn-outline-light btn-lg">Start Bidding</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Latest Auctions -->
    <section id="featured" class="featured-items">
        <div class="container">
            <h2 class="text-center mb-5">Latest Auction Items</h2>
            <div class="row">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($item = $result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card furniture-card">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="furniture-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                <?php else: ?>
                                    <img src="assets/images/no-image.jpg" class="furniture-image" alt="No image available">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                    <p class="card-text">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                    </p>
                                    <p class="seller-info">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['seller_name']); ?>
                                    </p>
                                    <p class="current-price">
                                        ₱<?php echo number_format($item['current_price'], 2); ?>
                                    </p>
                                    <p class="time-left">
                                        <i class="fas fa-clock"></i> Ends: <?php echo date('M d, Y H:i', strtotime($item['end_time'])); ?>
                                    </p>
                                </div>
                                <div class="card-footer">
                                    <a href="item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-primary w-100">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <p class="text-center">No auction items available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-center mt-4">
                <a href="furniture_list.php" class="btn btn-outline-primary btn-lg">View All Auctions</a>
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
            <a href="login.php" class="btn btn-light btn-lg">Sign Up Now</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
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