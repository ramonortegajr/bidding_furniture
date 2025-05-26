<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user information including username
$user_sql = "SELECT username, email, created_at FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$username = $user_data['username'];

// Get user's auction statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM furniture_items WHERE seller_id = ?) as total_items_listed,
    (SELECT COUNT(*) FROM bids WHERE user_id = ?) as total_bids_placed,
    (SELECT COUNT(*) FROM furniture_items f 
     JOIN bids b ON f.item_id = b.item_id 
     WHERE b.user_id = ? AND b.bid_amount = f.current_price AND f.end_time <= NOW()) as auctions_won";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Get user information first (needed for profile image update)
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    
    // Initialize variables for query building
    $types = "sss"; // Start with full_name, phone, address
    $params = array($full_name, $phone, $address);
    $profile_image_update = "";
    $password_update = "";
    
    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($file_ext, $allowed)) {
            $new_filename = uniqid() . '.' . $file_ext;
            $upload_path = 'uploads/profiles/' . $new_filename;
            
            if (!file_exists('uploads/profiles')) {
                mkdir('uploads/profiles', 0777, true);
            }

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // Delete old profile image if exists
                if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                    unlink($user['profile_image']);
                }
                $profile_image_update = ", profile_image = ?";
                $params[] = $upload_path;
                $types .= "s";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }
    
    // Update password only if provided
    if (!empty($_POST['new_password'])) {
        if (strlen($_POST['new_password']) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $password_update = ", password = ?";
            $types .= "s";
            $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        }
    }
    
    if (!isset($error)) {
        // Add user_id to params array
        $types .= "i";
        $params[] = $user_id;
        
        $sql = "UPDATE users SET full_name = ?, phone = ?, address = ?" . $password_update . $profile_image_update . " WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Error updating profile. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .profile-image-container {
            width: 180px;
            height: 180px;
            margin: 0 auto;
            position: relative;
        }
        .profile-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 15px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }
        .profile-image:hover {
            transform: scale(1.05);
        }
        .profile-image-upload {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #f8f9fa;
        }
        .profile-image-upload:hover {
            background: #f8f9fa;
            transform: scale(1.1);
        }
        .profile-image-upload input {
            display: none;
        }
        .card {
            border: none;
            box-shadow: 0 0 20px rgba(0,0,0,0.08);
            border-radius: 15px;
            overflow: hidden;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.5rem;
        }
        .card-body {
            padding: 1.5rem;
        }
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }
        .text-muted {
            color: #6c757d !important;
        }
        .alert {
            border-radius: 10px;
            padding: 1rem 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation_common.php'; ?>

    <div class="container" style="margin-top: 80px;">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Profile Header Card -->
                <div class="card mb-4">
                    <div class="card-body text-center py-5">
                        <div class="profile-image-container mb-4">
                            <img src="<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : './images/default-profile.png'; ?>" 
                                 class="profile-image" 
                                 alt="Profile Image">
                            <label class="profile-image-upload">
                                <input type="file" name="profile_image" form="profile-form" accept="image/*">
                                <i class="fas fa-camera"></i>
                            </label>
                        </div>
                        <h3 class="mb-2"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                        <p class="text-muted mb-0">
                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($user['username']); ?>
                        </p>
                        <p class="text-muted">
                            <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?>
                        </p>
                    </div>
                </div>

                <!-- Profile Details Card -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Profile Details</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="profile-form">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <small class="text-muted">Email cannot be changed</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control">
                                <small class="text-muted">Leave blank to keep current password</small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form when profile image is selected
        document.querySelector('input[name="profile_image"]').addEventListener('change', function() {
            document.getElementById('profile-form').submit();
        });
    </script>
</body>
</html> 