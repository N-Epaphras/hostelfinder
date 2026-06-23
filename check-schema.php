<?php
// Temporary DB schema checker
include 'db.php';

$tables = ['bookings', 'rooms', 'hostels', 'admin_notifications'];

echo "=== Hostel Finder DB Schema Check ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    echo "=== $table ===\n";
    $result = $conn->query("DESCRIBE $table");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Table '$table' does not exist!\n";
    }
    echo "\n";
}

echo "=== Sample Data Counts ===\n";
$counts = [
    'bookings' => "SELECT COUNT(*) as cnt FROM bookings",
    'hostels' => "SELECT COUNT(*) as cnt FROM hostels WHERE min_booking_fee > 0",
    'rooms' => "SELECT COUNT(*) as cnt FROM rooms WHERE status='available'",
    'admin_notifications' => "SELECT COUNT(*) as cnt FROM admin_notifications"
];

foreach ($counts as $name => $query) {
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        echo "$name: " . $row['cnt'] . "\n";
    }
}

$conn->close();
echo "\nRun: php check-schema.php\n";
?>
