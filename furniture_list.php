<?php
session_start();
require_once 'config/database.php';

// Get categories for filter
$categories_sql = "SELECT * FROM categories GROUP BY name ORDER BY name";
$categories_result = $conn->query($categories_sql);

// Build query based on filters
$where_conditions = ["status = 'active'"];
$params = [];
$types = "";

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $where_conditions[] = "category_id = ?";
    $params[] = intval($_GET['category']);
    $types .= "i";
}

if (isset($_GET['condition']) && !empty($_GET['condition'])) {
    $where_conditions[] = "condition_status = ?";
    $params[] = $_GET['condition'];
    $types .= "s";
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(f.title LIKE ? OR f.description LIKE ?)";
    $search_term = "%" . $_GET['search'] . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Sorting
$sort_options = [
    'price_asc' => 'current_price ASC',
    'price_desc' => 'current_price DESC',
    'newest' => 'created_at DESC',
    'ending_soon' => 'end_time ASC'
];
$sort = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_options) 
    ? $sort_options[$_GET['sort']] 
    : 'created_at DESC';

// Construct and execute query
$sql = "SELECT f.*, c.name as category_name, u.username as seller_name,
        (SELECT COUNT(*) FROM bids b WHERE b.item_id = f.item_id) as bid_count,
        (SELECT MAX(b2.bid_amount) FROM bids b2 WHERE b2.item_id = f.item_id AND b2.user_id = ?) as user_bid 
        FROM furniture_items f 
        JOIN categories c ON f.category_id = c.category_id 
        JOIN users u ON f.seller_id = u.user_id
        WHERE " . implode(" AND ", $where_conditions) . 
        " ORDER BY " . $sort;

// Add user_id to the beginning of params array
array_unshift($params, isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
$types = "i" . $types; // Add integer type for user_id

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Furniture - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .furniture-card {
            height: 100%;
            transition: transform 0.2s;
        }
        .furniture-card:hover {
            transform: translateY(-5px);
        }
        .furniture-image {
            height: 200px;
            object-fit: cover;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .badge.bg-info {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
            margin: 5px 0;
            background-color: #0dcaf0 !important;
        }
        .badge.bg-success {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
            margin: 5px 3px;
            background-color: #198754 !important;
        }
        .badge i {
            margin-right: 3px;
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
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Search furniture...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                            <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Condition</label>
                    <select name="condition" class="form-select">
                        <option value="">Any</option>
                        <option value="New" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'New') ? 'selected' : ''; ?>>New</option>
                        <option value="Used" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'Used') ? 'selected' : ''; ?>>Used</option>
                        <option value="Refurbished" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'Refurbished') ? 'selected' : ''; ?>>Refurbished</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="newest" <?php echo (!isset($_GET['sort']) || $_GET['sort'] == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="ending_soon" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'ending_soon') ? 'selected' : ''; ?>>Ending Soon</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <div class="row">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($item = $result->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card furniture-card">
                            <?php if ($item['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="card-img-top furniture-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php else: ?>
                                <img src="assets/images/no-image.jpg" class="card-img-top furniture-image" alt="No image available">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                <p class="card-text">
                                    <small class="text-muted">
                                        Category: <?php echo htmlspecialchars($item['category_name']); ?><br>
                                        Condition: <?php echo htmlspecialchars($item['condition_status']); ?><br>
                                        Seller: <?php echo htmlspecialchars($item['seller_name']); ?>
                                    </small>
                                </p>
                                <p class="card-text">
                                    <strong>Current Bid:</strong> ₱<?php echo number_format($item['current_price'], 2); ?><br>
                                    <span class="badge bg-info">
                                        <i class="fas fa-gavel"></i> <?php echo $item['bid_count']; ?> bid<?php echo $item['bid_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                    <?php if (isset($item['user_bid']) && $item['user_bid'] > 0): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Your bid: ₱<?php echo number_format($item['user_bid'], 2); ?>
                                        </span>
                                    <?php endif; ?><br>
                                    <small class="text-muted">
                                        Ends: <?php echo date('M d, Y h:i A', strtotime($item['end_time'])); ?>
                                    </small>
                                </p>
                                <div class="d-grid">
                                    <a href="item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-primary">View Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        No furniture items found matching your criteria.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 