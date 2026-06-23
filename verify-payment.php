<?php
session_start();
require_once 'db.php';
require_once 'config.php';

header('Content-Type: application/json');

// Pesapal V3 returns OrderTrackingId and OrderReference
if (!isset($_GET['OrderTrackingId']) || !isset($_GET['OrderReference'])) {
    echo json_encode(['success' => false, 'error' => 'Missing transaction identifiers']);
    exit;
}

$order_tracking_id = $_GET['OrderTrackingId'];
$tx_ref = $_GET['OrderReference'];
$booking_id = intval($tx_ref);

// Verify booking exists and is currently pending
$stmt_check = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND status = 'pending'");
$stmt_check->bind_param("i", $booking_id);
$stmt_check->execute();
$pending = $stmt_check->get_result()->fetch_assoc();

if (!$pending) {
    echo json_encode(['success' => false, 'error' => 'Booking not found or already confirmed']);
    exit;
}

$token = $_SESSION['pesapal_token'] ?? (defined('PESAPAL_TOKEN') ? PESAPAL_TOKEN : null);
$verify_response = pesapalRequest(PESAPAL_BASE_URL . "/api/Transactions/GetTransactionStatus?orderTrackingId=" . $order_tracking_id, 'GET', null, $token);

if (!$verify_response || !isset($verify_response['payment_status_description']) || $verify_response['payment_status_description'] !== 'Completed') {
    echo json_encode(['success' => false, 'error' => 'Payment verification failed or payment incomplete. Status: ' . ($verify_response['payment_status_description'] ?? 'Unknown')]);
    exit;
}

// Payment successful - Update booking to confirmed
$stmt = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
$stmt->bind_param("i", $booking_id);

// Assign available room (no date overlap)
$room_stmt = $conn->prepare("
    SELECT r.id 
    FROM rooms r 
    LEFT JOIN bookings b ON r.booking_id = b.id 
    WHERE r.hostel_id = ? AND r.status = 'available'
    AND (b.checkin_date > ? OR b.checkout_date < ? OR b.id IS NULL)
    LIMIT 1
");
$room_stmt->bind_param("iss", $pending['hostel_id'], $pending['checkout_date'], $pending['start_date']);
$room_stmt->execute();
$room_result = $room_stmt->get_result();
$room = $room_result->fetch_assoc();

if ($stmt->execute()) {
    // Assign room if available
    if ($room) {
        $update_room = $conn->prepare("UPDATE rooms SET status = 'booked', booking_id = ? WHERE id = ?");
        $update_room->bind_param("ii", $booking_id, $room['id']);
        $update_room->execute();
        $update_room->close();
    }
    $stmt->close();
    $room_stmt->close();
    unset($_SESSION['pending_booking']); // Clear pending
    
    $redirect_url = "hostel-details.php?id=" . $pending['hostel_id'] . "&booking_success=" . $booking_id;
    
    // Check if AJAX request or Flutterwave redirect (non-AJAX)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_POST['flutterwave_token'])) {
        // AJAX response
        echo json_encode([
            'success' => true,
            'booking_id' => $booking_id,
            'message' => 'Payment successful! Booking confirmed.',
            'redirect' => $redirect_url
        ]);
    } else {
        // Full page redirect (Flutterwave checkout redirect)
        echo "<script>window.location.href = '$redirect_url';</script>";
        echo "<p>Redirecting to receipt...</p>";
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save booking']);
    unset($_SESSION['pending_booking']);
}
?>
