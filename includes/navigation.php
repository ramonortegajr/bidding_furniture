<?php
require_once 'functions.php';
?>
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
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-home me-1"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="furniture_list.php">
                        <i class="fas fa-list me-1"></i>Browse Furniture
                    </a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                    // Check if user has bids or sold items
                    $show_winners_nav = checkWinnersNavVisibility($conn, $_SESSION['user_id']);
                    if ($show_winners_nav):
                    ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auction_winners.php">
                                <i class="fas fa-trophy me-1"></i>Winners
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php include 'notifications.php'; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="add_item.php">
                            <i class="fas fa-plus-circle me-1"></i>Add Item
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="my_bids.php"><i class="fas fa-gavel me-2"></i>My Bids</a></li>
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