<?php
// login.php - DEPRECATED - Redirect to unified auth
header('Location: auth.php');
exit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>HOSTEL FINDER - Login/Signup</title>
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
                </ul>
            </nav>
        </div>
    </header>

    <main class="container main-content">
        <h2>Login / Signup</h2>
        <div class="auth-container">
            <form id="loginForm" class="auth-form" action="login.php" method="post">
                <h3>Login</h3>
                <?php if ($error): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                <label for="loginUsername">Username</label>
                <input type="text" id="loginUsername" name="username" required />
                <label for="loginPassword">Password</label>
                <input type="password" id="loginPassword" name="password" required />
                <button type="submit">Login</button>
            </form>

            <form id="signupForm" class="auth-form" action="signup.php" method="post" style="display:none;">
                <h3>Signup</h3>
                <label for="signupUsername">Username</label>
                <input type="text" id="signupUsername" name="username" required />
                <label for="signupEmail">Email</label>
                <input type="email" id="signupEmail" name="email" required />
                <label for="signupPassword">Password</label>
                <input type="password" id="signupPassword" name="password" required />
                <button type="submit">Signup</button>
            </form>

            <div class="form-toggle" id="formToggle">
                Don't have an account? <span id="toggleLink">Sign up here</span>
            </div>
        </div>
    </main>

    <footer class="footer">
        <p>&copy; 2024 HOSTEL FINDER. All rights reserved.</p>
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
