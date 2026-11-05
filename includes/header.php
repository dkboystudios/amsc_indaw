<?php 
$request_uri = $_SERVER['PHP_SELF'];
?>
<!-- Top Navigation Bar -->
<header class="topbar">
    <div class="topbar-left">
        <button id="sidebarToggle" class="btn-icon">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-brand">
            <img src="/amsc_indaw/assets/images/site/amsc.webp" alt="AMSC Logo" class="topbar-logo" onerror="this.style.display='none'">
            <span>AMSC <strong>InDaw</strong></span>
        </div>
    </div>
    <div class="topbar-right">
        <div class="user-profile-menu">
            <span class="user-name">Hi, <?= !empty($userFirstName) ? $userFirstName : 'User' ?> (<?= $module === 'admin' ? 'Admin' : 'Applicant' ?>)!</span>
            <a href="profile" class="btn btn-primary"><i class="fa-solid fa-user"></i> Profile</a>
            <img src="<?= $userProfilePicture ?>" alt="Profile" class="user-avatar">
            <a href="/amsc_indaw/logout" class="btn-logout" title="Logout">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</header>