<?php
// signup.php - Handle user registration

include 'db.php';

// Handle query param messages from auth.php
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>HOSTEL FINDER - Signup</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <header class="header">
        <div class="container">
            <h1 class="logo"><img src="images/logo.png" alt="Hostel Finder Logo" /> HOSTEL FINDER</h1>
            <nav class="nav">
                <ul>
                    <li><a href="index.php">Hostels</a></li>
                    <li><a href="hostel-details.php">Hostel Details</a></li>
                    <li><a href="comparison.php">Compare</a></li>
                    <li><a href="map-view.php">Map View</a></li>
                    <li><a href="forum.php">Forum</a></li>
                    <li><a href="guidelines.php">Guidelines</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="register-hostel.php">Register New Hostel</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container main-content">
        <h2>Student Signup</h2>
        <div class="auth-container">
            <?php if ($error): ?>
                <div class="error"><?php echo match($error) {
                    'empty' => 'Please fill all required fields.',
                    'exists' => 'Username or email already exists.',
                    'failed' => 'Registration failed. Try again.',
                    default => 'An error occurred.'
                }; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success">Account created! Please <a href="login.php">login</a>.</div>
            <?php endif; ?>
            <form id="signupForm" class="auth-form" action="auth.php?role=student&action=register" method="post">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="role" value="student">
                <label for="signupUsername">Username</label>
                <input type="text" id="signupUsername" name="username" required />
                <label for="signupEmail">Email</label>
                <input type="email" id="signupEmail" name="email" required />
                <label for="signupPassword">Password</label>
                <input type="password" id="signupPassword" name="password" required minlength="6" />
                <label for="signupWhatsapp">WhatsApp (optional)</label>
                <input type="text" id="signupWhatsapp" name="whatsapp" placeholder="e.g. +1234567890" />
                <button type="submit">Create Account</button>
            </form>
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </main>

    <footer class="footer">
        <p>&copy; 2024 HOSTEL FINDER. All rights reserved.</p>
    </footer>
</body>
</html>
