(() => {
    const preference = window.matchMedia('(prefers-color-scheme: dark)');
    let saved;
    try { saved = localStorage.getItem('theme'); } catch (_) { /* Storage may be disabled. */ }
    function setTheme(theme) {
        document.documentElement.dataset.theme = theme;
        const button = document.getElementById('themeToggle');
        if (button) {
            button.setAttribute('aria-label', `Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`);
            const label = document.getElementById('themeText');
            if (label) label.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
            const icon = button.querySelector('i');
            if (icon) { icon.textContent = theme === 'dark' ? '☀' : '◐'; icon.setAttribute('aria-hidden', 'true'); }
        }
    }
    setTheme(saved === 'dark' || saved === 'light' ? saved : preference.matches ? 'dark' : 'light');
    document.addEventListener('DOMContentLoaded', () => {
        setTheme(document.documentElement.dataset.theme);
        document.getElementById('themeToggle')?.addEventListener('click', () => {
            saved = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            setTheme(saved);
            try { localStorage.setItem('theme', saved); } catch (_) { /* Keep this session usable. */ }
        });
    });
    preference.addEventListener('change', () => { if (saved !== 'dark' && saved !== 'light') setTheme(preference.matches ? 'dark' : 'light'); });
})();
