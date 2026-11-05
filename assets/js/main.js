function redirect(url) {
    window.location.href = url;
}

// -------------------------------------
// SNACKBAR CONTROLLER
// -------------------------------------

window.addEventListener('DOMContentLoaded', () => {
    const snackbar = document.getElementById('snackbar');
    if (snackbar) {
        // Trigger show animation
        setTimeout(() => {
            snackbar.classList.add('show');
        }, 100);

        // Auto-hide after 4 seconds
        setTimeout(() => {
            closeSnackbar();
        }, 4100);
    }
});

function closeSnackbar() {
    const snackbar = document.getElementById('snackbar');
    if (snackbar) {
        snackbar.classList.remove('show');
    }
}