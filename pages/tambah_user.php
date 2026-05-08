<?php
/**
 * Tambah Pegawai Baru (Admin)
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/role_helper.php';

requireLogin();

if ($_SESSION['role'] !== 'admin') {
    setFlash('error', 'Anda tidak memiliki akses!');
    redirect(BASE_URL . '/index.php');
}

$pageTitle = 'Tambah Pegawai Baru';

// Ambil daftar atasan (untuk pilihan atasan_id)
$stmt = $pdo->query("SELECT id, nama, jabatan FROM users WHERE aktif = 1 ORDER BY nama ASC");
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="card" style="margin: 0 auto; max-width: 1200px;">
    <div class="card-header">
        <h3>➕ Form Tambah Pegawai Baru (Multi-Add)</h3>
    </div>
    <div class="card-body">
        <div class="form-info warning" style="margin-bottom: 20px;">
            ℹ️ Password default untuk pegawai baru adalah: <strong>password123</strong>. Pegawai bisa mengubahnya nanti. Saldo cuti akan digenerate otomatis saat pegawai pertama kali login atau diajukan cuti.
        </div>

        <form action="<?= BASE_URL ?>/proses/tambah_user.php" method="POST" id="form-tambah-pegawai">
            <div class="table-wrapper" style="overflow-x: auto; margin-bottom: 16px;">
                <table style="width: 100%; min-width: 1100px;" id="table-pegawai">
                    <thead>
                        <tr>
                            <th style="width: 12%">NIP <span style="color:red">*</span></th>
                            <th style="width: 15%">Nama <span style="color:red">*</span></th>
                            <th style="width: 8%">L/P <span style="color:red">*</span></th>
                            <th style="width: 10%">Status <span style="color:red">*</span></th>
                            <th style="width: 12%">Jabatan <span style="color:red">*</span></th>
                            <th style="width: 13%">Pangkat/Gol</th>
                            <th style="width: 12%">Mulai Kerja</th>
                            <th style="width: 10%">Role</th>
                            <th style="width: 5%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-pegawai">
                        <tr class="row-pegawai">
                            <td><input type="text" name="nip[]" class="form-control" required placeholder="NIP"></td>
                            <td><input type="text" name="nama[]" class="form-control" required placeholder="Nama"></td>
                            <td>
                                <select name="jenis_kelamin[]" class="form-control" required>
                                    <option value="L">L</option>
                                    <option value="P">P</option>
                                </select>
                            </td>
                            <td>
                                <select name="status_pegawai[]" class="form-control" required>
                                    <option value="PNS">PNS</option>
                                    <option value="PPPK">PPPK</option>
                                    <option value="Hakim">Hakim</option>
                                </select>
                            </td>
                            <td><input type="text" name="jabatan[]" class="form-control" required placeholder="Jabatan"></td>
                            <td><input type="text" name="pangkat[]" class="form-control" placeholder="Pangkat"></td>
                            <td><input type="date" name="tgl_mulai_kerja[]" class="form-control" required value="<?= date('Y-m-d') ?>"></td>
                            <td>
                                <select name="role[]" class="form-control" required>
                                    <?php foreach (ROLE_LABELS as $roleVal => $roleLabel): ?>
                                    <option value="<?= $roleVal ?>" <?= $roleVal === 'staf_umk' ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm btn-hapus-baris" style="padding: 6px 10px;">X</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="margin-bottom: 24px;">
                <button type="button" class="btn btn-secondary" id="btn-tambah-baris">➕ Tambah Baris Lagi</button>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--glass-border); margin: 24px 0;">

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="<?= BASE_URL ?>/pages/kelola_user.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Semua Pegawai</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('tbody-pegawai');
    const btnTambah = document.getElementById('btn-tambah-baris');

    // Tambah baris baru
    btnTambah.addEventListener('click', function() {
        const trLama = tbody.querySelector('.row-pegawai');
        const trBaru = trLama.cloneNode(true);
        
        // Kosongkan value input text/date
        trBaru.querySelectorAll('input').forEach(input => {
            if (input.type === 'date') {
                input.value = '<?= date('Y-m-d') ?>';
            } else {
                input.value = '';
            }
        });
        
        // Reset select ke opsi pertama
        trBaru.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
        
        tbody.appendChild(trBaru);
    });

    // Hapus baris
    tbody.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-hapus-baris')) {
            const baris = document.querySelectorAll('.row-pegawai');
            if (baris.length > 1) {
                e.target.closest('tr').remove();
            } else {
                alert('Minimal harus ada 1 pegawai untuk ditambahkan!');
            }
        }
    });
    // === Auto-fill tgl_mulai_kerja dari NIP (PNS & Hakim) ===
    function parseTglMulaiFromNip(nip) {
        const clean = nip.replace(/\D/g, '');
        if (clean.length < 14) return null;
        const year  = clean.substring(8, 12);
        const month = clean.substring(12, 14);
        const y = parseInt(year, 10);
        const m = parseInt(month, 10);
        if (y < 1950 || y > 2100 || m < 1 || m > 12) return null;
        return `${year}-${month.padStart(2, '0')}-01`;
    }

    function tryAutoFillRow(tr) {
        const nipEl    = tr.querySelector('input[name="nip[]"]');
        const statusEl = tr.querySelector('select[name="status_pegawai[]"]');
        const tglEl    = tr.querySelector('input[name="tgl_mulai_kerja[]"]');
        if (!nipEl || !statusEl || !tglEl) return;
        const status = statusEl.value;
        if (status !== 'PNS' && status !== 'Hakim') return;
        const tgl = parseTglMulaiFromNip(nipEl.value);
        if (tgl) {
            tglEl.value = tgl;
            tglEl.style.borderColor = 'var(--orange-400)';
            setTimeout(() => tglEl.style.borderColor = '', 1500);
        }
    }

    // Event delegation: tangkap input NIP dan perubahan status di semua baris
    tbody.addEventListener('input', function(e) {
        if (e.target.name === 'nip[]') {
            tryAutoFillRow(e.target.closest('tr'));
        }
    });
    tbody.addEventListener('blur', function(e) {
        if (e.target.name === 'nip[]') {
            tryAutoFillRow(e.target.closest('tr'));
        }
    }, true);
    tbody.addEventListener('change', function(e) {
        if (e.target.name === 'status_pegawai[]') {
            tryAutoFillRow(e.target.closest('tr'));
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
