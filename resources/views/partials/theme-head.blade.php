{{--
    Design-system <head> bits — fonts + no-flash theme init.
    Include this BEFORE @vite in every layout so the theme class is on <html>
    before the stylesheet paints (prevents a light→dark flash).
--}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|newsreader:400,500,600,700|jetbrains-mono:400,500&display=swap" />

<script>
    (function () {
        try {
            var stored = localStorage.getItem('theme');
            var dark = stored
                ? stored === 'dark'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;
            var el = document.documentElement;
            el.classList.toggle('dark', dark);
            el.classList.toggle('light', !dark);
        } catch (e) {}
    })();
</script>
<style>[x-cloak]{display:none !important;}</style>
