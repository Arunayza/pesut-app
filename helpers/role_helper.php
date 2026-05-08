<?php
/**
 * PESUT - Role Helper
 * Konstanta, mapping hierarki, dan fungsi terkait role
 */

// ============================================================
// DAFTAR SEMUA ROLE
// ============================================================
const ALL_ROLES = [
    'admin', 'ketua', 'wakil_ketua', 'sekretaris', 'panitera',
    'panmud_hukum', 'panmud_perkara',
    'kasubbag_ptip', 'kasubbag_umk', 'kasubbag_kpot', 'analis_pk_apbn',
    'hakim', 'panitera_pengganti', 'juru_sita_pengganti',
    'staf_umk', 'staf_kpot', 'staf_ptip',
    'staf_panmud_hukum', 'staf_panmud_perkara',
];

// ============================================================
// LABEL ROLE (untuk tampilan UI)
// ============================================================
const ROLE_LABELS = [
    'admin'               => 'Admin IT',
    'ketua'               => 'Ketua',
    'wakil_ketua'         => 'Wakil Ketua',
    'sekretaris'          => 'Sekretaris',
    'panitera'            => 'Panitera',
    'panmud_hukum'        => 'Panmud Hukum',
    'panmud_perkara'      => 'Panmud Perkara',
    'kasubbag_ptip'       => 'Kasubbag PTIP',
    'kasubbag_umk'        => 'Kasubbag UMK',
    'kasubbag_kpot'       => 'Kasubbag KPOT',
    'analis_pk_apbn'      => 'Analis PK APBN',
    'hakim'               => 'Hakim',
    'panitera_pengganti'  => 'Panitera Pengganti',
    'juru_sita_pengganti' => 'Juru Sita Pengganti',
    'staf_umk'            => 'Staf UMK',
    'staf_kpot'           => 'Staf KPOT',
    'staf_ptip'           => 'Staf PTIP',
    'staf_panmud_hukum'   => 'Staf Panmud Hukum',
    'staf_panmud_perkara' => 'Staf Panmud Perkara',
];

// ============================================================
// JABATAN RESMI (untuk surat/dokumen output)
// ============================================================
const JABATAN_RESMI = [
    'ketua'               => 'Ketua',
    'wakil_ketua'         => 'Wakil Ketua',
    'sekretaris'          => 'Sekretaris',
    'panitera'            => 'Panitera',
    'panmud_hukum'        => 'Panitera Muda Hukum',
    'panmud_perkara'      => 'Panitera Muda Perkara',
    'kasubbag_ptip'       => 'Kasub Bag. Perencanaan, TI, dan Pelaporan',
    'kasubbag_umk'        => 'Kasub Bag. Umum dan Keuangan',
    'kasubbag_kpot'       => 'Kasub Bag. Kepegawaian, Organisasi dan Tata Laksana',
    'hakim'               => 'Hakim',
    'panitera_pengganti'  => 'Panitera Pengganti',
    'juru_sita_pengganti' => 'Juru Sita Pengganti',
];

// ============================================================
// MAPPING: Role → Default Atasan Role
// ============================================================
const ROLE_ATASAN_MAP = [
    'staf_umk'            => 'kasubbag_umk',
    'staf_kpot'           => 'kasubbag_kpot',
    'staf_ptip'           => 'kasubbag_ptip',
    'staf_panmud_hukum'   => 'panmud_hukum',
    'staf_panmud_perkara' => 'panmud_perkara',
    'kasubbag_umk'        => 'sekretaris',
    'kasubbag_kpot'       => 'sekretaris',
    'kasubbag_ptip'       => 'sekretaris',
    'analis_pk_apbn'      => 'sekretaris',
    'panmud_hukum'        => 'panitera',
    'panmud_perkara'      => 'panitera',
    'panitera_pengganti'  => 'panitera',
    'juru_sita_pengganti' => 'panitera',
    'hakim'               => 'ketua',
    'panitera'            => 'ketua',
    'sekretaris'          => 'ketua',
    'wakil_ketua'         => 'ketua',
    // ketua dan admin tidak punya atasan
];

// ============================================================
// ROLE-ROLE PEJABAT STRUKTURAL (bisa TTD/approve pengajuan)
// ============================================================
const ROLE_PEJABAT = [
    'ketua', 'wakil_ketua', 'sekretaris', 'panitera',
    'panmud_hukum', 'panmud_perkara',
    'kasubbag_ptip', 'kasubbag_umk', 'kasubbag_kpot',
];

// ============================================================
// ROLE YANG BISA AKSES HALAMAN KELOLA PENGAJUAN
// = Pejabat struktural + staf kepegawaian (KPOT)
// ============================================================
const ROLE_KELOLA = [
    'ketua', 'wakil_ketua', 'sekretaris', 'panitera',
    'panmud_hukum', 'panmud_perkara',
    'kasubbag_ptip', 'kasubbag_umk', 'kasubbag_kpot',
    'staf_kpot',
];

/**
 * Ambil label role untuk tampilan
 */
function getRoleLabel(string $role): string
{
    return ROLE_LABELS[$role] ?? ucwords(str_replace('_', ' ', $role));
}

/**
 * Ambil jabatan resmi lengkap untuk surat/dokumen
 */
function getJabatanResmi(string $role): string
{
    return JABATAN_RESMI[$role] ?? ucwords(str_replace('_', ' ', $role));
}

/**
 * Cek apakah role adalah pejabat struktural (bisa approve)
 */
function isPejabatStruktural(string $role): bool
{
    return in_array($role, ROLE_PEJABAT);
}

/**
 * Cek apakah user bisa lihat menu Kelola Pengajuan
 * Role di ROLE_KELOLA + siapa saja yang punya bawahan
 */
function canAccessKelolaPengajuan(string $role, PDO $pdo, int $userId): bool
{
    if ($role === 'admin') return true;
    if (in_array($role, ROLE_KELOLA)) return true;

    // Cek apakah punya bawahan
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE atasan_id = ? AND aktif = 1");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Cari default atasan_id berdasarkan role
 * Returns user ID of the default atasan, or null if not found
 */
function getDefaultAtasanId(string $role, PDO $pdo): ?int
{
    $atasanRole = ROLE_ATASAN_MAP[$role] ?? null;
    if (!$atasanRole) return null;

    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND aktif = 1 ORDER BY id ASC LIMIT 1");
    $stmt->execute([$atasanRole]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

/**
 * Cek apakah user berhak sebagai Pejabat Berwenang (TTD2 cuti)
 * Berdasarkan status_pegawai pemohon
 */
function isPejabatBerwenang(string $role, string $statusPegawai): bool
{
    if ($statusPegawai === 'Hakim') {
        // Hakim → Ketua / Wakil Ketua
        return in_array($role, ['ketua', 'wakil_ketua']);
    } elseif ($statusPegawai === 'PNS') {
        // PNS → Ketua / Wakil Ketua
        return in_array($role, ['ketua', 'wakil_ketua']);
    } elseif ($statusPegawai === 'PPPK') {
        // PPPK → Sekretaris / Panitera
        return in_array($role, ['sekretaris', 'panitera']);
    }
    return false;
}

/**
 * Cek apakah user berhak menandatangani pengajuan (untuk izin/pulang cepat — 1 TTD)
 */
function canSignIzin(int $userId, string $role, ?int $atasanId): bool
{
    // Atasan langsung
    if (!empty($atasanId) && $userId == $atasanId) return true;
    // Pejabat struktural
    if (isPejabatStruktural($role)) return true;
    // Admin bisa sign juga (fallback)
    if ($role === 'admin') return true;

    return false;
}

/**
 * Cek apakah user berhak menolak pengajuan
 */
function canReject(int $userId, string $role, ?int $atasanId, string $statusPegawai): bool
{
    // Atasan langsung
    if (!empty($atasanId) && $userId == $atasanId) return true;
    // Pejabat berwenang
    if (isPejabatBerwenang($role, $statusPegawai)) return true;
    // Admin
    if ($role === 'admin') return true;

    return false;
}

/**
 * Ambil role badge HTML
 */
function roleBadge(string $role): string
{
    $label = getRoleLabel($role);

    // Warna berdasarkan level
    $colorMap = [
        'admin'          => 'badge-danger',
        'ketua'          => 'badge-purple',
        'wakil_ketua'    => 'badge-purple',
        'sekretaris'     => 'badge-info',
        'panitera'       => 'badge-info',
        'panmud_hukum'   => 'badge-success',
        'panmud_perkara' => 'badge-success',
        'kasubbag_ptip'  => 'badge-success',
        'kasubbag_umk'   => 'badge-success',
        'kasubbag_kpot'  => 'badge-success',
        'analis_pk_apbn' => 'badge-success',
        'hakim'          => 'badge-orange',
    ];

    $colorClass = $colorMap[$role] ?? 'badge-warning';
    return '<span class="badge ' . $colorClass . '">' . htmlspecialchars($label) . '</span>';
}
