<?php
$designVersion = 'v2'; // V2 is now the default
if ($designVersion === 'v2'): 
?>
    </div> <!-- /.main-container-v2 -->
    <footer style="text-align: center; padding: 24px; color: var(--text-muted); font-size: 13px; margin-top: auto; border-top: 1px solid var(--glass-border);">
        PESUT - Pengajuan Elektronik Surat Izin dan Cuti Terpadu &copy; 2026
    </footer>
<?php else: ?>
    </div><!-- /.content-area -->
</div><!-- /.main-content -->
</div><!-- /.app-layout -->
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
<?php if ($designVersion === 'v2'): ?>
<script src="<?= BASE_URL ?>/assets/js/app_v2.js?v=<?= time() ?>"></script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cari semua elemen select
    const selects = document.querySelectorAll('select');
    
    selects.forEach(select => {
        // Jika option lebih dari 5, jadikan Tom Select
        if (select.options.length > 5 && !select.classList.contains('no-tomselect') && !select.classList.contains('tomselect-init')) {
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
