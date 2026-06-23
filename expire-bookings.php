<?php
#!/usr/bin/env php
// expire-bookings.php - Automatically free expired room bookings
// Usage: php expire-bookings.php [--dry-run]
// Cron: 01 00 * * * cd /c/xampp/htdocs/hostelfinder && php expire-bookings.php >> logs/expire.log 2>&1

$dry_run = in_array('--dry-run', $argv);
$log_file = __DIR__ . '/logs/expire.log';

if (!is_dir(dirname($log_file))) {
    mkdir(dirname($log_file), 0755, true);
}

function log_msg($msg, $level = 'INFO') {
    global $dry_run, $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $msg" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    echo $log_entry;
}

include 'db.php';

log_msg("=== Booking Expiration Check Started (Dry-run: " . ($dry_run ? 'YES' : 'NO') . ") ===");

$expired_count = 0;
$freed_rooms = 0;

// Find expired confirmed bookings
$query = "
    SELECT b.id, b.room_id, b.hostel_id, b.user_id, b.checkout_date, r.status as room_status
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    WHERE b.checkout_date < CURDATE() 
    AND b.status = 'confirmed'
";
$result = $conn->query($query);

if (!$result) {
    log_msg("Query failed: " . $conn->error, 'ERROR');
    exit(1);
}

$expired_bookings = [];
while ($row = $result->fetch_assoc()) {
    $expired_bookings[] = $row;
    $expired_count++;
}

log_msg("Found $expired_count expired bookings");

foreach ($expired_bookings as $booking) {
    log_msg("Processing booking #{$booking['id']} (room {$booking['room_id']}, checkout " . $booking['checkout_date'] . ")");
    
    if (!$dry_run) {
        // Free room
        $update_room = $conn->prepare("UPDATE rooms SET status = 'available', booking_id = NULL WHERE id = ?");
        $update_room->bind_param("i", $booking['room_id']);
        if ($update_room->execute()) {
            $freed_rooms += $update_room->affected_rows;
            log_msg("Room {$booking['room_id']} freed", 'SUCCESS');
        }
        $update_room->close();
        
        // Mark booking expired
        $update_booking = $conn->prepare("UPDATE bookings SET status = 'expired' WHERE id = ?");
        $update_booking->bind_param("i", $booking['id']);
        $update_booking->execute();
        $update_booking->close();
    } else {
        log_msg("DRY-RUN: Would free room {$booking['room_id']}", 'DRYRUN');
    }
}

log_msg("Summary: $expired_count expired bookings processed, $freed_rooms rooms freed");
log_msg("=== Booking Expiration Check Completed ===");

$conn->close();
?>
