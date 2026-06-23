<?php
session_start();
require_once 'db.php';
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Fetch user info for PesaPal
$user_id = $_SESSION['user_id'];
$u_stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$user_info = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();
$username_parts = explode(' ', $user_info['username'] ?? 'Student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$hostel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$duration = intval($_POST['duration'] ?? 0);
$start_date = trim($_POST['start_date'] ?? '');
$student_phone = trim($_POST['student_phone'] ?? '');

if (empty($start_date) || strtotime($start_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'error' => 'Invalid check-in date']);
    exit;
}

if (!$hostel_id || $duration <= 0 || empty($student_phone)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Fetch hostel min_booking_fee
$stmt = $conn->prepare("SELECT min_booking_fee FROM hostels WHERE id = ?");
$stmt->bind_param("i", $hostel_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Hostel not found']);
    exit;
}
$hostel = $result->fetch_assoc();
$min_fee = floatval($hostel['min_booking_fee']);
$amount = $min_fee * $duration;

$checkout_date_obj = new DateTime($start_date);
$checkout_date_obj->add(new DateInterval("P{$duration}D"));
$checkout_date = $checkout_date_obj->format('Y-m-d');

// Step 1: Create initial booking record (Status: pending)
// This follows PesaPal guidance to store details in DB before loading the iframe
$stmt = $conn->prepare("INSERT INTO bookings (user_id, hostel_id, duration, checkin_date, checkout_date, payment_method, amount, status, student_phone) VALUES (?, ?, ?, ?, ?, 'mobile_money', ?, 'pending', ?)");
$stmt->bind_param("iiisssds", $user_id, $hostel_id, $duration, $start_date, $checkout_date, $amount, $student_phone);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Failed to create temporary booking record']);
    exit;
}

$booking_id = $conn->insert_id;
$tx_ref = (string)$booking_id; // Use booking ID as the unique PesaPal reference
$stmt->close();

// Step 2: Get PesaPal Token
$token = getPesapalToken();
if (!$token) {
    global $pesapal_last_error;
    echo json_encode(['success' => false, 'error' => 'PesaPal Auth Failed: ' . ($pesapal_last_error ?: 'Unknown connection error')]);
    exit;
}

$_SESSION['pesapal_token'] = $token;

// Step 3: Submit Order Request
$callback_url = "http://".$_SERVER['HTTP_HOST']."/hostelfinder/verify-payment.php";
$order_data = [
    "id" => $tx_ref,
    "currency" => CURRENCY,
    "amount" => floatval($amount),
    "description" => "Hostel Booking: " . $hostel_id,
    "callback_url" => $callback_url,
    "notification_id" => "", 
    "billing_address" => [
        "email_address" => $user_info['email'] ?? 'student@example.com',
        "phone_number" => $student_phone,
        "country_code" => "UG",
        "first_name" => $username_parts[0] ?? 'Student',
        "middle_name" => "",
        "last_name" => $username_parts[1] ?? '',
        "line_1" => "Hostel Address",
        "line_2" => "",
        "city" => "Kabale",
        "state" => "",
        "postal_code" => "",
        "zip_code" => ""
    ]
];

$submit_response = pesapalRequest(PESAPAL_BASE_URL . "/api/Transactions/SubmitOrderRequest", 'POST', $order_data, $token);

if (!$submit_response || !isset($submit_response['redirect_url'])) {
    echo json_encode(['success' => false, 'error' => 'Failed to initiate PesaPal order']);
    exit;
}

echo json_encode([
    'success' => true,
    'redirect_url' => $submit_response['redirect_url']
]);
?>
