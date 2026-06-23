<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}
header("Location: index.php");
exit();
?>
