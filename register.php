<?php
require_once 'config/db.php';
require_once 'includes/activity_logs.php';

session_start();

$pageTitle = "Register";

$snackbarStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$snackbarMessage = isset($_GET['message']) ? trim($_GET['message']) : '';

// Helper function to handle redirects with snackbar notices
function redirectWithMessage($status, $message, $url) {
    $queryParams = $_GET;
    $queryParams['status'] = $status;
    $queryParams['message'] = $message;
    header("Location: $url" . http_build_query($queryParams));
    exit;
}

if (isset($_SESSION['user_id'])) {
    $module = $_SESSION['module'];
    $userID = $_SESSION['user_id'];
    logActivity($conn, $userID, 'Logs in Back Automatically', 'User session is still active.');
    header("Location: /amsc_indaw/$module/dashboard?status=info&message=" . urlencode("Welcome back."));
    exit();
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']) ?? '';
    $email = trim($_POST['email']) ?? '';
    $password = trim($_POST['password']) ?? '';
    $confirmPassword = trim($_POST['confirmPassword']) ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        redirectWithMessage('error', 'Please complete filling in all details.', 'register?'
        );
    }

    if (strlen($password) <= 6) {
        redirectWithMessage('error', 'Passwords must be at least six (6) characters long.', 'register?');
    }

    if ($password !==  $confirmPassword) {
        redirectWithMessage('error', 'Passwords do not match.', 'register?');
    }

    try {
        $sql = "INSERT INTO users (Email, Username, Password, RoleID) VALUES (?, ?, ?, 2)";
        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            throw new Exception("Prepared statement error: " . mysqli_error($conn));
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        mysqli_stmt_bind_param($stmt, 'sss', $email, $username, $hashedPassword);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Statement execution error: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        $userID = mysqli_insert_id($conn);

        logActivity($conn, $userID, 'New Applicant Account Created.', "Applicant @$username's has successfully been created. (ID: $userID).");
        redirectWithMessage('success', "Applicant account (@$username) successfully created.", 'login?');

    } catch (Exception $e) {
        $msg = $e->getMessage();
        redirectWithMessage('error', $msg, 'register?');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/head.php"; ?>
</head>
<body class="auth-body">

    <div class="auth-card">
        <div class="auth-header" onclick="redirect('index');" title="Click to go home." style="cursor: pointer;">
            <img src="assets/images/site/amsc.webp" alt="AMSC Logo" class="auth-logo" onerror="this.style.display='none'">
            <h2>Create Account</h2>
            <p>Sign up to apply for admission at AMSC</p>
        </div>

        <!-- Alert Container for Dynamic JS Messages -->
        <div id="alertBox" class="alert alert-danger hidden">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span id="alertText">Please fill in all fields correctly.</span>
        </div>

        <form id="registerForm" class="auth-form" method="post" action="register" novalidate>
            <div class="form-group">
                <label for="email"><i class="fa-solid fa-envelope"></i> Email Address *</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="student@example.com" required>
            </div>

            <div class="form-group">
                <label for="username"><i class="fa-solid fa-envelope"></i> Username *</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="username" required>
            </div>

            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock"></i> Password *</label>
                <div class="input-password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Create a password" required>
                    <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="confirmPassword"><i class="fa-solid fa-shield-halved"></i> Confirm Password *</label>
                <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" placeholder="Confirm your password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fa-solid fa-user-plus"></i> Register
            </button>
        </form>

        <div class="auth-footer">
            <p>Already have an account? <a href="login?as=applicant">Login here</a></p>
        </div>
    </div>

    <?php include 'includes/snackbar.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>