// Toggle sidebar on mobile
const toggleBtn = document.getElementById('sidebarToggle');
const sidebar = document.querySelector('.sidebar');
if (toggleBtn && sidebar && !window.sidebarToggleInitialized) {
    window.sidebarToggleInitialized = true;

    toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar.classList.toggle('active');
    });

    document.addEventListener('click', (e) => {
        const clickInsideSidebar = sidebar.contains(e.target);
        const clickToggleButton = toggleBtn.contains(e.target);

        if (sidebar.classList.contains('active') && !clickInsideSidebar && !clickToggleButton) {
            sidebar.classList.remove('active');
        }
    });
}