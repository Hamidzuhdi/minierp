    </main>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Highlight active menu
        $(document).ready(function() {
            let currentPath = window.location.pathname;
            $('.sidebar .nav-link').each(function() {
                if (this.href && currentPath.includes($(this).attr('href'))) {
                    $(this).addClass('active');
                }
            });
        });
    </script>
</body>
</html>
