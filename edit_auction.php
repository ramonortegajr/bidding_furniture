<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if item ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: furniture_list.php');
    exit();
}

$item_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Get item details and verify ownership
$sql = "SELECT f.*, c.name as category_name, c.category_id 
        FROM furniture_items f 
        JOIN categories c ON f.category_id = c.category_id 
        WHERE f.item_id = ? AND f.seller_id = ? AND f.status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $item_id, $user_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

// If item not found or user is not the seller, redirect
if (!$item) {
    header('Location: furniture_list.php');
    exit();
}

// Get all categories for the dropdown
$categories_sql = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = intval($_POST['category_id']);
    $condition_status = trim($_POST['condition_status']);
    $end_time = $_POST['end_time'];

    if (empty($title) || empty($description) || empty($condition_status) || empty($end_time)) {
        $error_message = "All fields are required.";
    } else {
        try {
            $conn->begin_transaction();

            // Update item details
            $update_sql = "UPDATE furniture_items 
                          SET title = ?, description = ?, category_id = ?, 
                              condition_status = ?, end_time = ? 
                          WHERE item_id = ? AND seller_id = ? AND status = 'active'";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssissii", 
                $title, $description, $category_id, 
                $condition_status, $end_time, $item_id, $user_id
            );

            if ($update_stmt->execute()) {
                // Handle image upload if new image is provided
                if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
                    $image = $_FILES['image'];
                    $image_name = $image['name'];
                    $image_tmp = $image['tmp_name'];
                    $image_size = $image['size'];
                    $image_error = $image['error'];

                    // Validate image
                    $valid_types = ['image/jpeg', 'image/png', 'image/jpg'];
                    $file_type = mime_content_type($image_tmp);

                    if ($image_error === 0 && in_array($file_type, $valid_types)) {
                        $new_image_name = uniqid('furniture_') . '.' . pathinfo($image_name, PATHINFO_EXTENSION);
                        $upload_path = 'uploads/' . $new_image_name;

                        if (move_uploaded_file($image_tmp, $upload_path)) {
                            // Update image URL in database
                            $image_sql = "UPDATE furniture_items SET image_url = ? WHERE item_id = ?";
                            $image_stmt = $conn->prepare($image_sql);
                            $image_stmt->bind_param("si", $upload_path, $item_id);
                            $image_stmt->execute();
                        }
                    }
                }

                $conn->commit();
                $success_message = "Auction updated successfully!";
                
                // Refresh item details
                $stmt->execute();
                $item = $stmt->get_result()->fetch_assoc();
            } else {
                throw new Exception("Failed to update auction.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get username for the navigation bar
$username = '';
$user_sql = "SELECT username FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$username = $user_stmt->get_result()->fetch_assoc()['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Auction - <?php echo htmlspecialchars($item['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation.php'; ?>

    <div class="container" style="margin-top: 80px;">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Auction</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($item['title']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category_id" required>
                                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                                        <option value="<?php echo $category['category_id']; ?>" 
                                                <?php echo $category['category_id'] == $item['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="condition" class="form-label">Condition</label>
                                <select class="form-select" id="condition" name="condition_status" required>
                                    <option value="New" <?php echo $item['condition_status'] == 'New' ? 'selected' : ''; ?>>New</option>
                                    <option value="Like New" <?php echo $item['condition_status'] == 'Like New' ? 'selected' : ''; ?>>Like New</option>
                                    <option value="Good" <?php echo $item['condition_status'] == 'Good' ? 'selected' : ''; ?>>Good</option>
                                    <option value="Fair" <?php echo $item['condition_status'] == 'Fair' ? 'selected' : ''; ?>>Fair</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="datetime-local" class="form-control" id="end_time" name="end_time" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($item['end_time'])); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">New Image (optional)</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <?php if ($item['image_url']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Current image:</small><br>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="Current item image" class="img-thumbnail mt-2" 
                                             style="max-height: 100px;">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                                <a href="item.php?id=<?php echo $item_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html> 