<!DOCTYPE html>
<html lang="en" id="sh-root">

<head>
    <script>
        (function() {
            var theme = localStorage.getItem('sh_theme') || 'light';
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var resolved = theme === 'auto' ? (prefersDark ? 'dark' : 'light') : theme;
            document.documentElement.setAttribute('data-theme', resolved);
            var accent = localStorage.getItem('sh_accent');
            if (accent) document.documentElement.style.setProperty('--primary', accent);
            var fontSize = localStorage.getItem('sh_font_size');
            if (fontSize) document.documentElement.style.setProperty('font-size', fontSize + 'px');
        })();
    </script>
