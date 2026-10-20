import './bootstrap';

// Global dark mode toggle helper
if (typeof window !== 'undefined' && !window.toggleDarkMode) {
    window.toggleDarkMode = function() {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('darkMode', 'false');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('darkMode', 'true');
        }
        window.dispatchEvent(new Event('dark-mode-toggled'));
    };
}
