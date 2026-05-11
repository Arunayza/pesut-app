/**
 * PESUT - Client-side JavaScript
 * Live calculation, form validation, and UI interactions
 */

document.addEventListener('DOMContentLoaded', function () {

    // ---- Sidebar Toggle (PC = collapse icon-only, Mobile = overlay) ----
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar    = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    if (menuToggle && sidebar) {
        const isMobile = () => window.innerWidth <= 768;

        // Apply saved collapsed state on PC
        const savedCollapsed = localStorage.getItem('pesut-sidebar-collapsed') === 'true';
        if (!isMobile() && savedCollapsed) {
            sidebar.classList.add('collapsed');
        }

        menuToggle.addEventListener('click', () => {
            if (isMobile()) {
                // Mobile: show/hide overlay
                sidebar.classList.toggle('open');
                sidebar.classList.remove('collapsed');
            } else {
                // PC: toggle icon-only mode
                const isCollapsed = sidebar.classList.toggle('collapsed');
                localStorage.setItem('pesut-sidebar-collapsed', isCollapsed);
            }
        });

        // Close mobile sidebar on outside click
        document.addEventListener('click', (e) => {
            if (isMobile() &&
                sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) &&
                !menuToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });

        // On resize, clean up state
        window.addEventListener('resize', () => {
            if (!isMobile()) {
                sidebar.classList.remove('open');
                const savedCollapsed = localStorage.getItem('pesut-sidebar-collapsed') === 'true';
                if (savedCollapsed) sidebar.classList.add('collapsed');
                else sidebar.classList.remove('collapsed');
            }
        });
    }

    // Initialize dynamic content first time
    initDynamicContent();

    // Initialize Notification System
    initNotifications();

    // Initialize Smooth SPA Routing (Mini PJAX)
    initMiniPJAX();
});

// ---- Re-runnable Logic for PJAX Content ----
function initDynamicContent() {
    // ---- Auto-dismiss flash messages ----
    const flash = document.querySelector('.flash-message');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            setTimeout(() => flash.remove(), 300);
        }, 5000);
    }

    // ---- FORM CUTI: Live Calculate Hari Kerja ----
    const cutiMulai = document.getElementById('tanggal_mulai');
    const cutiSelesai = document.getElementById('tanggal_selesai');
    const cutiPreview = document.getElementById('cuti-preview');
    const cutiSubmit = document.getElementById('btn-submit-cuti');

    if (cutiMulai && cutiSelesai) {
        const calcCuti = () => {
            const mulai = cutiMulai.value;
            const selesai = cutiSelesai.value;
            const tipeCutiEl = document.getElementById('tipe_cuti');
            const tipe = tipeCutiEl ? tipeCutiEl.value : 'tahunan';

            if (!mulai || !selesai) {
                if (cutiPreview) cutiPreview.classList.remove('show');
                if (cutiSubmit) cutiSubmit.disabled = true;
                return;
            }

            fetch(BASE_URL + `/api/hitung_hari_kerja.php?tanggal_mulai=${mulai}&tanggal_selesai=${selesai}&tipe_cuti=${tipe}`)
                .then(r => r.json())
                .then(data => {
                    // Update sisa cuti badge per tipe
                    const badge = document.getElementById('badge-sisa-cuti');
                    if (badge && data.sisa_cuti !== undefined) {
                        badge.textContent = data.sisa_cuti;
                    }

                    if (cutiPreview) {
                        cutiPreview.classList.add('show');

                        // Hitung hari
                        const hariEl = document.getElementById('preview-hari-kerja');
                        if (hariEl) hariEl.textContent = (data.hari_kerja ?? 0) + ' hari';

                        // Rincian tanggal
                        const tglRow = document.getElementById('preview-tanggal-row');
                        const tglList = document.getElementById('preview-tanggal-list');
                        if (tglRow && tglList && data.tanggal_list && data.tanggal_list.length > 0) {
                            tglRow.style.display = 'flex';
                            tglList.innerHTML = data.tanggal_list.map(t =>
                                `<span style="background:rgba(59,130,246,0.12);color:var(--blue-400,#3b82f6);padding:3px 10px;border-radius:8px;font-size:11px;font-weight:600;border:1px solid rgba(59,130,246,0.2);">${t}</span>`
                            ).join('');
                        } else if (tglRow) {
                            tglRow.style.display = 'none';
                        }

                        // Sisa kuota
                        const sisaEl = document.getElementById('preview-sisa-cuti');
                        if (sisaEl) sisaEl.textContent = (data.sisa_cuti ?? 0) + ' hari';

                        const statusEl = document.getElementById('preview-status');

                        if (data.overlap) {
                            // ⚠️ Overlap warning — styling oranye
                            if (statusEl) {
                                statusEl.innerHTML = `⚠️ ${data.message}`;
                                statusEl.className = 'value overlap-warn';
                                statusEl.style.color = '#f59e0b';
                            }
                            // Tambah baris overlap detail jika belum ada
                            let overlapRow = document.getElementById('preview-overlap-row');
                            if (!overlapRow && data.overlap_list && data.overlap_list.length) {
                                overlapRow = document.createElement('div');
                                overlapRow.id = 'preview-overlap-row';
                                overlapRow.className = 'preview-row';
                                overlapRow.style.cssText = 'flex-direction:column; align-items:flex-start; gap:4px;';
                                overlapRow.innerHTML = `<span class="label" style="color:#f59e0b; font-weight:700;">📋 Pengajuan yang bertabrakan:</span>
                                    <ul style="margin:4px 0 0 12px; padding:0; font-size:11px; color:var(--text-muted);">
                                        ${data.overlap_list.map(o =>
                                            `<li>${o.tanggal_mulai} s/d ${o.tanggal_selesai} &mdash; <strong>${o.status}</strong> (${o.tipe_cuti ?? '-'})</li>`
                                        ).join('')}
                                    </ul>`;
                                cutiPreview.appendChild(overlapRow);
                            }
                            if (cutiSubmit) cutiSubmit.disabled = true;

                        } else {
                            // Hapus overlap row jika sudah tidak overlap
                            const existingRow = document.getElementById('preview-overlap-row');
                            if (existingRow) existingRow.remove();

                            if (data.valid) {
                                if (statusEl) { statusEl.textContent = '✓ Tanggal tersedia & kuota mencukupi'; statusEl.className = 'value'; statusEl.style.color = ''; }
                                if (cutiSubmit) cutiSubmit.disabled = false;
                            } else {
                                if (statusEl) { statusEl.textContent = '✗ ' + data.message; statusEl.className = 'value error'; statusEl.style.color = ''; }
                                if (cutiSubmit) cutiSubmit.disabled = true;
                            }
                        }
                    }
                })
                .catch(() => {
                    showPreviewError('Gagal menghitung. Coba lagi.');
                });
        };

        cutiMulai.addEventListener('change', calcCuti);
        cutiSelesai.addEventListener('change', calcCuti);

        const tipeCutiEl = document.getElementById('tipe_cuti');
        if (tipeCutiEl) tipeCutiEl.addEventListener('change', calcCuti);
    }

    // ---- FORM PULANG CEPAT: Live Calculate Selisih ----
    const pcTanggal = document.getElementById('tanggal_pulang');
    const pcJam = document.getElementById('jam_pulang_diajukan');
    const pcTipe = document.getElementById('tipe_izin_waktu');
    const pcPreview = document.getElementById('pc-preview');
    const pcSubmit = document.getElementById('btn-submit-pc');

    if (pcTanggal && pcJam) {
        const getPcTipeValue = () => {
            const radio = document.querySelector('input[name="tipe_izin_waktu"]:checked');
            if (radio) return radio.value;
            return pcTipe ? pcTipe.value : 'pulang_cepat';
        };

        const updateOnTipeChange = () => {
            const val = getPcTipeValue();
            const isTerlambat = val === 'datang_terlambat';
            const labelJam = document.getElementById('label_jam_diajukan');
            const labelResmi = document.getElementById('label_jam_resmi');
            if (labelJam) labelJam.textContent = isTerlambat ? '⏰ Jam Kedatangan' : '⏰ Jam Kepulangan';
            if (labelResmi) labelResmi.textContent = isTerlambat ? 'Jam Masuk Resmi' : 'Jam Pulang Resmi';
            calcPC();
        };

        if (pcTipe) {
            pcTipe.addEventListener('change', updateOnTipeChange);
        }
        
        // Also listen to radio buttons if present
        document.querySelectorAll('input[name="tipe_izin_waktu"]').forEach(r => {
            r.addEventListener('change', updateOnTipeChange);
        });
        
        // Initial call
        updateOnTipeChange();

        function calcPC() {
            const tgl = pcTanggal.value;
            const jam = pcJam.value;
            const tipe = getPcTipeValue();
            
            if (!tgl || !jam) {
                if (pcPreview) pcPreview.classList.remove('show');
                return;
            }


            fetch(BASE_URL + `/api/hitung_selisih_jam.php?tanggal=${tgl}&jam_pulang=${jam}&tipe=${tipe}`)
                .then(r => r.json())
                .then(data => {
                    if (pcPreview) {
                        pcPreview.classList.add('show');
                        document.getElementById('preview-hari').textContent = data.hari || '-';
                        document.getElementById('preview-jam-resmi').textContent = data.jam_resmi || '-';
                        document.getElementById('preview-selisih').textContent = data.selisih_format || '-';

                        const statusEl = document.getElementById('preview-pc-status');
                        if (data.valid) {
                            statusEl.textContent = '✓ Valid';
                            statusEl.className = 'value';
                            if (pcSubmit) pcSubmit.disabled = false;
                        } else {
                            statusEl.textContent = '✗ ' + data.message;
                            statusEl.className = 'value error';
                            if (pcSubmit) pcSubmit.disabled = true;
                        }
                    }
                })
                .catch((e) => {
                    console.error('calcPC fetch error:', e.message);
                    showPreviewError('Gagal menghitung. Coba lagi.');
                });
        }

        pcTanggal.addEventListener('change', calcPC);
        pcJam.addEventListener('change', calcPC);
    }

    // ---- FORM IZIN: Hitung Hari Kerja & Lampiran ----
    const izinMulai = document.getElementById('tanggal_mulai');
    const izinSelesai = document.getElementById('tanggal_selesai');
    const izinPreview = document.getElementById('izin-preview');
    const formIzinEl = document.getElementById('form-izin');
    const izinSubmit = formIzinEl ? formIzinEl.querySelector('button[type="submit"]') : null;
    const btnSamakan = document.getElementById('btn-samakan-tgl');
    const lampiranGroup = document.getElementById('lampiran-group');
    const lampiranInput = document.getElementById('lampiran');

    if (izinMulai && izinSelesai && document.getElementById('form-izin')) {
        // Tombol samakan tanggal
        if (btnSamakan) {
            btnSamakan.addEventListener('click', () => {
                if (izinMulai.value) {
                    izinSelesai.value = izinMulai.value;
                    calcIzin(); // trigger kalkulasi ulang
                }
            });
        }

        function calcIzin() {
            const mulai = izinMulai.value;
            const selesai = izinSelesai.value;

            // Sembunyikan lampiran default
            if (lampiranGroup) lampiranGroup.style.display = 'none';
            if (lampiranInput) lampiranInput.required = false;

            if (!mulai || !selesai) {
                if (izinPreview) izinPreview.style.display = 'none';
                return;
            }
            if (mulai > selesai) {
                showPreviewError('Tanggal mulai harus sebelum tanggal selesai!');
                if (izinSubmit) izinSubmit.disabled = true;
                return;
            }

            // Izin uses the same API but we ignore the quota check
            fetch(BASE_URL + `/api/hitung_hari_kerja.php?tanggal_mulai=${mulai}&tanggal_selesai=${selesai}`)
                .then(r => r.json())
                .then(data => {
                    if (izinPreview) {
                        izinPreview.style.display = 'block';
                        document.getElementById('preview-hari-izin').textContent = data.hari_kerja + ' hari';

                        if (data.hari_kerja > 0) {
                            if (izinSubmit) izinSubmit.disabled = false;

                            // Jika izin > 1 hari, wajib lampirkan surat dokter
                            if (data.hari_kerja > 1) {
                                if (lampiranGroup) {
                                    lampiranGroup.style.display = 'block';
                                    // Animasi muncul pelan
                                    lampiranGroup.style.animation = 'fadeIn 0.3s ease';
                                }
                                if (lampiranInput) lampiranInput.required = true;
                            }
                        } else {
                            showPreviewError('Tidak ada hari kerja di rentang tanggal tersebut.');
                            if (izinSubmit) izinSubmit.disabled = true;
                        }
                    }
                })
                .catch(() => {
                    showPreviewError('Gagal menghitung. Coba lagi.');
                });
        };

        izinMulai.addEventListener('change', calcIzin);
        izinSelesai.addEventListener('change', calcIzin);
    }

    // ---- Approval Modal ----
    window.openApprovalModal = function (id, aksi) {
        const modal = document.getElementById('approval-modal');
        const form = document.getElementById('approval-form');
        const title = document.getElementById('modal-title');
        if (modal && form) {
            document.getElementById('modal-pengajuan-id').value = id;
            document.getElementById('modal-aksi').value = aksi;
            title.textContent = aksi === 'setujui' ? 'Setujui Pengajuan' : 'Tolak Pengajuan';
            modal.classList.add('show');
        }
    };

    window.closeModal = function () {
        const modal = document.getElementById('approval-modal');
        if (modal) modal.classList.remove('show');
    };

    // ---- Helper ----
    function showPreviewError(msg) {
        const preview = document.querySelector('.preview-box');
        if (preview) {
            preview.classList.add('show');
            preview.innerHTML = `<h4>⚠ Peringatan</h4><p style="color: var(--red-400);">${msg}</p>`;
        }
    }
}

// ---- Notification Functions (global) ----
const BASE_URL = window.PESUT_BASE_URL || '';

function toggleNotifDropdown() {
    const dropdown = document.getElementById('notif-dropdown');
    if (!dropdown) return;
    const isOpen = dropdown.classList.contains('show');
    if (isOpen) {
        dropdown.classList.remove('show');
    } else {
        dropdown.classList.add('show');
        loadNotifications();
    }
}

function loadNotifications() {
    const list = document.getElementById('notif-list');
    if (!list) return;
    list.innerHTML = '<div class="notif-loading" style="text-align:center;padding:16px;color:var(--text-muted);font-size:13px;">⏳ Memuat...</div>';

    fetch(BASE_URL + '/api/notifikasi.php', { credentials: 'same-origin' })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            if (data.error) {
                list.innerHTML = '<div class="notif-empty">🔒 Sesi habis. Silakan refresh.</div>';
                return;
            }
            if (data.notifikasi && data.notifikasi.length > 0) {
                list.innerHTML = data.notifikasi.map(n => {
                    const icon = n.judul.includes('Disetujui') ? '✅'
                               : n.judul.includes('Ditolak')  ? '❌'
                               : n.judul.includes('TTD')      ? '✍️' : '🔔';
                    return `<a href="${n.link || '#'}" class="notif-item ${!n.dibaca ? 'unread' : ''}"
                               onclick="bacaNotif(${n.id})" data-id="${n.id}">
                        <div class="notif-icon">${icon}</div>
                        <div class="notif-content">
                            <div class="notif-title">${n.judul}</div>
                            <div class="notif-text">${n.pesan || ''}</div>
                            <div class="notif-time">${n.waktu}</div>
                        </div>
                    </a>`;
                }).join('');
            } else {
                list.innerHTML = '<div class="notif-empty">🔕 Tidak ada notifikasi</div>';
            }
            updateBadge(data.count);
        })
        .catch(err => {
            list.innerHTML = '<div class="notif-empty">⚠️ Gagal memuat. Cek koneksi.</div>';
            console.error('Notif error:', err);
        });
}

function bacaNotif(id) {
    fetch(BASE_URL + '/api/notifikasi.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ aksi: 'baca', id: id })
    });
}

function bacaSemuaNotif() {
    fetch(BASE_URL + '/api/notifikasi.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ aksi: 'baca_semua' })
    }).then(() => {
        window.location.reload();
    });
}

function updateBadge(count) {
    let badge = document.getElementById('notif-badge');
    const bell = document.getElementById('notif-bell');
    if (!bell) return;

    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.id = 'notif-badge';
            badge.className = 'notif-badge';
            bell.appendChild(badge);
        }
        badge.textContent = count > 9 ? '9+' : count;
    } else if (badge) {
        badge.remove();
    }
}

function initNotifications() {
    document.addEventListener('click', (e) => {
        const wrapper = document.getElementById('notif-wrapper');
        const dropdown = document.getElementById('notif-dropdown');
        if (wrapper && dropdown && !wrapper.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });

    // Auto poll every 60 seconds

    setInterval(() => {
        fetch(BASE_URL + '/api/notifikasi.php', { credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : null)
            .then(data => { if (data && !data.error) updateBadge(data.count); })
            .catch(() => { });
    }, 60000);
}

// ---- Theme Toggle ----
// Apply saved theme immediately (before DOMContentLoaded to prevent flash)
(function () {
    const savedTheme = localStorage.getItem('pesut-theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    // Update icon when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        const icon = document.getElementById('theme-icon');
        if (icon) icon.textContent = savedTheme === 'light' ? '☀️' : '🌙';
    });
})();

function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme') || 'dark';
    const newTheme = current === 'dark' ? 'light' : 'dark';

    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('pesut-theme', newTheme);

    const icon = document.getElementById('theme-icon');
    if (icon) icon.textContent = newTheme === 'light' ? '☀️' : '🌙';
}

// ---- Mini PJAX (SPA Smooth Routing) ----
function initMiniPJAX() {
    if (!window.fetch || !window.history || !window.history.pushState) return;

    const pjaxProgress = document.createElement('div');
    pjaxProgress.id = 'pjax-progress';
    document.body.appendChild(pjaxProgress);

    let isNavigating = false;

    function navigate(url, push = true) {
        if (isNavigating) return;
        isNavigating = true;
        
        pjaxProgress.style.width = '30%';
        pjaxProgress.style.opacity = '1';

        fetch(url)
            .then(r => r.text())
            .then(html => {
                pjaxProgress.style.width = '70%';
                
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const newContentArea = doc.querySelector('.content-area');
                if (!newContentArea) {
                    window.location.href = url; // Fallback jika bukan halaman app
                    return;
                }

                document.title = doc.title;
                const pageTitle = doc.querySelector('.page-title');
                if (pageTitle) document.querySelector('.page-title').innerHTML = pageTitle.innerHTML;
                
                const contentArea = document.querySelector('.content-area');
                contentArea.innerHTML = newContentArea.innerHTML;
                
                // Eksekusi ulang script inline yang ada di dalam contentArea (dibutuhkan untuk halaman dengan custom JS)
                Array.from(contentArea.querySelectorAll('script')).forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
                
                document.querySelectorAll('.sidebar-nav .nav-link').forEach(el => el.classList.remove('active'));
                const newSidebar = doc.querySelectorAll('.sidebar-nav .nav-link');
                newSidebar.forEach((el, i) => {
                    if (el.classList.contains('active')) {
                        const curLink = document.querySelectorAll('.sidebar-nav .nav-link')[i];
                        if (curLink) curLink.classList.add('active');
                    }
                });

                if (typeof initDynamicContent === 'function') {
                    initDynamicContent();
                }

                if (push) {
                    window.history.pushState({}, '', url);
                }
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                pjaxProgress.style.width = '100%';
                setTimeout(() => {
                    pjaxProgress.style.opacity = '0';
                    setTimeout(() => {
                        pjaxProgress.style.width = '0';
                        isNavigating = false;
                    }, 300);
                }, 300);
            })
            .catch(() => {
                window.location.href = url; // Fallback pada error
            });
    }

    document.addEventListener('click', function(e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;
        
        const a = e.target.closest('a');
        if (!a) return;
        
        const url = a.getAttribute('href');
        if (!url || url.startsWith('#') || url.startsWith('javascript:') || url.startsWith('mailto:') || a.target === '_blank') return;
        
        // Exclude specific urls that shouldn't be loaded via AJAX
        if (url.includes('logout.php') || url.includes('download') || url.includes('tcpdf')) return;
        
        if (a.origin !== window.location.origin) return;

        // Skip intercepting login form if we are on login page, though login form isn't a link
        // Skip links that have no-pjax class if we want to be safe
        if (a.classList.contains('no-pjax')) return;

        e.preventDefault();
        navigate(a.href);
    });

    window.addEventListener('popstate', function() {
        navigate(window.location.href, false);
    });
}
