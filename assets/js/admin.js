// ABC FOOD Admin JS
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh dashboard every 30 seconds
    const dashboardStats = document.querySelector('.dashboard-grid');
    if (dashboardStats) {
        setInterval(function() {
            fetch(window.location.href)
                .then(r => r.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newStats = doc.querySelector('.dashboard-grid');
                    if (newStats) dashboardStats.innerHTML = newStats.innerHTML;
                })
                .catch(() => {});
        }, 30000);
    }

    // Confirm dialogs for danger actions
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm || '确认执行此操作?')) {
                e.preventDefault();
            }
        });
    });
});
