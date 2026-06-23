<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

if (isset($_GET['phone'])) {
    $phone = $_GET['phone'];
    // Format phone number for WhatsApp
    $phone = preg_replace('/\D/', '', $phone); // Remove non-digits
    if (preg_match('/^0/', $phone)) {
        // Local number starting with 0, remove 0 and prepend +256
        $phone = '+256' . substr($phone, 1);
    } elseif (!preg_match('/^\+/', $phone)) {
        // If not starting with +, assume local and prepend +256
        $phone = '+256' . $phone;
    }
    // If already starts with +, use as is
    // Redirect to WhatsApp
    header("Location: https://wa.me/" . $phone);
    exit();
} else {
    echo "Invalid request.";
}
?>
