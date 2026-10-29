<!-- sk2_theme_init.php -->
<script>
    // Initialize Theme BEFORE page renders to prevent flashing
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>

<script>
    // Shared Tailwind Configuration for SK OS 2.0
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                colors: {
                    sk: {
                        50: '#eef2ff',
                        100: '#e0e7ff',
                        400: '#818cf8',
                        500: '#6366f1',
                        600: '#4f46e5', // Primary Modern SaaS Indigo
                        700: '#4338ca',
                        900: '#312e81',
                    }
                }
            }
        }
    }
</script>