<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';

    if (!$name || !$email || !$message) {
        die("Please fill all required fields.");
    }

    // Prepare and bind
    $stmt = $conn->prepare("INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $message);

    if ($stmt->execute()) {
        header("Location: https://wa.me/256743545852");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>HOSTEL FINDER - Contact</title>
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        .contact-container {
            margin-top: 20px;
            background-color: white;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        .contact-container label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .contact-container input,
        .contact-container textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }
        .contact-container textarea {
            height: 100px;
            resize: vertical;
        }
        .contact-container button {
            background-color: #2e8b57;
            color: white;
            border: none;
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .contact-container button:hover {
            background-color: #1e5e3a;
        }
        .social-media {
            text-align: center;
            margin-top: 30px;
        }
        .social-media h3 {
            margin-bottom: 15px;
        }
        .social-media a {
            display: inline-block;
            margin: 0 10px;
            color: #2e8b57;
            text-decoration: none;
            font-weight: 600;
        }
        .social-media a:hover {
            color: #1e5e3a;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
             <h1 class="logo"><img src="images/logo.png" alt="Hostel Finder Logo" /> HOSTEL FINDER</h1>
            <nav class="nav">
                <ul>
                    <li><a href="index.php">Hostels</a></li>
                    <li><a href="contact.php" class="active">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container main-content">
        <h2>Contact Us</h2>
        <div class="contact-container">
            <form id="contactForm" action="contact.php" method="post">
                <label for="contactName">Name</label>
                <input type="text" id="contactName" name="name" required />

                <label for="contactEmail">Email</label>
                <input type="email" id="contactEmail" name="email" required />

                <label for="contactMessage">Message</label>
                <textarea id="contactMessage" name="message" required></textarea>

                <button type="submit">Send Message</button>
            </form>
        </div>

        <div class="social-media">
            <h3>Connect with Developers</h3>
            <a href="https://wa.me/256743545852" target="_blank"><i class="fab fa-whatsapp"></i> WhatsApp Developer 1</a>
            <a href="https://wa.me/256s765600313" target="_blank"><i class="fab fa-whatsapp"></i> WhatsApp Developer 2</a>
            <a href="https://facebook.com/yourpage" target="_blank"><i class="fab fa-facebook"></i> Facebook</a>
            <a href="https://github.com/N-Epaphras" target="_blank"><i class="fab fa-github"></i> GitHub</a>
        </div>
    </main>

    <footer class="footer">
        <p>&copy; 2026 HOSTEL FINDER. All rights reserved.</p>
    </footer>
</body>
</html>
