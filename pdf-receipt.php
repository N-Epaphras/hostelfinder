<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['booking_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(400);
    die('Invalid request');
}

$booking_id = intval($_GET['booking_id']);
$user_id = $_SESSION['user_id'];

// Fetch booking details with hostel info
$stmt = $conn->prepare("
    SELECT b.id, b.amount, b.status, b.duration, b.student_phone, b.created_at,
           h.name as hostel_name, u.name as user_name, u.email as user_email
    FROM bookings b 
    JOIN hostels h ON b.hostel_id = h.id 
    LEFT JOIN users u ON b.user_id = u.id 
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    http_response_code(404);
    die('Booking not found');
}

$status_class = $booking['status'] === 'confirmed' ? 'confirmed' : 'pending';
$status_icon = $booking['status'] === 'confirmed' ? '✓' : '⏳';

// PDF Options
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true); // For logo

// HTML Content
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 20px; color: #333; background: white; }
        .header { text-align: center; border-bottom: 3px solid #2e8b57; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { width: 100px; height: auto; margin-bottom: 10px; }
        .title { color: #2e8b57; font-size: 28px; font-weight: bold; margin: 0; }
        .receipt-container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border: 2px solid #2e8b57; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .receipt-header { text-align: center; margin-bottom: 30px; }
        .status { font-size: 24px; font-weight: bold; padding: 10px 20px; border-radius: 25px; display: inline-block; margin-bottom: 20px; }
        .confirmed { background: #d4edda; color: #155724; border: 2px solid #c3e6cb; }
        .pending { background: #fff3cd; color: #856404; border: 2px solid #ffeaa7; }
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0; }
        .detail-item { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #2e8b57; }
        .detail-label { font-weight: bold; color: #2e8b57; margin-bottom: 5px; display: block; }
        .detail-value { font-size: 16px; }
        .amount { font-size: 24px; font-weight: bold; color: #2e8b57; text-align: center; margin: 20px 0; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
        @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <img src="images/logo.png" alt="Hostel Finder Logo" class="logo">
            <h1 class="title">BOOKING RECEIPT</h1>
        </div>
        
        <div class="receipt-header">
            <div class="status ' . $status_class . '">' . $status_icon . ' ' . ucfirst($booking['status']) . ' Receipt</div>
        </div>
        
        <div class="details-grid">
            <div class="detail-item">
                <span class="detail-label">Booking ID</span>
                <span class="detail-value">#' . $booking['id'] . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Date</span>
                <span class="detail-value">' . date('M d, Y H:i', strtotime($booking['created_at'])) . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Hostel</span>
                <span class="detail-value">' . htmlspecialchars($booking['hostel_name']) . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Duration</span>
                <span class="detail-value">' . $booking['duration'] . ' days</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Student Phone</span>
                <span class="detail-value">' . htmlspecialchars($booking['student_phone']) . '</span>
            </div>
            ' . (!empty($booking['user_name']) ? '
            <div class="detail-item">
                <span class="detail-label">Student Name</span>
                <span class="detail-value">' . htmlspecialchars($booking['user_name']) . '</span>
            </div>' : '') . '
            ' . (!empty($booking['user_email']) ? '
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value">' . htmlspecialchars($booking['user_email']) . '</span>
            </div>' : '') . '
        </div>
        
        <div class="amount">UGX ' . number_format($booking['amount'], 0) . '</div>
        
        <div class="footer">
            <p>Thank you for choosing Hostel Finder!</p>
            <p>Print or save this receipt for your records.</p>
            <p>Contact support if you have any questions.</p>
        </div>
    </div>
</body>
</html>';

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "receipt_booking_{$booking_id}_" . date('Y-m-d') . ".pdf";

$dompdf->stream($filename, ["Attachment" => true]);
?>
