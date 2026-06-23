 <?php
session_start();
include 'db.php';

$error = '';
$success = '';


// Check if hostel owner is logged in
if (!isset($_SESSION['hostel_owner_id'])) {
    header("Location: auth.php?role=landlord");
    exit();
}

// Check if hostel owner is approved
$stmt = $conn->prepare("SELECT status FROM hostel_owners WHERE id = ?");
$stmt->bind_param("i", $_SESSION['hostel_owner_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['status'] != 'approved') {
        $error = "Your account is not approved yet. Please wait for developer approval.";
    }
}
$stmt->close();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $location = $_POST['location'] ?? '';
    $rating = $_POST['rating'] ?? '';
    $price = $_POST['price'] ?? '';
    $rooms = intval($_POST['rooms'] ?? 0);
    $bathrooms = intval($_POST['bathrooms'] ?? 0);
    $wifi = $_POST['wifi'] ?? '';
    $distance = $_POST['distance'] ?? '';
    $water = $_POST['water'] ?? '';
    $electricity = $_POST['electricity'] ?? '';
    $security = $_POST['security'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $description = $_POST['description'] ?? '';
    $min_booking_fee = floatval($_POST['min_booking_fee'] ?? 0);
    $booking_fee_valid_days = intval($_POST['booking_fee_valid_days'] ?? 30);
    $image = '';
    $images = '[]';
    $id_document = '';
    $license_document = '';

    // Handle primary image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $filetmp = $_FILES['image']['tmp_name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $newfilename = uniqid() . '.' . $ext;
            $destination = 'images/' . $newfilename;
            if (move_uploaded_file($filetmp, $destination)) {
                $image = $destination;
            } else {
                $error = "Failed to upload primary image.";
            }
        } else {
            $error = "Invalid primary image file type.";
        }
    } else {
        $error = "Primary image is required.";
    }

    // Handle multiple additional images
    if (isset($_FILES['images']) && isset($_FILES['images']['tmp_name']) && is_array($_FILES['images']['tmp_name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $image_array = [];
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] == 0) {
                $filename = $_FILES['images']['name'][$key];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $newfilename = uniqid() . '_' . $key . '.' . $ext;
                    $destination = 'images/' . $newfilename;
                    if (move_uploaded_file($tmp_name, $destination)) {
                        $image_array[] = $destination;
                    }
                }
            }
        }
        if (!empty($image_array)) {
            $images = json_encode($image_array);
        }
    }

    // Handle ID document upload
    if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] == 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $filename = $_FILES['id_document']['name'];
        $filetmp = $_FILES['id_document']['tmp_name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['id_document']['size'] <= 5242880) { // 5MB
            $newfilename = uniqid() . '_id.' . $ext;
            $destination = 'uploads/' . $newfilename;
            if (move_uploaded_file($filetmp, $destination)) {
                $id_document = $destination;
            } else {
                $error = "Failed to upload ID document.";
            }
        } else {
            $error = "Invalid ID document file type or size.";
        }
    } else {
        $error = "ID document is required.";
    }

    // Handle license document upload
    if (isset($_FILES['license_document']) && $_FILES['license_document']['error'] == 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $filename = $_FILES['license_document']['name'];
        $filetmp = $_FILES['license_document']['tmp_name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['license_document']['size'] <= 5242880) { // 5MB
            $newfilename = uniqid() . '_license.' . $ext;
            $destination = 'uploads/' . $newfilename;
            if (move_uploaded_file($filetmp, $destination)) {
                $license_document = $destination;
            } else {
                $error = "Failed to upload license document.";
            }
        } else {
            $error = "Invalid license document file type or size.";
        }
    } else {
        $error = "License document is required.";
    }

    if (!$error) {
        $owner_id = $_SESSION['hostel_owner_id'];

        // Verify that the owner_id exists in hostel_owners table
        $check_stmt = $conn->prepare("SELECT id FROM hostel_owners WHERE id = ?");
        $check_stmt->bind_param("i", $owner_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows == 0) {
            $error = "Invalid session. Please log in again.";
            $check_stmt->close();
        } else {
        $check_stmt->close();
        $stmt = $conn->prepare("INSERT INTO hostels (owner_id, name, location, rating, price, rooms, bathrooms, wifi, distance, water, electricity, security, contact, description, image, images, id_document, license_document, min_booking_fee, booking_fee_valid_days, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("issssiisssssssssssdi", $owner_id, $name, $location, $rating, $price, $rooms, $bathrooms, $wifi, $distance, $water, $electricity, $security, $contact, $description, $image, $images, $id_document, $license_document, $min_booking_fee, $booking_fee_valid_days);

        if ($stmt->execute()) {
            $new_hostel_id = $conn->insert_id;
            
            // Auto-create rooms for the new hostel
            $room_stmt = $conn->prepare("INSERT INTO rooms (hostel_id, room_number, status, capacity, price) VALUES (?, ?, 'available', 1, ?)");
            for ($i = 1; $i <= $rooms; $i++) {
                $room_number = "Room " . str_pad($i, 3, "0", STR_PAD_LEFT);
                $room_stmt->bind_param("isi", $new_hostel_id, $room_number, $price);
                $room_stmt->execute();
            }
            $room_stmt->close();

            // Redirect to approve page to view details
            header("Location: approve.php?type=hostel&id=$new_hostel_id&action=view");
            exit();
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>HostelHub - Register New Hostel</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <header class="header">
        <div class="container">
             <h1 class="logo"><img src="images/logo.png" alt="Hostel Finder Logo" /> HostelHub</h1>
            <nav class="nav">
                <ul>
                    <li><a href="index.php">Hostels</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container main-content">
        <h2>Register New Hostel</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form action="register-hostel.php" method="post" enctype="multipart/form-data" class="auth-form">
            <label for="name">Hostel Name</label>
            <input type="text" id="name" name="name" required />

            <label for="location">Location</label>
            <input type="text" id="location" name="location" required />

            <label for="rating">Rating</label>
            <input type="text" id="rating" name="rating" placeholder="e.g. ★★★★☆" required />

            <label for="price">Price (UGX)</label>
            <input type="text" id="price" name="price" required />

            <label for="min_booking_fee">Minimum Booking Fee (UGX)</label>
            <input type="number" id="min_booking_fee" name="min_booking_fee" min="0" step="0.01" required />

            <label for="booking_fee_valid_days">Booking Fee Valid Days</label>
            <input type="number" id="booking_fee_valid_days" name="booking_fee_valid_days" min="1" max="365" required />

            <label for="rooms">Rooms</label>
            <input type="number" id="rooms" name="rooms" min="1" required />

            <label for="bathrooms">Bathrooms</label>
            <input type="number" id="bathrooms" name="bathrooms" min="1" required />

            <label for="wifi">WiFi</label>
            <select id="wifi" name="wifi" required>
                <option value="Available">Available</option>
                <option value="Not Available">Not Available</option>
            </select>

            <label for="distance">Distance from the University to the hostel</label>
            <input type="text" id="distance" name="distance" placeholder="e.g. 2 km" required />

            <label for="water">Water</label>
            <select id="water" name="water" required>
                <option value="Available">Available</option>
                <option value="Not Available">Not Available</option>
            </select>

            <label for="electricity">Electricity</label>
            <select id="electricity" name="electricity" required>
                <option value="Available">Available</option>
                <option value="Not Available">Not Available</option>
            </select>

            <label for="security">Security</label>
            <select id="security" name="security" required>
                <option value="Available">Available</option>
                <option value="Not Available">Not Available</option>
            </select>

            <label for="contact">Contact</label>
            <input type="text" id="contact" name="contact" required />

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" required></textarea>

            <label for="image">Primary Hostel Image *</label>
            <input type="file" id="image" name="image" accept="image/*" required />

            <label for="images">Additional Images (2+ recommended, multiple select)</label>
            <input type="file" id="images" name="images[]" accept="image/*" multiple />
            <div id="imagePreview"></div>

            <label for="id_document">Ugandan National ID</label>
            <input type="file" id="id_document" name="id_document" accept=".pdf,.jpg,.jpeg,.png" required />

            <label for="license_document">Hostel License</label>
            <input type="file" id="license_document" name="license_document" accept=".pdf,.jpg,.jpeg,.png" required />

            <button type="submit">Register Hostel</button>
        </form>
    </main>

    <footer class="footer">
        <p>&copy; 2026 HostelHub. All rights reserved.</p>
    </footer>
</body>
</html>
</create_file>
