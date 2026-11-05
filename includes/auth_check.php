<?php
// includes/auth_check.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkAuth($allowedRoles = []) {
    if (!isset($_SESSION['userID'])) {
        header("Location: ../login.php?error=pls_login");
        exit();
    }

    if (!empty($allowedRoles) && !in_array($_SESSION['roleName'], $allowedRoles)) {
        header("Location: ../login.php?error=unauthorized");
        exit();
    }
}

function isLoggedIn() {
    return isset($_SESSION['userID']);
}
?>