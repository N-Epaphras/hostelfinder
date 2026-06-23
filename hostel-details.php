<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}
include 'db.php';
include 'notify-admin.php';
require_once 'vendor/autoload.php';

// Fetch current user email for payment processing
$user_id = $_SESSION['user_id'];
$u_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$user_data = $u_stmt->get_result()->fetch_assoc();
$current_user_email = $user_data['email'] ?? 'epaphrasnasasira21@gmail.com';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}


$hostel_id = intval($_GET['id']);

// Fetch hostel details from database
$sql = "SELECT * FROM hostels WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $hostel_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Hostel not found, redirect to index
    header("Location: index.php");
    exit();
}

$hostel = $result->fetch_assoc();

// Check available rooms (date-aware if dates provided)
$room_count = 0;
$proposed_start = $_POST['start_date'] ?? $_GET['start_date'] ?? '';
$proposed_duration = intval($_POST['duration'] ?? $_GET['duration'] ?? 0);

if ($proposed_start && $proposed_duration > 0) {
    $checkin_date = DateTime::createFromFormat('Y-m-d', $proposed_start);
    if ($checkin_date) {
        $checkout_date = clone $checkin_date;
        $checkout_date->add(new DateInterval("P{$proposed_duration}D"));
        $checkout_str = $checkout_date->format('Y-m-d');
        
        $rooms_query = "SELECT COUNT(*) as available_rooms FROM rooms r WHERE r.hostel_id = ? AND r.status = 'available'
                       AND NOT EXISTS (
                           SELECT 1 FROM bookings b 
                           WHERE b.room_id = r.id AND b.status = 'confirmed'
                           AND (
                               (b.checkin_date <= ? AND b.checkout_date > ?) OR
                               (b.checkin_date < ? AND b.checkout_date >= ?)
                           )
                       )";
        $rooms_stmt = $conn->prepare($rooms_query);
        $rooms_stmt->bind_param("issss", $hostel_id, $proposed_start, $proposed_start, $checkout_str, $proposed_start);
    }
} else {
    $rooms_query = "SELECT COUNT(*) as available_rooms FROM rooms WHERE hostel_id = ? AND status = 'available'";
    $rooms_stmt = $conn->prepare($rooms_query);
    $rooms_stmt->bind_param("i", $hostel_id);
}

$rooms_stmt->execute();
$rooms_result = $rooms_stmt->get_result();
$room_count = $rooms_result->fetch_assoc()['available_rooms'] ?? 0;
$rooms_stmt->close();

// Consolidated booking handling logic
$booking_success = '';
$booking_error = '';
$receipt_data = null; // Will hold receipt info as array
$whatsapp_url = '';

// 1. Handle payment success redirect first
$booking_success_id = isset($_GET['booking_success']) ? intval($_GET['booking_success']) : 0;
if ($booking_success_id > 0) {
    $stmt = $conn->prepare("SELECT b.*, h.name as hostel_name FROM bookings b JOIN hostels h ON b.hostel_id = h.id WHERE b.id = ? AND b.user_id = ?");
    $stmt->bind_param("ii", $booking_success_id, $_SESSION['user_id']);
    $stmt->execute();
    $result_check = $stmt->get_result();
    if ($booking = $result_check->fetch_assoc()) {
        $stmt->close();
        $booking_success = 'Payment successful! Booking confirmed.';
        $receipt_data = [
            'id' => $booking_success_id,
            'hostel_name' => $booking['hostel_name'],
            'amount' => $booking['amount'],
            'status' => 'confirmed'
        ];
        $message = 'Booking confirmed ID #' . $booking_success_id . ' for ' . $booking['hostel_name'];
        $whatsapp_url = 'https://wa.me/' . $hostel['contact'] . '?text=' . urlencode($message);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_hostel'])) {
    $duration = intval($_POST['duration'] ?? 0);
    $start_date = trim($_POST['start_date'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $student_phone = trim($_POST['student_phone'] ?? '');

    if ($duration > 0 && $start_date && $payment_method && $student_phone && $payment_method !== 'mobile_money') {
        $min_fee = floatval($hostel['min_booking_fee'] ?? 0);
        if ($min_fee <= 0) {
            $booking_error = 'Hostel booking fee not configured.';
            notifyAdmin($booking_error, $_SESSION['user_id'] ?? 0, $hostel_id, $_SERVER['REMOTE_ADDR'], json_encode($_POST ?? []));
        } else {
            // Validate start_date
            $checkin_date = DateTime::createFromFormat('Y-m-d', $start_date);
            if (!$checkin_date || $checkin_date < new DateTime()) {
                $booking_error = 'Check-in date must be today or later.';
            } else {
                $checkout_date = clone $checkin_date;
                $checkout_date->add(new DateInterval("P{$duration}D"));
                $checkout_str = $checkout_date->format('Y-m-d');
                $amount = $min_fee * $duration;
                $user_id = $_SESSION['user_id'];

                // Check date availability - find available room
                $avail_query = "SELECT r.id as room_id, r.room_number 
                               FROM rooms r 
                               WHERE r.hostel_id = ? AND r.status = 'available'
                               AND NOT EXISTS (
                                   SELECT 1 FROM bookings b 
                                   WHERE b.room_id = r.id 
                                   AND b.status = 'confirmed'
                                   AND (
                                       (b.checkin_date <= ? AND b.checkout_date > ?) OR
                                       (b.checkin_date < ? AND b.checkout_date >= ?)
                                   )
                               )
                               LIMIT 1";
                $avail_stmt = $conn->prepare($avail_query);
                $avail_stmt->bind_param("issss", $hostel_id, $start_date, $start_date, $checkout_str, $start_date);
                $avail_stmt->execute();
                $avail_result = $avail_stmt->get_result();
                
                if ($avail_row = $avail_result->fetch_assoc()) {
                    $room_id = $avail_row['room_id'];
                    $room_number = $avail_row['room_number'];
                    
                    // Create booking with dates and room
                    $stmt = $conn->prepare("INSERT INTO bookings (user_id, hostel_id, room_id, checkin_date, checkout_date, duration, payment_method, amount, student_phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->bind_param("iiisssisds", $user_id, $hostel_id, $room_id, $start_date, $checkout_str, $duration, $payment_method, $amount, $student_phone);

                    if ($stmt->execute()) {
                        $stmt->close();
                        $avail_stmt->close();
                        
                        $booking_id = $conn->insert_id;
                        
                        // Update room status with booking_id
                        $update_room = $conn->prepare("UPDATE rooms SET status = 'booked', booking_id = ? WHERE id = ?");
                        $update_room->bind_param("ii", $booking_id, $room_id);
                        $update_room->execute();
                        $update_room->close();
                        $booking_success = "Room {$room_number} booked successfully! Check-in: {$start_date}, Check-out: {$checkout_str} (Pending payment confirmation)";
                        $receipt_data = [
                            'id' => $booking_id,
                            'hostel_name' => $hostel['name'],
                            'room_number' => $room_number,
                            'checkin_date' => $start_date,
                            'checkout_date' => $checkout_str,
                            'amount' => $amount,
                            'status' => 'pending'
                        ];
                        $message = "New booking #$booking_id for {$hostel['name']} Room {$room_number}. Check-in: {$start_date}, Check-out: {$checkout_str}, Duration: $duration days, Amount: UGX " . number_format($amount, 0) . ", Phone: $student_phone";
                        $whatsapp_url = 'https://wa.me/' . $hostel['contact'] . '?text=' . urlencode($message);
                    } else {
                        $db_error = $conn->error;
                        $booking_error = 'Failed to create booking. Please try again.';
                        notifyAdmin($booking_error . ' DB Error: ' . $db_error, $user_id, $hostel_id, $_SERVER['REMOTE_ADDR'], json_encode($_POST ?? []));
                        $stmt->close();
                        $avail_stmt->close();
                    }
                } else {
                    $booking_error = 'No rooms available for selected dates.';
                    $avail_stmt->close();
                    notifyAdmin($booking_error, $user_id, $hostel_id, $_SERVER['REMOTE_ADDR'], json_encode($_POST ?? []));
                }
            }
        }
    } else if ($payment_method === 'mobile_money') {
        $booking_error = 'Mobile Money payments handled via Flutterwave form below.';
    } else {
        $booking_error = 'Please complete all required fields including check-in date.';
        notifyAdmin($booking_error, $_SESSION['user_id'] ?? 0, $hostel_id, $_SERVER['REMOTE_ADDR'], json_encode($_POST ?? []));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>HOSTEL FINDER - Hostel Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="cinematic.css" />
    <link rel="stylesheet" href="styles.css" />
    <!-- PayPal SDK (Replace YOUR_CLIENT_ID with your actual Sandbox/Live Client ID) -->
    <script src="https://www.paypal.com/sdk/js?client-id=test&currency=USD"></script>
    <style>
        /* Image Gallery Slider Styles */
        .image-gallery {
            margin-bottom: 2rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .slider-container {
            position: relative;
            max-width: 100%;
            margin: auto;
            overflow: hidden;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .slider-wrapper {
            display: flex;
            transition: transform 0.5s ease-in-out;
            height: 400px;
        }
        .slider-image {
            min-width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 20px;
        }
        .slider-prev, .slider-next {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(46, 139, 87, 0.8);
            color: white;
            border: none;
            padding: 16px 20px;
            font-size: 24px;
            cursor: pointer;
            border-radius: 50%;
            transition: all 0.3s ease;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .slider-prev:hover, .slider-next:hover {
            background: rgba(46, 139, 87, 1);
            transform: translateY(-50%) scale(1.1);
        }
        .slider-prev {
            left: 15px;
        }
        .slider-next {
            right: 15px;
        }
        .slider-dots {
            text-align: center;
            padding: 20px 0;
        }
        .dot {
            height: 15px;
            width: 15px;
            margin: 0 8px;
            background-color: rgba(46, 139, 87, 0.3);
            border-radius: 50%;
            display: inline-block;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }
        .dot.active {
            background-color: #2e8b57;
            transform: scale(1.2);
        }
        .image-count {
            text-align: center;
            color: #2e8b57;
            font-weight: 600;
            font-size: 1.1rem;
            margin-top: 10px;
        }
        @media (max-width: 768px) {
            .slider-image, .slider-wrapper {
                height: 250px;
            }
            .slider-prev, .slider-next {
                width: 50px;
                height: 50px;
                font-size: 20px;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <h1 class="logo">HOSTEL FINDER</h1>
    <nav class="nav">
        <ul>
            <li><a href="index.php">Hostels</a></li>
            <li><a href="contact.php">Contact</a></li>
        </ul>
    </nav>
        </div>
    </header>

    <main class="container main-content">
        <section class="hostel-details animate-fadeInUp">
            <!-- Image Gallery Slider -->
            <div class="image-gallery animate-slideInLeft">
                <div class="slider-container">
                    <div class="slider-wrapper">
                        <?php
                        $gallery_images = [];
                        if (!empty($hostel['image'])) {
                            $gallery_images[] = $hostel['image'];
                        }
                        if (!empty($hostel['images'])) {
                            $additional_images = json_decode($hostel['images'], true);
                            if (is_array($additional_images)) {
                                $gallery_images = array_merge($gallery_images, $additional_images);
                            }
                        }
                        $total_images = count($gallery_images);
                        ?>
                        <?php foreach ($gallery_images as $index => $img_src): ?>
                            <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($hostel['name']); ?> - Image <?php echo $index + 1; ?>" class="slider-image" data-index="<?php echo $index; ?>">
                        <?php endforeach; ?>
                    </div>
                    <button class="slider-prev" onclick="changeSlide(-1)">❮</button>
                    <button class="slider-next" onclick="changeSlide(1)">❯</button>
                    <div class="slider-dots">
                        <?php for ($i = 0; $i < $total_images; $i++): ?>
                            <span class="dot" onclick="currentSlide(<?php echo $i; ?>)" data-index="<?php echo $i; ?>"></span>
                        <?php endfor; ?>
                    </div>
                    <div class="image-count"><?php echo $total_images; ?> images</div>
                </div>
            </div>

            <div class="hostel-info-card animate-fadeInUp">
                <h2><?php echo htmlspecialchars($hostel['name']); ?></h2>
                <p>Location: <?php echo htmlspecialchars($hostel['location']); ?></p>
                <p>Rating: <?php echo htmlspecialchars($hostel['rating']); ?></p>
                <p>Price: UGX <?php echo htmlspecialchars($hostel['price']); ?></p>
                <p>Min Booking Fee: UGX <?php echo isset($hostel['min_booking_fee']) ? number_format($hostel['min_booking_fee'], 0) : '0'; ?> (valid for <?php echo isset($hostel['booking_fee_valid_days']) ? $hostel['booking_fee_valid_days'] : '30'; ?> days)</p>
<p>Total Rooms: <?php echo htmlspecialchars($hostel['rooms']); ?></p>
<p>Available Rooms: <span id="roomCount"><?php echo $room_count; ?></span> 
<?php if ($proposed_start && $proposed_duration > 0): ?>
(for <?php echo $proposed_duration; ?> days from <?php echo htmlspecialchars($proposed_start); ?>)
<?php endif; ?>
</p>
<?php if ($room_count == 0): ?>
<div class="error-message" style="margin-top: 10px;">No rooms available for selected dates.</div>
<?php endif; ?>
                <p>Bathrooms: <?php echo htmlspecialchars($hostel['bathrooms']); ?></p>
                <p>Distance: <?php echo htmlspecialchars($hostel['distance']); ?></p>
                <p>WiFi: <?php echo htmlspecialchars($hostel['wifi']); ?></p>
                <p>Water: <?php echo htmlspecialchars($hostel['water']); ?></p>
                <p>Electricity: <?php echo htmlspecialchars($hostel['electricity']); ?></p>
                <p>Security: <?php echo htmlspecialchars($hostel['security']); ?></p>
                <p>Contact: <?php echo htmlspecialchars($hostel['contact']); ?></p>
            </div>
            <div class="hostel-description">
                <h3>Description</h3>
                <p>
                    <?php echo nl2br(htmlspecialchars($hostel['description'])); ?>
                </p>
            </div>

            <?php if (!empty($booking_success)): ?>
                <div class="success-message"><?php echo htmlspecialchars($booking_success); ?></div>
                
                <?php if ($receipt_data): ?>
                    <div class="receipt <?php echo $receipt_data['status']; ?>">
                        <h3><?php echo ucfirst($receipt_data['status']); ?> Receipt <?php echo $receipt_data['status'] === 'confirmed' ? '✓' : ''; ?></h3>
                        <p><strong>Booking ID:</strong> #<?php echo $receipt_data['id']; ?></p>
                        <p><strong>Hostel:</strong> <?php echo htmlspecialchars($receipt_data['hostel_name']); ?></p>
                        <?php if (isset($receipt_data['room_number'])): ?>
                        <p><strong>Room:</strong> <?php echo htmlspecialchars($receipt_data['room_number']); ?></p>
                        <p><strong>Check-in:</strong> <?php echo htmlspecialchars($receipt_data['checkin_date']); ?></p>
                        <p><strong>Check-out:</strong> <?php echo htmlspecialchars($receipt_data['checkout_date']); ?></p>
                        <?php endif; ?>
                        <p><strong>Amount:</strong> UGX <?php echo number_format($receipt_data['amount'], 0); ?></p>
                        <p><strong>Status:</strong> <?php echo ucfirst($receipt_data['status']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                        <button onclick="printReceipt(<?php echo $receipt_data['id']; ?>)">Print Receipt</button>
                        <button onclick="downloadReceipt(<?php echo $receipt_data['id']; ?>)">Download PDF</button>
                    </div>
                <?php endif; ?>

                <?php if ($whatsapp_url): ?>
                    <p><a href="<?php echo htmlspecialchars($whatsapp_url); ?>" target="_blank" class="whatsapp-btn" style="display: inline-block; background: #25D366; color: white; padding: 12px 24px; text-decoration: none; border-radius: 25px; margin: 10px 0;">📱 Notify Landlord via WhatsApp</a></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($booking_error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($booking_error); ?></div>
            <?php endif; ?>

            <div class="booking-form">
                <h3>Book This Hostel</h3>
                <form id="bookingForm" method="post" onsubmit="handleBooking(event); return false;">
                    <label for="duration">Duration (days):</label>
                    <input type="number" id="duration" name="duration" min="1" required />

                    <label for="start_date">Check-in Date:</label>
                    <input type="date" id="start_date" name="start_date" min="<?php echo date('Y-m-d'); ?>" required />

                    <label for="payment_method">Payment Method:</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Select</option>
                        <option value="M-Pesa">M-Pesa</option>
                        <option value="paypal">PayPal</option>
                        <option value="mobile_money">Mobile Money (PesaPal)</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank">Bank Transfer</option>
                    </select>

                    <label for="student_phone">Student Phone:</label>
                    <input type="tel" id="student_phone" name="student_phone"
                           placeholder="+256712345678" 
                           pattern="\+2567[0-9]{8}" 
                           title="Enter Uganda MTN number starting with +2567 followed by 8 digits"
                           required />
                    <small style="color: #666; font-size: 0.9em; display: block; margin-top: 5px;">
                        Enter Uganda MTN number (e.g. +256712345678) for MOMO OTP
                    </small>

                    <button type="submit" id="standardBookBtn" name="book_hostel">Book Now</button>
                    <input type="hidden" name="book_hostel" value="1">
                    <div id="paypal-button-container" style="display: none; margin-top: 20px;"></div>
                    <div id="pesapal-iframe-container" style="display: none; margin-top: 20px;"></div>
                </form>
            </div>

            <a href="index.php" class="back-btn" style="display: inline-block; margin: 20px 0; padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px;">← Back to Hostels</a>
        </section>
    </main>

    <footer class="footer">
        <p>&copy; 2026 HOSTEL FINDER. All rights reserved.</p>
    </footer>

    <script>
        let slideIndex = 0;
        const totalSlides = <?php echo $total_images; ?>;

        function showSlide(index) {
            const wrapper = document.querySelector('.slider-wrapper');
            const dots = document.querySelectorAll('.dot');
            
            slideIndex = (index + totalSlides) % totalSlides;
            wrapper.style.transform = `translateX(-${slideIndex * 100}%)`;
            
            dots.forEach(dot => dot.classList.remove('active'));
            if (dots[slideIndex]) {
                dots[slideIndex].classList.add('active');
            }
        }

        function changeSlide(direction) {
            showSlide(slideIndex + direction);
        }

        function currentSlide(index) {
            showSlide(index);
        }

        // Auto-slide every 5 seconds
        setInterval(() => {
            changeSlide(1);
        }, 5000);

        // Initialize first slide
        document.addEventListener('DOMContentLoaded', () => {
            showSlide(0);
        });

        // PayPal Integration Logic
        const paymentMethodSelect = document.getElementById('payment_method');
        const standardBookBtn = document.getElementById('standardBookBtn');
        const paypalContainer = document.getElementById('paypal-button-container');
        const minBookingFee = <?php echo floatval($hostel['min_booking_fee'] ?? 0); ?>;

        paymentMethodSelect.addEventListener('change', function() {
            if (this.value === 'paypal') {
                standardBookBtn.style.display = 'none';
                paypalContainer.style.display = 'block';
                initPayPalButton();
            } else {
                standardBookBtn.style.display = 'block';
                paypalContainer.style.display = 'none';
            }
        });

        function initPayPalButton() {
            // Avoid multiple renders
            paypalContainer.innerHTML = '';
            
            paypal.Buttons({
                createOrder: function(data, actions) {
                    const duration = document.getElementById('duration').value || 1;
                    const totalUGX = minBookingFee * duration;
                    // Note: PayPal doesn't support UGX, converting to USD (Placeholder rate: 1 USD = 3800 UGX)
                    const totalUSD = (totalUGX / 3800).toFixed(2);

                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: totalUSD,
                                currency_code: 'USD'
                            },
                            description: 'Hostel Booking Fee: <?php echo addslashes($hostel['name']); ?>'
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        alert('Transaction completed by ' + details.payer.name.given_name);
                        // After successful payment, submit the form to record the booking
                        document.getElementById('bookingForm').submit();
                    });
                },
                onError: function(err) {
                    console.error('PayPal Error:', err);
                }
            }).render('#paypal-button-container');
        }

        // Flutterwave Mobile Money Booking Handler
        function handleBooking(event) {
            event.preventDefault();
            
            const duration = document.getElementById('duration').value;
            const startDate = document.getElementById('start_date').value;
            const paymentMethod = document.getElementById('payment_method').value;
            const studentPhone = document.getElementById('student_phone').value.trim();
            const hostelId = <?php echo $hostel_id; ?>;
            
            if (!duration || !startDate || !paymentMethod || !studentPhone) {
                alert('Please fill all fields including check-in date');
                return;
            }
            
            // Validate Uganda MTN phone for mobile_money
            if (paymentMethod === 'mobile_money') {
                const ugandaMtnPattern = /^\+2567[0-9]{8}$/;
                if (!ugandaMtnPattern.test(studentPhone)) {
                    alert('Invalid phone number! Use Uganda MTN format: +2567xxxxxxxx (8 digits)');
                    document.getElementById('student_phone').focus();
                    return;
                }
                console.log('Valid MOMO phone:', studentPhone);
            }
            
            if (paymentMethod === 'mobile_money') {
                // Mobile Money - PesaPal
                handleMobileMoneyPayment(duration, startDate, studentPhone, hostelId);
            } else {
                // Other methods - direct submit
                document.getElementById('bookingForm').submit();
            }
        }

        function handleMobileMoneyPayment(duration, startDate, studentPhone, hostelId) {
            // Show loading
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Processing...';
            submitBtn.disabled = true;
            
            fetch(`initiate-payment.php?id=${hostelId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `duration=${duration}&start_date=${encodeURIComponent(startDate)}&student_phone=${encodeURIComponent(studentPhone)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Step 3: Display PesaPal Iframe
                    const iframeContainer = document.getElementById('pesapal-iframe-container');
                    iframeContainer.innerHTML = `
                        <div style="background: white; padding: 15px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.1);">
                            <h4 style="color: #2e8b57; text-align: center; margin-bottom: 15px;">Complete Your Payment</h4>
                            <iframe src="${data.redirect_url}" width="100%" height="600px" style="border:none; border-radius: 8px;"></iframe>
                            <p style="text-align: center; margin-top: 10px; font-size: 0.85em; color: #666;">
                                After payment, you will be redirected back automatically.
                            </p>
                        </div>`;
                    iframeContainer.style.display = 'block';
                    document.getElementById('bookingForm').style.display = 'none';
                } else {
                    alert('Error: ' + data.error);
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }); 
        }

        function printReceipt(bookingId) {
            const printContent = document.querySelector('.receipt');
            const originalContent = printContent.innerHTML;
            printContent.classList.add('print-friendly');
            window.print();
            printContent.classList.remove('print-friendly');
        }

        function downloadReceipt(bookingId) {
            // Download PDF receipt using server-side Dompdf
            fetch(`pdf-receipt.php?booking_id=${bookingId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to generate PDF');
                    }
                    return response.blob();
                })
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `receipt_booking_${bookingId}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                })
                .catch(error => {
                    console.error('Download error:', error);
                    alert('Failed to download receipt. Please try again.');
                });
        }

        // Add print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body * { visibility: hidden; }
                .receipt, .receipt * { visibility: visible; }
                .receipt { 
                    position: absolute; 
                    left: 0; 
                    top: 0; 
                    width: 100%; 
                    background: white; 
                    padding: 20px; 
                    font-size: 14px;
                }
                .receipt button { display: none; }
            }
            .receipt { 
                background: white; 
                border: 2px solid #2e8b57; 
                padding: 20px; 
                margin: 20px 0; 
                border-radius: 10px; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .success-message { 
                background: #d4edda; 
                color: #155724; 
                padding: 15px; 
                border-radius: 8px; 
                margin: 20px 0; 
                border: 1px solid #c3e6cb; 
            }
            .error-message { 
                background: #f8d7da; 
                color: #721c24; 
                padding: 15px; 
                border-radius: 8px; 
                margin: 20px 0; 
                border: 1px solid #f5c6cb; 
            }
            .whatsapp-btn:hover { opacity: 0.9; }
            .back-btn:hover { background: #5a6268; }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
