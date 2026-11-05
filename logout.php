<?php
require_once "config/db.php";
require_once "config/session_check.php";
require_once "includes/activity_logs.php";
/**
 * logout.php
 * Destroys the user session and clears authentication data.
 */

$_SESSION = array();

// 2. Destroy the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// 3. Completely destroy the session on the server
session_destroy();

/**
 * 4. Redirect to index.php with a success snackbar message.
 * We use 'status=success' so the snackbar is Green,
 * but you can use 'info' if you have a blue styling.
 */

logActivity($conn, $userID, 'Logout', 'Logs out successfully. User session destroyed.');
header("Location: index?status=info&message=Logged out successfully. Have a great day!")
?>