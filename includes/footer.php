    </div><!-- /.content-area -->
</div><!-- /.main-content -->
</div><!-- /.app-layout -->

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cari semua elemen select
    const selects = document.querySelectorAll('select');
    
    selects.forEach(select => {
        // Jika option lebih dari 5, jadikan Tom Select
        if (select.options.length > 5) {
            new TomSelect(select, {
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                }
            });
        }
    });
});
</script>
</body>
</html>
