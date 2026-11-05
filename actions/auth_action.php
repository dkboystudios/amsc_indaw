<?php

// actions/auth_action.php

session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

// --- HANDLE REGISTRATION ---
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Basic validation
    if (empty($email) || empty($password) || empty($confirmPassword)) {
        header("Location: ../register.php?error=empty_fields");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../register.php?error=invalid_email");
        exit();
    }

    if ($password != $confirmPassword) {
        header("Location: ../register.php?error=password_mismatch");
        exit();
    }

    // Check if email already exists
    $checkSql = "SELECT ID FROM users WHERE Email = '$email' LIMIT 1";
    $checkResult = mysqli_query($conn, $sql);
    if (mysqli_num_rows($checkResult) > 0) {
        header("Location: ../register.php?error=email_exists");
        exit();
    }

    // Fetch Application Role ID (Default = 2)
    $roleSql = "SELECT ID FROM roles WHERE RoleName = 'Applicant' LIMIT 1";
    $roleResult = mysqli_query($conn, $roleSql);
    $roleRow = mysqli_fetch_assoc($roleResult);
    $roleID = $roleRow ? $roleRow['ID'] : 2;

    // Hash Password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Insert User
    $insertSql = "INSERT INTO users (Email, Password, RoleID, IsVerified) VALUES (?, ?, ?, 1)";

    $insertStmt = mysqli_prepare($conn, $insertSql);

    // if 
}

?>