document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    highlightCurrentPage();
});
function highlightCurrentPage() {
    const currentPage = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        const linkPath = new URL(link.href).pathname;
        if (currentPage === linkPath || 
            (linkPath !== '/' && currentPage.includes(linkPath))) {
            link.classList.add('active');
        }
    });
} 