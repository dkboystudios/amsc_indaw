<?php
require_once 'config/db.php';
require_once 'includes/activity_logs.php';

session_start();

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

if (isset($_SESSION['user_id']) && isset($_SESSION['module'])) {
    $module = $_SESSION['module'];
    $userID = $_SESSION['user_id'];
    logActivity($conn, $userID, 'Logs in Back Automatically', 'User session is still active.');
    header("Location: /amsc_indaw/$module/dashboard?status=info&message=" . urlencode("Welcome back."));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMSC InDaw - Online Admission & Enrollment System</title>
    <!-- Local Font Awesome -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <!-- Vanilla Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="shortcut icon" href="amsc.png" type="image/x-icon">
</head>
<body class="landing-body">

    <!-- Header Navigation -->
    <header class="navbar">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">
                <img src="assets/images/site/amsc.webp" alt="AMSC Logo" class="logo-img" onerror="this.style.display='none'">
                <span class="logo-text">AMSC <strong>InDaw</strong></span>
            </a>
            <nav class="nav-links">
                <a href="#about">About</a>
                <a href="#colleges">Colleges</a>
                <a href="#admission-process">Admission Process</a>
            </nav>
            <div class="nav-auth-buttons">
                <a href="login" class="btn btn-outline"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
                <a href="register" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Apply Now</a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <span class="badge"><i class="fa-solid fa-graduation-cap"></i> Adiong Memorial State College</span>
                <h1 class="hero-title">Welcome to AMSC InnDaw</h1>
                <p class="hero-subtitle">
                    Streamlining your path to academic excellence. Complete your admission application, upload requirements, and track your enrollment status online with ease.
                </p>
                <div class="hero-cta">
                    <a href="register" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-paper-plane"></i> Start Application
                    </a>
                    <a href="login" class="btn btn-secondary btn-lg">
                        <i class="fa-solid fa-id-card"></i> Student Portal
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Admission Process Section -->
    <section id="admission-process" class="section">
        <div class="container">
            <div class="section-title">
                <h2>How to Apply</h2>
                <p>Simple 4-step online admission workflow</p>
            </div>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-icon">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <h3>1. Register Account</h3>
                    <p>Create your personal account using a valid email address to access the portal.</p>
                </div>

                <div class="step-card">
                    <div class="step-icon">
                        <i class="fa-solid fa-file-signature"></i>
                    </div>
                    <h3>2. Complete Profile</h3>
                    <p>Fill out your personal information and select your preferred degree programs.</p>
                </div>

                <div class="step-card">
                    <div class="step-icon">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <h3>3. Upload Documents</h3>
                    <p>Submit required admission credentials such as Form 138 and Birth Certificate.</p>
                </div>

                <div class="step-card">
                    <div class="step-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h3>4. Track & Enroll</h3>
                    <p>Monitor review status, receive your student number, and print your enrollment form.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Colleges Section -->
    <section id="colleges" class="section section-alt">
        <div class="container">
            <div class="section-title">
                <h2>Academic Colleges</h2>
                <p>Explore degree offerings across our academic units</p>
            </div>
            <div class="colleges-grid">
                <div class="college-card">
                    <i class="fa-solid fa-wheat-awn college-icon"></i>
                    <h3>College of Agriculture</h3>
                    <p>COA</p>
                </div>
                <div class="college-card">
                    <i class="fa-solid fa-briefcase college-icon"></i>
                    <h3>College of Business Administration</h3>
                    <p>CBA</p>
                </div>
                <div class="college-card">
                    <i class="fa-solid fa-scale-balanced college-icon"></i>
                    <h3>College of Criminology</h3>
                    <p>COC</p>
                </div>
                <div class="college-card">
                    <i class="fa-solid fa-chalkboard-user college-icon"></i>
                    <h3>College of Education</h3>
                    <p>CED</p>
                </div>
                <div class="college-card">
                    <i class="fa-solid fa-fish college-icon"></i>
                    <h3>College of Fisheries</h3>
                    <p>FISH</p>
                </div>
                <div class="college-card">
                    <i class="fa-solid fa-tree college-icon"></i>
                    <h3>College of Forestry</h3>
                    <p>FOREST</p>
                </div>
                <div class="college-card">
                    <i class="fa-solid fa-laptop-code college-icon"></i>
                    <h3>College of Information Technology</h3>
                    <p>CIT</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container footer-content">
            <p>&copy; 2026 Adiong Memorial State College - AMSC InnDaw. All rights reserved.</p>
            <div class="footer-links">
                <a href="login_as_admin">Staff Login</a>
                <a href="login?">Portal Login</a>
                <a href="register">Apply Now</a>
            </div>
        </div>
    </footer>

    <?php include 'includes/snackbar.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>