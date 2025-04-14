document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    const themeText = document.getElementById('themeText');
    const themeIcon = themeToggle.querySelector('.bi');
    
    function setTheme(theme) {
        html.setAttribute('data-bs-theme', theme);
        document.body.classList.remove('theme-light', 'theme-dark');
        document.body.classList.add('theme-' + theme);
        localStorage.setItem('theme', theme);
        
        if (theme === 'dark') {
            themeText.textContent = 'Light Mode';
            themeIcon.className = 'bi bi-sun';
            themeToggle.classList.remove('btn-dark');
            themeToggle.classList.add('btn-light');
        } else {
            themeText.textContent = 'Dark Mode';
            themeIcon.className = 'bi bi-moon';
            themeToggle.classList.remove('btn-light');
            themeToggle.classList.add('btn-dark');
        }
    }
    
    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (savedTheme) {
        setTheme(savedTheme);
    } else {
        setTheme(prefersDark ? 'dark' : 'light');
    }
    
    themeToggle.addEventListener('click', function() {
        const currentTheme = html.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
    });
});