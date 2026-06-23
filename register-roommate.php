<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}
include 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $area_of_origin = $_POST['area_of_origin'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if ($name && $area_of_origin && $phone) {
        // Get user id from users table based on session user_id (username or email)
        $userId = null;
        $userCheckStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $userCheckStmt->bind_param("i", $_SESSION['user_id']);
        $userCheckStmt->execute();
        $userCheckStmt->store_result();
        if ($userCheckStmt->num_rows > 0) {
            $userId = $_SESSION['user_id'];
        }
        $userCheckStmt->close();

        $stmt = $conn->prepare("INSERT INTO roommates (user_id, name, area_of_origin, phone) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $name, $area_of_origin, $phone);

        if ($stmt->execute()) {
            $success = "Registered successfully as looking for roommate.";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "All fields are required.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>HOSTEL FINDER - Register for Roommate</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <header class="header">
        <div class="container">
            <h1 class="logo"><img src="images/logo.png" alt="Hostel Finder Logo" /> HOSTEL FINDER</h1>
            <nav class="nav">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container main-content">
        <h2>Register to Find Roommate</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form action="register-roommate.php" method="post" class="auth-form">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required />

            <label for="area_of_origin">Area of Origin</label>
            <input type="text" id="area_of_origin" name="area_of_origin" required />

            <label for="phone">Phone Number (Uganda)</label>
            <input type="text" id="phone" name="phone" placeholder="e.g. +256700000000" required />

            <button type="submit">Register</button>
        </form>
    </main>

    <footer class="footer">
        <p>&copy; 2026 HOSTEL FINDER. All rights reserved.</p>
    </footer>
</body>
</html>
