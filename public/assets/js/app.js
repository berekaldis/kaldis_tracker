// Kaldis Coffee Telegram Reply Tracker - Front-end JavaScript
document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Sidebar Toggle & Backdrop Handling
    const sidebar = document.getElementById('appSidebar');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const backdrop = document.getElementById('sidebarBackdrop');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('show');
        if (backdrop) backdrop.classList.add('show');
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
    }

    if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    // 2. Auto-dismiss flash alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 6000);
    });

    // 3. Copy to clipboard helper
    const copyBtns = document.querySelectorAll('[data-copy]');
    copyBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const text = this.getAttribute('data-copy');
            if (navigator.clipboard && text) {
                navigator.clipboard.writeText(text).then(() => {
                    const originalHtml = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check2"></i> Copied!';
                    setTimeout(() => {
                        this.innerHTML = originalHtml;
                    }, 2000);
                });
            }
        });
    });
});
