<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/nav_helpers.php';

// Get unread chats count
$unread_chats = isset($_SESSION['user_id']) ? getUnreadChatsCount($conn, $_SESSION['user_id']) : 0;
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
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php?tab=listings">
                            <i class="fas fa-box me-1"></i>My Listings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_bids.php">
                            <i class="fas fa-gavel me-1"></i>My Bids
                        </a>
                    </li>
                    <li class="nav-item position-relative">
                        <a class="nav-link" href="chats.php">
                            <i class="fas fa-comments me-1"></i>Chats
                            <span class="chat-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: <?php echo $unread_chats > 0 ? 'flex' : 'none'; ?>">
                                <?php echo $unread_chats; ?>
                                <span class="visually-hidden">unread messages</span>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
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

<!-- Add JavaScript for checking notifications -->
<script>
    // Function to update chat badge
    function updateChatBadge() {
        const chatBadge = document.querySelector('.chat-badge');
        if (chatBadge) {
            fetch('check_unread_chats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.unread_count > 0) {
                        chatBadge.textContent = data.unread_count;
                        chatBadge.style.display = 'flex';
                    } else {
                        chatBadge.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error checking unread chats:', error));
        }
    }

    // Check for new messages every 5 seconds
    setInterval(updateChatBadge, 5000);

    // Initial check for messages
    updateChatBadge();

    // Check for ended auctions
    function checkEndedAuctions() {
        if (document.querySelector('.nav-link[href="auction_winners.php"]')) {
            fetch('check_ended_auctions.php')
                .then(response => response.json())
                .then(data => {
                    const winnersLink = document.querySelector('.nav-link[href="auction_winners.php"]');
                    const existingBadge = winnersLink.querySelector('.badge');
                    
                    if (data.new_count > 0) {
                        if (existingBadge) {
                            existingBadge.textContent = data.new_count;
                        } else {
                            const badge = document.createElement('span');
                            badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                            badge.textContent = data.new_count;
                            badge.innerHTML += '<span class="visually-hidden">new ended auctions</span>';
                            winnersLink.classList.add('position-relative');
                            winnersLink.appendChild(badge);
                        }
                        
                        // Reload page if we're on auction_winners.php
                        if (window.location.pathname.endsWith('auction_winners.php')) {
                            window.location.reload();
                        }
                    } else if (existingBadge) {
                        existingBadge.remove();
                    }
                });
        }
    }

    // Check every 30 seconds
    setInterval(checkEndedAuctions, 30000);
    // Initial check
    checkEndedAuctions();
</script> 