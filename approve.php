<?php
session_start();
include 'db.php';

// ================= AUTH =================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: auth.php?unauthorized=1');
    exit();
}

$message = "";

// ================= ACTION HANDLER =================
if (isset($_GET['type'], $_GET['id'], $_GET['action'])) {

    $type = $_GET['type'];
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    $table = ($type === 'hostel') ? "hostels" : "hostel_owners";

    if ($action === 'approve' || $action === 'reject') {

        $status = ($action === 'approve') ? 'approved' : 'rejected';

        $stmt = $conn->prepare("UPDATE $table SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            // ACTIVITY LOG
            $log = $conn->prepare("INSERT INTO activity_logs (action) VALUES (?)");
            $logText = "$type ID $id $status";
            $log->bind_param("s", $logText);
            $log->execute();
            $log->close();

            // Get email and name for notification
            $email = '';
            $name = '';
            if ($type === 'owner') {
                $nameStmt = $conn->prepare("SELECT username, email FROM hostel_owners WHERE id=?");
                $nameStmt->bind_param("i", $id);
                $nameStmt->execute();
                $nameResult = $nameStmt->get_result();
                if ($nameRow = $nameResult->fetch_assoc()) {
                    $name = $nameRow['username'];
                    $email = $nameRow['email'];
                }
                $nameStmt->close();
            } else { // hostel - notify owner
                $ownerStmt = $conn->prepare("SELECT ho.username, ho.email FROM hostel_owners ho JOIN hostels h ON ho.id = h.owner_id WHERE h.id=?");
                $ownerStmt->bind_param("i", $id);
                $ownerStmt->execute();
                $ownerResult = $ownerStmt->get_result();
                if ($ownerRow = $ownerResult->fetch_assoc()) {
                    $name = $ownerRow['username'];
                    $email = $ownerRow['email'];
                }
                $ownerStmt->close();
            }

            // Insert notification if approved and email found
            if ($action === 'approve' && $email) {
                $notifType = ($type === 'owner') ? 'approval' : 'hostel_approval';
                $notifMsg = "Your " . (($type === 'owner') ? 'landlord account' : 'hostel') . " '$name' has been approved by admin!";
                $notifStmt = $conn->prepare("INSERT INTO notifications (target_email, type, message) VALUES (?, ?, ?)");
                $notifStmt->bind_param("sss", $email, $notifType, $notifMsg);
                $notifStmt->execute();
                $notifStmt->close();

                // Set session popup for landlord approval
                if ($type === 'owner') {
                    $_SESSION['approval_popup'] = "Your account '$name' has been approved by admin!";
                }
            }

            $message = ucfirst($type) . " " . $name . " $status successfully.";
        }

        $stmt->close();
    }
}

// ================= FILTER =================
$statusFilter = $_GET['status'] ?? "";

// ================= STATS =================
$stats = [
    'pendingHostels' => $conn->query("SELECT COUNT(*) FROM hostels WHERE status='pending'")->fetch_row()[0],
    'pendingOwners'  => $conn->query("SELECT COUNT(*) FROM hostel_owners WHERE status='pending'")->fetch_row()[0],
    'totalBookings'  => $conn->query("SELECT COUNT(*) FROM bookings")->fetch_row()[0],
    'revenue'        => $conn->query("SELECT SUM(amount) FROM bookings WHERE status='paid'")->fetch_row()[0] ?? 0
];

// ================= DATA =================
$pendingHostels = $conn->query("SELECT id,name FROM hostels WHERE status='pending'");
$pendingOwners  = $conn->query("SELECT id,username FROM hostel_owners WHERE status='pending'");

// BOOKINGS (SAFE FILTER)
if ($statusFilter) {
    $stmt = $conn->prepare("
        SELECT b.*, u.username, h.name AS hostel_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN hostels h ON b.hostel_id = h.id
        WHERE b.status = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->bind_param("s", $statusFilter);
    $stmt->execute();
    $bookings = $stmt->get_result();
} else {
    $bookings = $conn->query("
        SELECT b.*, u.username, h.name AS hostel_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN hostels h ON b.hostel_id = h.id
        ORDER BY b.created_at DESC
    ");
}

// ACTIVITY LOG
$logs = $conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");

// CHART DATA
$chartBookings = $conn->query("
    SELECT DATE(created_at) as day, COUNT(*) as total
    FROM bookings
    GROUP BY day
");

$chartStatus = $conn->query("
    SELECT status, COUNT(*) as total
    FROM bookings
    GROUP BY status
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<style>
.cards { display: flex; gap: 20px; margin: 20px 0; }
.card {
    flex: 1;
    padding: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    font-weight: bold;
    text-align: center;
}
table { 
    width: 100%; 
    border-collapse: collapse; 
    margin-top: 20px; 
}
th, td { 
    border: 1px solid #ddd; 
    padding: 10px; 
    text-align: left;
}
th { 
    background: #eee; 
}
a { 
    text-decoration: none; 
    color: blue; 
}
.success { 
    color: green; 
}
#bookingChart, #statusChart {
    max-height: 300px;
    margin: 20px 0;
}
</style>
</head>
<body>
<!-- Admin Header with Back Button -->
<div class="header bg-primary text-white py-3" style="position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <h4 class="mb-0 fw-bold">🛏️ HostelFinder Admin</h4>
        </div>
        <a href="auth.php?from=admin" class="back-btn btn fw-bold">
            ← Back to Auth
        </a>
    </div>
</div>

<div class="container mt-4">

<?php if ($message): ?>
<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #c3e6cb;">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- ================= STATS ================= -->
<div class="cards">
    <div class="card">Pending Hostels: <?= $stats['pendingHostels'] ?></div>
    <div class="card">Pending Owners: <?= $stats['pendingOwners'] ?></div>
    <div class="card">Bookings: <?= $stats['totalBookings'] ?></div>
    <div class="card">Revenue: UGX <?= number_format($stats['revenue']) ?></div>
</div>

<!-- ================= PENDING HOSTELS ================= -->
<h3>Pending Hostels</h3>
<table>
<tr><th>Name</th><th>Action</th></tr>
<?php while ($h = $pendingHostels->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($h['name']) ?></td>
<td>
<a href="?type=hostel&id=<?= $h['id'] ?>&action=approve">Approve</a> |
<a href="?type=hostel&id=<?= $h['id'] ?>&action=reject">Reject</a>
</td>
</tr>
<?php endwhile; ?>
</table>

<!-- ================= PENDING OWNERS ================= -->
<h3>Pending Owners</h3>
<table>
<tr><th>Name</th><th>Action</th></tr>
<?php while ($o = $pendingOwners->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($o['username']) ?></td>
<td>
<a href="?type=owner&id=<?= $o['id'] ?>&action=approve">Approve</a> |
<a href="?type=owner&id=<?= $o['id'] ?>&action=reject">Reject</a>
</td>
</tr>
<?php endwhile; ?>
</table>

<!-- ================= BOOKINGS ================= -->
<h3>Bookings</h3>

<form method="GET">
<select name="status">
<option value="">All</option>
<option value="paid">Paid</option>
<option value="pending">Pending</option>
</select>
<button type="submit">Filter</button>
</form>

<table>
<tr>
<th>Student</th>
<th>Hostel</th>
<th>Amount</th>
<th>Status</th>
<th>Receipt</th>
</tr>

<?php while ($b = $bookings->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($b['username']) ?></td>
<td><?= htmlspecialchars($b['hostel_name']) ?></td>
<td><?= number_format($b['amount']) ?> UGX</td>
<td><?= htmlspecialchars($b['status']) ?></td>
<td>
<a href="pdf-receipt.php?id=<?= $b['id'] ?>" target="_blank">PDF</a>
</td>
</tr>
<?php endwhile; ?>
</table>

<!-- ================= ACTIVITY LOG ================= -->
<h3>Recent Activity</h3>
<ul>
<?php while ($l = $logs->fetch_assoc()): ?>
<li><?= htmlspecialchars($l['action']) ?> (<?= $l['created_at'] ?>)</li>
<?php endwhile; ?>
</ul>

<!-- ================= CHARTS ================= -->
<canvas id="bookingChart"></canvas>
<canvas id="statusChart"></canvas>

<script>
const bookingLabels = [];
const bookingData = [];

<?php while($c = $chartBookings->fetch_assoc()): ?>
bookingLabels.push("<?= $c['day'] ?>");
bookingData.push(<?= $c['total'] ?>);
<?php endwhile; ?>

new Chart(document.getElementById('bookingChart'), {
    type: 'line',
    data: {
        labels: bookingLabels,
        datasets: [{ label: 'Bookings', data: bookingData }]
    }
});

const statusLabels = [];
const statusData = [];

<?php while($s = $chartStatus->fetch_assoc()): ?>
statusLabels.push("<?= $s['status'] ?>");
statusData.push(<?= $s['total'] ?>);
<?php endwhile; ?>

new Chart(document.getElementById('statusChart'), {
    type: 'pie',
    data: {
        labels: statusLabels,
        datasets: [{ data: statusData }]
    }
});
</script>

</div>
</body>
</html>
