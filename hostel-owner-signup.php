<?php
header('Location: auth.php?role=landlord&action=register');
exit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>HOSTEL FINDER - Hostel Owner Signup</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <header class="header">
        <div class="container">
             <h1 class="logo"><img src="images/logo.png" alt="Hostel Finder Logo" /> HOSTEL FINDER</h1>
            <nav class="nav">
                <ul>
                    <li><a href="index.php">Hostels</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="hostel-owner-login.php">Register new hostel</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container main-content">
        <h2>Hostel Owner Signup</h2>
        <div class="auth-container">
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            <form id="signupForm" class="auth-form" action="hostel-owner-signup.php" method="post">
                <label for="signupUsername">Username</label>
                <input type="text" id="signupUsername" name="username" required />
                <label for="signupEmail">Email</label>
                <input type="email" id="signupEmail" name="email" required />
                <label for="signupWhatsapp">WhatsApp Number (Uganda)</label>
                <input type="tel" id="signupWhatsapp" name="whatsapp" placeholder="+256700000000" required />
                <label for="signupPassword">Password</label>
                <input type="password" id="signupPassword" name="password" required />
                <button type="submit">Signup</button>
            </form>
        </div>
    </main>

    <footer class="footer">
        <p>&copy; 2026 HOSTEL FINDER. All rights reserved.</p>
    </footer>
</body>
</html>
