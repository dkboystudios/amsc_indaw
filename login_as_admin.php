<?php
require_once 'config/db.php';
require_once 'includes/activity_logs.php';

session_start();

$pageTitle = "Staff login";

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


// Handle login
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    try {
        if (empty($email) || empty($password)) {
            throw new Exception("Please fill in all the fields.");
        }

        $sql = "SELECT u.*, r.Module FROM users u INNER JOIN roles r ON r.ID = u.RoleID WHERE (Email = ? OR Username = ?) AND r.Module = 'admin' LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            throw new Exception("Prepared statement error (Login): " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, 'ss', $email, $email);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Statement execution error (Login): " . mysqli_stmt_error($stmt));
        }

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        $userID = $user['ID'];

        if (!$user) {
            throw new Exception("Admin account not found.");
        } else {
            // Check if password is correct
            if (password_verify($password, $user['Password']) || $password === $user['Password']) {
                $acctStatus = $user['Status'];
                $username = $user['Username'];
                if ($acctStatus === 'Active') {
                    $_SESSION['user_id'] = $userID;
                    $_SESSION['module'] = 'admin';
                    logActivity($conn, $userID, 'Login attempt successful', 'Successfully logs in.');
                    redirectWithMessage('success', 'You have successfully logged in.', "admin/dashboard?");
                } else if ($acctStatus === 'Suspended') {
                    logActivity($conn, $userID, 'Login attempt denied', 'Account is currently suspended.');
                    throw new Exception("Login failed. Account is currently suspended!");
                } else if ($acctStatus === 'Locked') {
                    logActivity($conn, $userID, 'Login attempt denied', 'Account is currently locked.');
                    throw new Exception("Login failed. Account is locked!");
                } else {
                    logActivity($conn, $userID, 'Login failed', 'Failed logging in.');
                    throw new Exception("Login failed. Unknown reason.");
                }
            } else {
                logActivity($conn, $userID, 'Login attempt failed', 'Enters incorrect password.');
                throw new Exception("Login failed. Incorrect password!");
            }
        }

        mysqli_stmt_close($stmt);
        mysqli_stmt_close($roleStmt);

    } catch (Exception $e) {
        $msg = $e->getMessage();
        redirectWithMessage('error', $msg, 'login_as_admin?');
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
            <h2>AMSC InDaw</h2>
            <p>Online Admission & Enrollment System</p>
        </div>

        <div style="font-size: 12px; margin-bottom: 10px; text-align: center; background-color: green; border: 1px solid green; color: white; border-radius: 25px; padding: 5px 10px;">
            <i class="fa-solid fa-key"></i> Staff Login
        </div>

        <!-- Alert Container for Dynamic JS Messages -->
        <div id="alertBox" class="alert alert-danger hidden">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span id="alertText">Invalid credentials.</span>
        </div>

        <form method="post" action="login_as_admin" id="loginForm" class="auth-form" novalidate>
            <div class="form-group">
                <label for="email"><i class="fa-solid fa-envelope"></i> Username</label>
                <input type="text" id="email" name="email" class="form-control" placeholder="Enter your email or username" required>
            </div>

            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                <div class="input-password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                    <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
                </div>
            </div>

            <button type="submit" name="submit" class="btn btn-primary btn-block">
                <i class="fa-solid fa-right-to-bracket"></i> Login
            </button>
        </form>

        <div class="auth-footer">
            <p>Don't have an account? Contact the <strong>System Administrator</strong> to provide you with an account.</p>
        </div>
    </div>

    <?php include 'includes/snackbar.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>