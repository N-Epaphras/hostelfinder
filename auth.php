II<?php
session_start();
include 'db.php';

// ================= PROCESS POST (ALL LOGIC AT TOP) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'student';
    $action   = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $whatsapp = trim($_POST['whatsapp'] ?? '');

    // ================= ADMIN LOGIN (handle before role restriction) =================
    if ($action === 'login' && $role === 'admin') {
        if (empty($username) || empty($password)) {
            header("Location: auth.php?role=admin&error=empty");
            exit();
        }

        error_log("LOGIN DEBUG: admin login attempt, username=$username");

        $sql = "SELECT id, username, password, role FROM users WHERE (email=? OR username=?) AND role='admin'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            error_log("Admin user found: " . print_r($user, true));
            
            if (!password_verify($password, $user['password'])) {
                error_log("Admin password mismatch");
                header("Location: auth.php?role=admin&error=invalid");
                exit();
            }

            // SESSION
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = 'admin';
            error_log("Admin session set, redirecting to approve.php");

            header("Location: approve.php");
            exit();
        } else {
            error_log("Admin user not found");
            header("Location: auth.php?role=admin&error=notfound");
            exit();
        }
    }

    // ================= OTHER ROLES (restrict after admin handling) =================
    $valid_roles = ['student', 'landlord'];
    if (!in_array($role, $valid_roles)) $role = 'student';

// ================= LOGIN =================
    if ($action === 'login') {
        if (empty($username) || empty($password)) {
            header("Location: auth.php?role=" . urlencode($role) . "&error=empty");
            exit();
        }

        error_log("LOGIN DEBUG: role=$role, username=$username");

        // ================= STUDENT/LANDLORD LOGIN =================
        if ($role === 'landlord') {
            $sql = "SELECT id, username, password, status FROM hostel_owners WHERE email=? OR username=?";
            error_log("Using hostel_owners query");
        } else {
            $sql = "SELECT id, username, password, role FROM users WHERE email=? OR username=?";
            error_log("Using users query");
        }

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            // Clear approval popup after successful landlord login
            if ($role === 'landlord') {
                unset($_SESSION['approval_popup']);
            }

        if ($user = $result->fetch_assoc()) {
            error_log("User found: " . print_r($user, true));
            
            if (!password_verify($password, $user['password'])) {
                error_log("Password mismatch");
                header("Location: auth.php?role=" . urlencode($role) . "&error=invalid");
                exit();
            }

            // landlord approval check
            if ($role === 'landlord' && $user['status'] !== 'approved') {
                error_log("Landlord status not approved: " . $user['status']);
                header("Location: auth.php?role=landlord&error=pending");
                exit();
            }

            // SESSION
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['hostel_owner_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $role;
            error_log("Session set: role=$role, redirecting...");

            // REDIRECT
            if ($role === 'landlord') {
                error_log("Landlord redirect to register-hostel.php");
                header("Location: register-hostel.php");
            } else {
                error_log("Student/other redirect to index.php");
                header("Location: index.php");
            }
            exit();
        } else {
            error_log("User not found in database");
            header("Location: auth.php?role=" . urlencode($role) . "&error=notfound");
            exit();
        }
    }

    // ================= REGISTER =================
    elseif ($action === 'register') {
        if (empty($username) || empty($email) || empty($password)) {
            header("Location: auth.php?role=" . urlencode($role) . "&action=register&error=empty");
            exit();
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // STUDENT
        if ($role === 'student') {
            $check = $conn->prepare("SELECT id FROM users WHERE email=? OR username=?");
            $check->bind_param("ss", $email, $username);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                header("Location: auth.php?role=" . urlencode($role) . "&action=register&error=exists");
                exit();
            }

            $stmt = $conn->prepare("
                INSERT INTO users (username, email, password, role, whatsapp)
                VALUES (?, ?, ?, 'student', ?)
            ");
            $stmt->bind_param("ssss", $username, $email, $hashedPassword, $whatsapp);

            if ($stmt->execute()) {
                header("Location: auth.php?role=student&success=registered");
            } else {
                header("Location: auth.php?role=" . urlencode($role) . "&action=register&error=failed");
            }
            $stmt->close();
        }

        // LANDLORD
        elseif ($role === 'landlord') {
            $check = $conn->prepare("SELECT id FROM hostel_owners WHERE email=? OR username=?");
            $check->bind_param("ss", $email, $username);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                header("Location: auth.php?role=" . urlencode($role) . "&action=register&error=exists");
                exit();
            }

            $stmt = $conn->prepare("
                INSERT INTO hostel_owners (username, email, password, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->bind_param("sss", $username, $email, $hashedPassword);

            if ($stmt->execute()) {
                header("Location: auth.php?role=landlord&success=pending");
            } else {
                header("Location: auth.php?role=" . urlencode($role) . "&action=register&error=failed");
            }
            $stmt->close();
        }
        exit();
    }
}

// ================= INIT VARS AFTER PROCESSING =================
$message = '';
$role = $_GET['role'] ?? 'student';
$action = $_GET['action'] ?? 'login';

// Early redirect if already logged in (skip for admins/from=admin/unauthorized)
if (isset($_SESSION['user_id']) && 
    !( (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
       (isset($_GET['from']) && $_GET['from'] === 'admin') ||
       isset($_GET['unauthorized']) )) {
    error_log("Early redirect: existing session user_id=" . $_SESSION['user_id'] . ", role=" . ($_SESSION['role'] ?? 'none'));
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelFinder - Login / Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="cinematic.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h3>HostelFinder</h3>
                        <p class="mb-0">Welcome! Please <?php echo $action === 'login' ? 'login' : 'register'; ?></p>
                    </div>
                    <div class="card-body p-0">
                        
                            <!-- Role Selector Tabs -->
                            <ul class="nav nav-tabs border-0" id="roleTabs">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $role==='student' ? 'active' : ''; ?>" href="?role=student&action=<?php echo $action; ?>" data-role="student">Student</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $role==='landlord' ? 'active' : ''; ?>" href="?role=landlord&action=<?php echo $action; ?>" data-role="landlord">Landlord</a>
                                </li>
                                <?php if ($action === 'login'): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $role==='admin' ? 'active' : ''; ?>" href="?role=admin&action=login" data-role="admin">Admin</a>
                                </li>
                                <?php endif; ?>
                            </ul>

                            <!-- Toggle Login/Register -->
                            <div class="p-4">
                                <?php 
                                // Handle messages from redirects
                                // Handle messages from redirects
                                if (isset($_GET['error'])) {
                                    $error = $_GET['error'];
                                    switch($error) {
                                        case 'empty': $msg = 'Please fill all fields.'; break;
                                        case 'invalid': $msg = 'Invalid credentials.'; break;
                                        case 'notfound': $msg = 'User not found.'; break;
                                        case 'pending': $msg = 'Account pending approval.'; break;
                                        case 'exists': $msg = 'User already exists.'; break;
                                        case 'failed': $msg = 'Registration failed. Try again.'; break;
                                        default: $msg = 'An error occurred.';
                                    }
                                    echo '<div class="alert alert-danger mb-3">' . htmlspecialchars($msg) . '</div>';
                                } elseif (isset($_GET['unauthorized'])) {
                                    echo '<div class="alert alert-warning mb-3">Admin access required. Please login as admin.</div>';
                                } elseif (isset($_GET['success'])) {
                                    $success = $_GET['success'];
                                    switch($success) {
                                        case 'registered': $msg = 'Account created! Welcome aboard.'; break;
                                        case 'pending': $msg = 'Account created! Awaiting admin approval.'; break;
                                        case 'landlord_approved':
                                            $username = $_GET['username'] ?? 'your account';
                                            $msg = "Your landlord account ($username) has been approved by admin! You can now login.";
                                            break;
                                        case 'approval_complete':
                                            $type = $_GET['type'] ?? 'item';
                                            $name = $_GET['name'] ?? '';
                                            $msg = "$type '$name' approved successfully!";
                                            break;
                                        default: $msg = 'Success!';
                                    }
                                    echo '<div class="alert alert-success mb-3">' . htmlspecialchars($msg) . '</div>';
                                }

                                // ================= APPROVAL POPUP FOR LANDLORD =================
                                $popupMsg = '';
                                if ($role === 'landlord' && isset($_SESSION['approval_popup'])) {
                                    $popupMsg = $_SESSION['approval_popup'];
                                    unset($_SESSION['approval_popup']);
                                }
                                ?>

                                <?php if ($popupMsg): ?>
                                <!-- Approval Success Modal -->
                                <div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title" id="approvalModalLabel">Account Approved! 🎉</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body text-center">
                                                <div class="alert alert-success mb-0">
                                                    <?= htmlspecialchars($popupMsg) ?>
                                                </div>
                                                <p class="mt-3 mb-0">You can now login and manage your hostels.</p>
                                            </div>
                                            <div class="modal-footer justify-content-center">
                                                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Got it!</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var approvalModal = new bootstrap.Modal(document.getElementById('approvalModal'));
                                    approvalModal.show();
                                });
                                </script>
                                <?php endif; ?>

                            <!-- Forms -->
                            <div class="toggle-form">
                                <?php if ($action === 'login'): ?>
                                    <!-- LOGIN FORM -->
                                    <form action="auth.php" method="POST" class="needs-validation" novalidate>
                                        <input type="hidden" name="action" value="login">
                                        <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Username or Email</label>
                                            <input type="text" name="username" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <input type="password" name="password" class="form-control" required>
                                        </div>
                                        <?php if ($role === 'admin'): ?>
                                        <div class="alert alert-info mb-3">
                                            <strong>Admin Login Only:</strong> Contact support for account setup if needed.
                                        </div>
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-primary w-100">Login</button>
                                    </form>
                                <?php else: ?>
                                    <!-- REGISTER FORM -->
                                    <form action="auth.php" method="POST" class="needs-validation" novalidate>
                                        <input type="hidden" name="action" value="register">
                                        <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" name="username" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <input type="password" name="password" class="form-control" required minlength="6">
                                        </div>
                                        
                                        <?php if ($role === 'landlord'): ?>
                                        <div class="alert alert-warning mb-3">
                                            <strong>NOTE: Important for Landlords:</strong> After registering, please wait <strong>24 hours</strong> for admin approval before attempting to login.
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($role === 'student'): ?>
                                        <div class="mb-3">
                                            <label class="form-label">WhatsApp (optional)</label>
                                            <input type="text" name="whatsapp" class="form-control" placeholder="e.g. +1234567890">
                                        </div>
                                        <?php endif; ?>
                                        <button type="submit" class="btn btn-success w-100">Create Account</button>
                                    </form>
                                <?php endif; ?>

                                <!-- Toggle Link -->
                                <?php if ($role !== 'admin'): ?>
                                <div class="text-center mt-3">
                                    <?php if ($action === 'login'): ?>
                                        <p>Don't have an account? <a href="?role=<?php echo $role; ?>&action=register" class="text-primary">Register here</a></p>
                                    <?php else: ?>
                                        <p>Already have an account? <a href="?role=<?php echo $role; ?>&action=login" class="text-primary">Login here</a></p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Role tab switching
        document.querySelectorAll('#roleTabs .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const role = this.dataset.role;
                window.location.href = `?role=${role}&action=<?php echo $action; ?>`;
            });
        });
    </script>
</body>
</html>
