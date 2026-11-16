<link rel="stylesheet" href="{{ asset('css/smart-ui.css') }}?v=1.0">
<script>
    (function () {
        var root = document.documentElement;
        var reduceMotion = false;
        var introSeen = false;

        try {
            reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (error) {
            reduceMotion = false;
        }

        try {
            introSeen = window.sessionStorage.getItem('sa_intro_seen') === '1';
        } catch (error) {
            introSeen = true;
        }

        if (introSeen || reduceMotion) {
            root.classList.add('sa-intro-skip');
            root.classList.add('sa-page-ready');
        } else {
            root.classList.add('sa-intro-run');
        }
    })();
</script>
