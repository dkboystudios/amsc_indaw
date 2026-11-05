<!-- SNACKBAR COMPONENT -->

<?php

// Floating Snackbar Component
if (!empty($snackbarMessage)):
    $statusClass = 'snackbar-info';
    $iconClass = 'fa-circle-info';

    switch (strtolower($snackbarStatus)) {
        case 'success':
            $statusClass = 'snackbar-success';
            $iconClass = 'fa-circle-check';
            break;
        case 'error':
        case 'danger':
            $statusClass = 'snackbar-error';
            $iconClass = 'fa-circle-xmark';
            break;
        case 'warning':
            $statusClass = 'snackbar-warning';
            $iconClass = 'fa-triangle-exclamation';
            break;
    }
?>

<div id="snackbar" class="snackbar <?= $statusClass ?>">
    <div class="snackbar-content">
        <i class="fa-solid <?= $iconClass ?>" style="font-size: 1.2rem;"></i>
        <span><?= htmlspecialchars($snackbarMessage) ?></span>
    </div>
    <button class="snackbar-close" onclick="closeSnackbar()"><i class="fa-solid fa-xmark"></i></button>
</div>

<?php endif; ?>