<?php
// Start output buffering and session check
ob_start();
require_once 'config/db.php';
require_once 'config/session_check.php';

$pageTitle = "404 Page Not Found - AMSC InnDaw";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "includes/head.php"; ?>
    <style>
        .error-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 80vh;
            text-align: center;
            padding: 20px;
        }
        .error-code {
            font-size: 6rem;
            font-weight: 800;
            color: var(--primary, #0284c7);
            margin: 0;
            line-height: 1;
        }
        .error-title {
            font-size: 1.8rem;
            color: var(--text-color, #1e293b);
            margin: 15px 0 10px 0;
        }
        .error-description {
            color: var(--text-muted, #64748b);
            max-width: 450px;
            font-size: 0.95rem;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body class="dashboard-body">

    <?php 
        // Include header if available, or skip gracefully if optional
        if (file_exists('includes/header.php')) {
            include 'includes/header.php';
        }
    ?>

    <div class="dashboard-wrapper" style="width: 100%; justify-content: center;">
        <main class="dashboard-content" style="padding: 0;">
            <div class="error-container">
                <p class="error-code">404</p>
                <h1 class="error-title">Page Not Found</h1>
                <p class="error-description">
                    The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.
                </p>
                <div class="error-actions">
                    <?php if (isset($userInfo) && !empty($module)): ?>
                        <a href="/amsc_indaw/<?= htmlspecialchars($module) ?>/dashboard" class="btn btn-primary">
                            <i class="fa-solid fa-gauge"></i> Go to Dashboard
                        </a>
                    <?php endif; ?>
                    <a href="/amsc_indaw/index" class="btn btn-secondary">
                        <i class="fa-solid fa-house"></i> Go Home
                    </a>
                </div>
            </div>
        </main>
    </div>

    <?php 
        if (file_exists('includes/snackbar.php')) {
            include "includes/snackbar.php"; 
        }
    ?>
    <script src="assets/js/main.js"></script>
</body>
</html>