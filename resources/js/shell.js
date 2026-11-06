document.addEventListener('alpine:init', () => {
    window.Alpine.data('shell', () => ({
        menu: false,
        toggleTheme() {
            const root = document.documentElement;
            const light = root.dataset.theme !== 'light';
            root.classList.toggle('dark', !light);
            if (light) root.dataset.theme = 'light';
            else delete root.dataset.theme;
            try { localStorage.setItem('dot-agents-theme', light ? 'light' : 'dark'); } catch {}
        },
    }));
});
