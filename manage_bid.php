<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Function to create notification
function createNotification($conn, $user_id, $item_id, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, item_id, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $user_id, $item_id, $message);
    $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $bid_id = isset($_POST['bid_id']) ? intval($_POST['bid_id']) : 0;
        
        // Verify bid belongs to user and get seller info
        $check_bid = $conn->prepare("SELECT b.*, f.end_time, f.status, f.item_id, f.seller_id, f.title 
                                   FROM bids b 
                                   JOIN furniture_items f ON b.item_id = f.item_id 
                                   WHERE b.bid_id = ? AND b.user_id = ?");
        $check_bid->bind_param("ii", $bid_id, $user_id);
        $check_bid->execute();
        $result = $check_bid->get_result();
        
        if ($result->num_rows === 0) {
            $response['message'] = "Bid not found or you don't have permission to modify it.";
        } else {
            $bid_data = $result->fetch_assoc();
            
            // Check if auction has ended
            if (strtotime($bid_data['end_time']) < time() || $bid_data['status'] === 'expired') {
                $response['message'] = "Cannot modify bid for ended auction.";
            } else {
                switch ($_POST['action']) {
                    case 'delete':
                        $delete_bid = $conn->prepare("DELETE FROM bids WHERE bid_id = ? AND user_id = ?");
                        $delete_bid->bind_param("ii", $bid_id, $user_id);
                        
                        if ($delete_bid->execute()) {
                            // Update current price to next highest bid or starting price
                            $update_price = $conn->prepare("UPDATE furniture_items f 
                                                         SET current_price = COALESCE(
                                                             (SELECT MAX(bid_amount) FROM bids WHERE item_id = f.item_id),
                                                             f.starting_price
                                                         )
                                                         WHERE item_id = ?");
                            $update_price->bind_param("i", $bid_data['item_id']);
                            $update_price->execute();
                            
                            // Notify seller about bid deletion
                            $delete_message = "A bidder has withdrawn their bid of ₱" . number_format($bid_data['bid_amount'], 2) . " on your item: " . $bid_data['title'];
                            createNotification($conn, $bid_data['seller_id'], $bid_data['item_id'], $delete_message);
                            
                            $response['success'] = true;
                            $response['message'] = "Bid successfully deleted.";
                        } else {
                            $response['message'] = "Error deleting bid.";
                        }
                        break;
                        
                    case 'edit':
                        $new_amount = isset($_POST['new_amount']) ? floatval($_POST['new_amount']) : 0;
                        $old_amount = $bid_data['bid_amount'];
                        
                        if ($new_amount <= 0) {
                            $response['message'] = "Invalid bid amount.";
                            break;
                        }
                        
                        // Get current highest bid
                        $check_highest = $conn->prepare("SELECT MAX(bid_amount) as highest_bid FROM bids WHERE item_id = ?");
                        $check_highest->bind_param("i", $bid_data['item_id']);
                        $check_highest->execute();
                        $highest_result = $check_highest->get_result()->fetch_assoc();
                        
                        if ($new_amount <= $highest_result['highest_bid'] && $bid_data['bid_amount'] != $highest_result['highest_bid']) {
                            $response['message'] = "New bid must be higher than current highest bid.";
                            break;
                        }
                        
                        // Start transaction
                        $conn->begin_transaction();
                        
                        try {
                            // Update bid amount
                            $update_bid = $conn->prepare("UPDATE bids SET bid_amount = ? WHERE bid_id = ? AND user_id = ?");
                            $update_bid->bind_param("dii", $new_amount, $bid_id, $user_id);
                            $update_bid->execute();
                            
                            // Update current price in furniture_items
                            $update_price = $conn->prepare("UPDATE furniture_items SET current_price = ? WHERE item_id = ?");
                            $update_price->bind_param("di", $new_amount, $bid_data['item_id']);
                            $update_price->execute();
                            
                            // Create notification for seller
                            $update_message = "A bidder has updated their bid from ₱" . number_format($old_amount, 2) . 
                                            " to ₱" . number_format($new_amount, 2) . " on your item: " . $bid_data['title'];
                            createNotification($conn, $bid_data['seller_id'], $bid_data['item_id'], $update_message);
                            
                            // Notify other bidders
                            $notify_bidders = $conn->prepare("SELECT DISTINCT user_id FROM bids 
                                                           WHERE item_id = ? AND user_id != ? AND user_id != ?");
                            $notify_bidders->bind_param("iii", $bid_data['item_id'], $user_id, $bid_data['seller_id']);
                            $notify_bidders->execute();
                            $other_bidders = $notify_bidders->get_result();
                            
                            while ($bidder = $other_bidders->fetch_assoc()) {
                                $outbid_message = "Another bidder has increased their bid to ₱" . number_format($new_amount, 2) . 
                                                " on item: " . $bid_data['title'];
                                createNotification($conn, $bidder['user_id'], $bid_data['item_id'], $outbid_message);
                            }
                            
                            $conn->commit();
                            $response['success'] = true;
                            $response['message'] = "Bid successfully updated.";
                        } catch (Exception $e) {
                            $conn->rollback();
                            $response['message'] = "Error updating bid.";
                        }
                        break;
                        
                    default:
                        $response['message'] = "Invalid action.";
                }
            }
        }
    } else {
        $response['message'] = "No action specified.";
    }
}

header('Content-Type: application/json');
echo json_encode($response); 