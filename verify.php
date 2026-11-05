<?php
require_once 'config/db.php';
require_once 'includes/activity_logs.php';
require_once 'config/session_check.php';

$verifiedFlag = filter_input(INPUT_GET, 'verified', FILTER_VALIDATE_INT);

$verificationMessage = "Invalid verification link parameters.";
$isSuccess = false;

if ($userID && $verifiedFlag === 1) {
    $stmt = mysqli_prepare($conn, "UPDATE users SET IsVerified = 1 WHERE ID = ?");
    mysqli_stmt_bind_param($stmt, 'i', $userID);
    if (mysqli_stmt_execute($stmt)) {
        $isSuccess = true;
        $verificationMessage = "Your email address has been successfully verified!";
        logActivity($conn, $userID, 'Email Verified', 'User successfully verified email address via link.');
    } else {
        $verificationMessage = "An error occurred while updating verification status.";
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($userID): ?>
        <?php include "includes/head.php"; ?>
    <?php else: ?>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Verification - AMSC InDaw</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php endif; ?>
    <style>
        .verify-card { max-width: 500px; margin: 80px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); text-align: center; border: 1px solid var(--border-color, #e2e8f0); }
    </style>
</head>
<body class="<?= $userID ? 'dashboard-body' : '' ?>">

    <?php if ($userID): ?>
        <?php include 'includes/header.php'; ?>
        <div class="dashboard-wrapper">
            <?php include 'includes/sidebar.php'; ?>
            <main class="dashboard-content">
    <?php endif; ?>

    <div class="verify-card">
        <div style="font-size: 4rem; color: <?= $isSuccess ? '#10b981' : '#ef4444' ?>; margin-bottom: 20px;">
            <i class="fa-solid <?= $isSuccess ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
        </div>
        <h2 style="color: var(--primary, #1e3a8a); margin-bottom: 10px;"><?= $isSuccess ? 'Verification Successful!' : 'Verification Failed' ?></h2>
        <p style="color: #64748b; font-size: 1rem; margin-bottom: 30px;"><?= htmlspecialchars($verificationMessage) ?></p>
        
        <?php if ($userID): ?>
            <a href="<?= $module ?>/profile" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Go Back to Profile</a>
        <?php else: ?>
            <a href="index" class="btn btn-primary"><i class="fa-solid fa-house"></i> Go Home</a>
        <?php endif; ?>
    </div>

    <?php if ($userID): ?>
            </main>
        </div>
    <?php endif; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>