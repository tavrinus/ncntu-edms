document.addEventListener('DOMContentLoaded', function() {
    const toggleSidebarButton = document.getElementById('toggleSidebar');
    const userSidebar = document.getElementById('userSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    function toggleSidebar() {
        userSidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        const iconElement = toggleSidebarButton.querySelector('i');
        if (userSidebar.classList.contains('active')) {
            iconElement.classList.remove('bi-list');
            iconElement.classList.add('bi-x-lg');
        } else {
            iconElement.classList.remove('bi-x-lg');
            iconElement.classList.add('bi-list');
        }
    }
    if (toggleSidebarButton) {
        toggleSidebarButton.addEventListener('click', toggleSidebar);
    }
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', toggleSidebar);
    }
    if (window.innerWidth <= 767) {
        const sidebarLinks = userSidebar.querySelectorAll('a.list-group-item');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                toggleSidebar();
            });
        });
    }
    window.addEventListener('resize', function() {
        if (window.innerWidth > 767 && userSidebar.classList.contains('active')) {
            userSidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            const iconElement = toggleSidebarButton.querySelector('i');
            iconElement.classList.remove('bi-x-lg');
            iconElement.classList.add('bi-list');
        }
    });
}); 