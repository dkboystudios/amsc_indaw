<?php

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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /amsc_indaw/index?status=error&message=" . urlencode("Unauthorized access. Please log in."));
    exit();
}

$userID = $_SESSION['user_id'];
$module = $_SESSION['module'];
$defaultProfilePhoto = "/amsc_indaw/assets/images/defaults/default-photo.avif";

$user = array();
try {
    // Fetching user info
    $userSql = "SELECT * FROM users WHERE ID = ?";
    $userStmt = mysqli_prepare($conn, $userSql);

    if (!$userStmt) {
        throw new Exception("Prepared statement error (session_check): " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($userStmt, 'i', $userID);

    if (!mysqli_stmt_execute($userStmt)) {
        throw new Exception("Statement execution error (session_check): " . mysqli_stmt_error($stmt));
    }

    $userResult = mysqli_stmt_get_result($userStmt);
    $user = mysqli_fetch_assoc($userResult);
} catch (Exception $e) {
    $msg = $e->getMessage();
    redirectWithMessage('error', $msg, 'dashboard?');
}

$userInfo = array();
$studentInfo = array();

try {
    // Fetching person info
    if ($module === 'admin') {
        $adminSql = "SELECT * FROM staff_members WHERE UserID = ? LIMIT 1";
        $adminStmt = mysqli_prepare($conn, $adminSql);

        if (!$adminStmt) {
            throw new Exception("Prepared statement error (fetching admin info): " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($adminStmt, 'i', $userID);

        if (!mysqli_stmt_execute($adminStmt)) {
            throw new Exception("Statement execution error (fetching admin info): " . mysqli_stmt_error($adminStmt));
        }

        $adminResult = mysqli_stmt_get_result($adminStmt);
        $userInfo = mysqli_fetch_assoc($adminResult);

    } else if ($module === 'applicant') {
        $applicantSql = "SELECT * FROM applicants WHERE UserID = ? LIMIT 1";
        $applicantStmt = mysqli_prepare($conn, $applicantSql);

        if (!$applicantStmt) {
            throw new Exception("Prepared statement error (fetching applicant info): " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($applicantStmt, 'i', $userID);

        if (!mysqli_stmt_execute($applicantStmt)) {
            throw new Exception("Statement execution error (fetching applicant info): " . mysqli_stmt_error($applicantStmt));
        }

        $applicantResult = mysqli_stmt_get_result($applicantStmt);
        $userInfo = mysqli_fetch_assoc($applicantResult);
            
        // Check if applicant is already admitted as student.
        $studSql = "SELECT * FROM students WHERE ApplicantID = ?";
        $studStmt = mysqli_prepare($conn, $studSql);

        if (!$studStmt) {
            throw new Exception("Prepared statement error (fetching student's details): " . mysqli_error($conn));
        }

        $studentApplicantID = $userInfo['ApplicantID'];

        mysqli_stmt_bind_param($studStmt, 'i', $studentApplicantID);

        if (!mysqli_stmt_execute($studStmt)) {
            throw new Exception("Statement execution error (fetching student's details): " . mysqli_stmt_error($studStmt));
        }

        $studRes = mysqli_stmt_get_result($studStmt);
        $studentInfo = mysqli_fetch_assoc($studRes);

    }
} catch (Exception $e) {
    $msg = $e->getMessage();
    redirectWithMessage('error', $msg, 'dashboard?');
}

$userRole = array();
// Get user's role details
try {
    $roleSql = "SELECT r.* FROM roles r INNER JOIN users u ON u.RoleID = r.ID WHERE u.ID = ?";
    $roleStmt = mysqli_prepare($conn, $roleSql);

    if (!$roleStmt) {
        throw new Exception("Prepared statement error (fetching user's role details): " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($roleStmt, 'i', $userID);

    if (!mysqli_stmt_execute($roleStmt)) {
        throw new Exception("Statement execution error (fetching user's role details): " . mysqli_stmt_error($roleStmt));
    }

    $roleResult = mysqli_stmt_get_result($roleStmt);
    $userRole = mysqli_fetch_assoc($roleResult);
} catch (Exception $e) {
    $msg = $e->getMessage();
    redirectWithMessage('error', $msg, 'dashboard');
}

$userProfilePicture = isset($userInfo['ProfilePicture']) ? '/amsc_indaw/assets/uploads/profiles/' . $userInfo['ProfilePicture'] : $defaultProfilePhoto;
$userFirstName = $userInfo['FirstName'] ?? 'User';
$userMiddleName = $userInfo['MiddleName'] ?? '';
$userLastName = $userInfo['LastName'] ?? '';
$userExtName = $userInfo['ExtName'] ?? '';

// Full name setup
$userFullName = "User";
if (isset($userInfo)) {
    $userFullName = $userFirstName . ' ' . $userMiddleName . ' ' . $userLastName . ' ' . $userExtName;
}
$userName = $user['Username'] ?? 'user';
?>