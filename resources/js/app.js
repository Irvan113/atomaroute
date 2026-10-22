import './bootstrap';
import { pomodoroTimer } from './pomodoro';

// Register Alpine components
function registerAlpineComponents() {
    if (window.Alpine && typeof window.Alpine.data === 'function') {
        window.Alpine.data('pomodoroTimer', pomodoroTimer);
    }
}

document.addEventListener('alpine:init', registerAlpineComponents);
document.addEventListener('livewire:init', registerAlpineComponents);
if (window.Alpine && typeof window.Alpine.data === 'function') {
    registerAlpineComponents();
}

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
