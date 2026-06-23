<?php
// hostel-owner-login.php - Handle hostel owner login

session_start();
include 'db.php';

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
    <title>HOSTEL FINDER - Hostel Owner Login/Signup</title>
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
                    <li><a href="hostel-owner-login.php" class="active">Register new hostel</a></li>
                </ul>
            </nav>
        </div>
    </header>

        <main class="container main-content">
            <h2>Hostel Owner Login / Signup</h2>
            <div class="auth-container">
                <?php if ($error): ?>
                    <div class="error"><?php echo match($error) {
                        'empty' => 'Please fill all required fields.',
                        'invalid' => 'Invalid username/email or password.',
                        'notfound' => 'User not found.',
                        'pending' => 'Landlord account pending approval.',
                        default => 'An error occurred.'
                    }; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success"><?php echo match($success) {
                        'pending' => 'Landlord account created. Awaiting admin approval.',
                        default => 'Success!'
                    }; ?></div>
                <?php endif; ?>

                <form id="loginForm" class="auth-form" action="auth.php?role=landlord&action=login" method="post">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="role" value="landlord">
                    <h3>Login</h3>
                    <label for="loginUsername">Username or Email</label>
                    <input type="text" id="loginUsername" name="username" required />
                    <label for="loginPassword">Password</label>
                    <input type="password" id="loginPassword" name="password" required />
                    <button type="submit">Login</button>
                </form>

                <form id="signupForm" class="auth-form" action="auth.php?role=landlord&action=register" method="post" style="display:none;">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="role" value="landlord">
                    <h3>Signup</h3>
                    <label for="signupUsername">Username</label>
                    <input type="text" id="signupUsername" name="username" required />
                    <label for="signupEmail">Email</label>
                    <input type="email" id="signupEmail" name="email" required />
                    <label for="signupPassword">Password</label>
                    <input type="password" id="signupPassword" name="password" required minlength="6" />
                    <button type="submit">Create Account</button>
                </form>

                <div class="form-toggle" id="formToggle">
                    Don't have an account? <span id="toggleLink">Sign up here</span>
                </div>
            </div>
        </main>

    <footer class="footer">
        <p>&copy; 2026 HOSTEL FINDER. All rights reserved.</p>
    </footer>

    <script>
        const loginForm = document.getElementById('loginForm');
        const signupForm = document.getElementById('signupForm');
        const formToggle = document.getElementById('formToggle');
        const toggleLink = document.getElementById('toggleLink');

        toggleLink.addEventListener('click', () => {
            if (loginForm.style.display === 'none') {
                loginForm.style.display = 'flex';
                signupForm.style.display = 'none';
                toggleLink.textContent = 'Sign up here';
                formToggle.innerHTML = 'Don\'t have an account? <span id="toggleLink">Sign up here</span>';
            } else {
                loginForm.style.display = 'none';
                signupForm.style.display = 'flex';
                toggleLink.textContent = 'Login here';
                formToggle.innerHTML = 'Already have an account? <span id="toggleLink">Login here</span>';
            }
        });
    </script>
</body>
</html>
