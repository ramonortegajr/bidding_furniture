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

// Get all bids (both active and ended) for the user
$bids_sql = "SELECT 
    b.*, 
    f.title,
    f.image_url,
    f.end_time,
    f.status,
    f.current_price,
    f.seller_id,
    u.username as seller_name,
    CASE 
        WHEN f.end_time > NOW() THEN 'ongoing'
        ELSE 'ended'
    END as auction_status,
    CASE 
        WHEN b.bid_amount = f.current_price THEN 1
        ELSE 0
    END as is_highest_bidder,
    (SELECT COUNT(*) FROM bids WHERE item_id = f.item_id) as total_bids
    FROM bids b
    JOIN furniture_items f ON b.item_id = f.item_id
    JOIN users u ON f.seller_id = u.user_id
    WHERE b.user_id = ?
    ORDER BY 
        CASE 
            WHEN f.end_time > NOW() THEN 0
            ELSE 1
        END,
        f.end_time DESC";

$bids_stmt = $conn->prepare($bids_sql);
$bids_stmt->bind_param("i", $user_id);
$bids_stmt->execute();
$bids_result = $bids_stmt->get_result();

// Group bids by status
$ongoing_bids = [];
$ended_bids = [];

while ($bid = $bids_result->fetch_assoc()) {
    if ($bid['auction_status'] === 'ongoing') {
        $ongoing_bids[] = $bid;
    } else {
        $ended_bids[] = $bid;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bids - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation_common.php'; ?>

    <div class="container" style="margin-top: 80px;">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">My Bids</li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-gavel me-2"></i>My Bids
                </h2>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="myBidsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ongoing-tab" data-bs-toggle="tab" data-bs-target="#ongoing" type="button" role="tab">
                            <i class="fas fa-clock me-1"></i>Ongoing Bids
                            <?php if (count($ongoing_bids) > 0): ?>
                                <span class="badge bg-primary ms-1"><?php echo count($ongoing_bids); ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="ended-tab" data-bs-toggle="tab" data-bs-target="#ended" type="button" role="tab">
                            <i class="fas fa-flag-checkered me-1"></i>Ended Bids
                            <?php if (count($ended_bids) > 0): ?>
                                <span class="badge bg-secondary ms-1"><?php echo count($ended_bids); ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="myBidsTabContent">
                    <!-- Ongoing Bids Tab -->
                    <div class="tab-pane fade show active" id="ongoing" role="tabpanel">
                        <?php if (!empty($ongoing_bids)): ?>
                            <div class="row">
                                <?php foreach ($ongoing_bids as $bid): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="row g-0">
                                                <div class="col-md-4">
                                                    <img src="<?php echo htmlspecialchars($bid['image_url'] ?: 'assets/images/no-image.jpg'); ?>" 
                                                         class="img-fluid rounded-start" 
                                                         alt="<?php echo htmlspecialchars($bid['title']); ?>"
                                                         style="height: 200px; width: 100%; object-fit: cover;">
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="card-body">
                                                        <h5 class="card-title"><?php echo htmlspecialchars($bid['title']); ?></h5>
                                                        
                                                        <!-- Bid Status -->
                                                        <div class="mb-3">
                                                            <?php if ($bid['is_highest_bidder']): ?>
                                                                <span class="badge bg-success">
                                                                    <i class="fas fa-trophy me-1"></i>Highest Bidder
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark">
                                                                    <i class="fas fa-exclamation-circle me-1"></i>Outbid
                                                                </span>
                                                            <?php endif; ?>
                                                            <span class="badge bg-info ms-1">
                                                                <i class="fas fa-gavel me-1"></i><?php echo $bid['total_bids']; ?> total bids
                                                            </span>
                                                        </div>

                                                        <!-- Bid Details -->
                                                        <p class="card-text">
                                                            <small class="text-muted">Seller: <?php echo htmlspecialchars($bid['seller_name']); ?></small><br>
                                                            <span class="d-block mb-1">
                                                                <strong>Your Bid:</strong> 
                                                                <span class="text-primary">₱<?php echo number_format($bid['bid_amount'], 2); ?></span>
                                                            </span>
                                                            <span class="d-block mb-2">
                                                                <strong>Current Price:</strong> 
                                                                <span class="text-success">₱<?php echo number_format($bid['current_price'], 2); ?></span>
                                                            </span>
                                                            <small class="text-danger">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php
                                                                $end_time = strtotime($bid['end_time']);
                                                                $now = time();
                                                                $time_left = $end_time - $now;
                                                                
                                                                if ($time_left > 0) {
                                                                    $days = floor($time_left / (60 * 60 * 24));
                                                                    $hours = floor(($time_left % (60 * 60 * 24)) / (60 * 60));
                                                                    $minutes = floor(($time_left % (60 * 60)) / 60);
                                                                    
                                                                    if ($days > 0) {
                                                                        echo $days . "d " . $hours . "h left";
                                                                    } elseif ($hours > 0) {
                                                                        echo $hours . "h " . $minutes . "m left";
                                                                    } else {
                                                                        echo $minutes . "m left";
                                                                    }
                                                                } else {
                                                                    echo "Ending soon";
                                                                }
                                                                ?>
                                                            </small>
                                                        </p>

                                                        <!-- Action Buttons -->
                                                        <div class="mt-auto">
                                                            <a href="item.php?id=<?php echo $bid['item_id']; ?>" class="btn btn-primary btn-sm">
                                                                <i class="fas fa-eye me-1"></i>View Item
                                                            </a>
                                                            <a href="place_bid.php?id=<?php echo $bid['item_id']; ?>&edit=1&amount=<?php echo $bid['bid_amount']; ?>" 
                                                               class="btn btn-success btn-sm">
                                                                <i class="fas fa-edit me-1"></i>Update Bid
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>You have no ongoing bids
                                <div class="mt-3">
                                    <a href="furniture_list.php" class="btn btn-primary">Browse Auctions</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Ended Bids Tab -->
                    <div class="tab-pane fade" id="ended" role="tabpanel">
                        <?php if (!empty($ended_bids)): ?>
                            <div class="row">
                                <?php foreach ($ended_bids as $bid): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="row g-0">
                                                <div class="col-md-4">
                                                    <img src="<?php echo htmlspecialchars($bid['image_url'] ?: 'assets/images/no-image.jpg'); ?>" 
                                                         class="img-fluid rounded-start" 
                                                         alt="<?php echo htmlspecialchars($bid['title']); ?>"
                                                         style="height: 200px; width: 100%; object-fit: cover;">
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="card-body">
                                                        <h5 class="card-title"><?php echo htmlspecialchars($bid['title']); ?></h5>
                                                        
                                                        <!-- Auction Result -->
                                                        <div class="mb-3">
                                                            <?php if ($bid['is_highest_bidder']): ?>
                                                                <div class="alert alert-success mb-2 py-2">
                                                                    <i class="fas fa-trophy me-1"></i>
                                                                    <strong>Congratulations!</strong> You won this auction!
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="alert alert-secondary mb-2 py-2">
                                                                    <i class="fas fa-info-circle me-1"></i>
                                                                    You didn't win this auction
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Bid Details -->
                                                        <p class="card-text">
                                                            <small class="text-muted">Seller: <?php echo htmlspecialchars($bid['seller_name']); ?></small><br>
                                                            <span class="d-block mb-1">
                                                                <strong>Your Bid:</strong> 
                                                                <span class="text-primary">₱<?php echo number_format($bid['bid_amount'], 2); ?></span>
                                                            </span>
                                                            <span class="d-block mb-2">
                                                                <strong>Final Price:</strong> 
                                                                <span class="text-success">₱<?php echo number_format($bid['current_price'], 2); ?></span>
                                                            </span>
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar-alt me-1"></i>
                                                                Ended: <?php echo date('M d, Y h:i A', strtotime($bid['end_time'])); ?>
                                                            </small>
                                                        </p>

                                                        <!-- Action Button -->
                                                        <a href="item.php?id=<?php echo $bid['item_id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-info-circle me-1"></i>View Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>You have no ended bids
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 