<?php
$current_page = str_replace('.php', '', basename($_SERVER['PHP_SELF']));
$request_uri = $_SERVER['PHP_SELF'];

// Detect whether we are in the admin or applicant section based on the folder path
$isAdmin = strpos($request_uri, '/admin/') !== false;
?>
<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <nav class="sidebar-nav">
        <?php if ($isAdmin): ?>
            <!-- Admin Navigation Menu -->
            <a href="/amsc_indaw/admin/dashboard" class="nav-item <?php echo ($current_page === 'dashboard') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i>
                <span>Dashboard</span>
            </a>
            <a href="/amsc_indaw/admin/applicants" class="nav-item <?php echo ($current_page === 'applicants' || $current_page === 'applicant_view') ? 'active' : ''; ?>">
                <i class="fa-solid fa-briefcase"></i>
                <span>Applicants</span>
            </a>
            <a href="/amsc_indaw/admin/students" class="nav-item <?php echo ($current_page === 'students' || $current_page === 'student_view') ? 'active' : ''; ?>">
                <i class="fa-solid fa-child"></i>
                <span>Students</span>
            </a><a href="/amsc_indaw/admin/employees" class="nav-item <?php echo ($current_page === 'employees' || $current_page === 'employee_designations') ? 'active' : ''; ?>">
                <i class="fa-solid fa-person-chalkboard"></i>
                <span>Employees</span>
            </a>
            <a href="/amsc_indaw/admin/academics" class="nav-item <?php echo ($current_page === 'academics' || $current_page === 'curriculum' || $current_page === 'programs') ? 'active' : ''; ?>">
                <i class="fa-solid fa-graduation-cap"></i>
                <span>Academics</span>
            </a>
            <a href="/amsc_indaw/admin/classes" class="nav-item <?php echo ($current_page === 'classes' || $current_page === 'class_view') ? 'active' : ''; ?>">
                <i class="fa-solid fa-clock"></i>
                <span>Classes & Schedules</span>
            </a>
            <a href="/amsc_indaw/admin/offices" class="nav-item <?php echo ($current_page === 'offices') ? 'active' : ''; ?>">
                <i class="fa-solid fa-book"></i>
                <span>Offices & Colleges</span>
            </a>
            <!-- <a href="/amsc_indaw/admin/finances" class="nav-item <?php echo ($current_page === 'finances') ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Finances & Billings</span>
            </a> -->
            <a href="/amsc_indaw/admin/users" class="nav-item <?php echo ($current_page === 'users') ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>User Accounts & Privileges</span>
            </a>
        <?php else: ?>
            <!-- Applicant Navigation Menu -->
            <a href="/amsc_indaw/applicant/dashboard" class="nav-item <?php echo ($current_page === 'dashboard') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i>
                <span>Dashboard</span>
            </a>
            <a href="/amsc_indaw/applicant/profile" class="nav-item <?php echo ($current_page === 'profile') ? 'active' : ''; ?>">
                <i class="fa-solid fa-id-card"></i>
                <span>Personal Profile</span>
            </a>
            <a href="/amsc_indaw/applicant/documents" class="nav-item <?php echo ($current_page === 'documents') ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-arrow-up"></i>
                <span>Upload Requirements</span>
            </a>
            <a href="/amsc_indaw/applicant/enrollment" class="nav-item <?php echo ($current_page === 'enrollment') ? 'active' : ''; ?>">
                <i class="fa-solid fa-book-open"></i>
                <span>Enrolled Subjects</span>
            </a>
            <a href="/amsc_indaw/applicant/print_com" class="nav-item <?php echo ($current_page === 'print_com') ? 'active' : ''; ?>">
                <i class="fa-solid fa-print"></i>
                <span>Print Enrollment Form</span>
            </a>
        <?php endif; ?>
    </nav>
</aside>