<?php
/**
 * Proses Profil & Ganti Password
 */
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../config/mail.php'; // For email verification

requireLogin();

$userId = $_SESSION['user_id'];
$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';

if ($aksi === 'update_profil') {
    $email = trim($_POST['email'] ?? '');
    $pangkat = trim($_POST['pangkat'] ?? '');
    $status_pegawai = $_POST['status_pegawai'] ?? 'PNS';
    $atasan_id = empty($_POST['atasan_id']) ? NULL : (int)$_POST['atasan_id'];
    $no_telp = trim($_POST['no_telp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    // Cek apakah email sudah dipakai orang lain
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            setFlash('error', 'Email sudah digunakan oleh akun lain!');
            redirect(BASE_URL . '/pages/profil.php');
            exit;
        }
    }

    // Cek apakah email berubah
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldUser = $stmt->fetch();

    $emailBerubah = ($oldUser['email'] !== $email);

    try {
        if ($emailBerubah) {
            // Reset verification status and generate new token
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET email = ?, pangkat = ?, status_pegawai = ?, atasan_id = ?, no_telp = ?, alamat = ?, email_verified_at = NULL, email_verify_token = ? WHERE id = ?");
            $stmt->execute([empty($email) ? NULL : $email, $pangkat, $status_pegawai, $atasan_id, $no_telp, $alamat, $token, $userId]);
            
            if (!empty($email)) {
                // Kirim email verifikasi
                $mailer = getMailer();
                if ($mailer) {
                    $mailer->addAddress($email);
                    $mailer->Subject = 'Verifikasi Email PESUT';
                    $link = 'http://localhost' . BASE_URL . '/pages/verifikasi.php?token=' . $token;
                    $mailer->isHTML(true);
                    $mailer->Body = "Klik link ini untuk verifikasi email Anda: <a href='$link'>$link</a>";
                    $mailer->send();
                    setFlash('success', 'Profil diperbarui. Silakan cek email Anda untuk verifikasi.');
                } else {
                    setFlash('warning', 'Profil diperbarui, namun sistem email belum dikonfigurasi. Link verifikasi Anda: ' . $link);
                }
            } else {
                setFlash('success', 'Profil diperbarui tanpa email.');
            }
        } else {
            $stmt = $pdo->prepare("UPDATE users SET pangkat = ?, status_pegawai = ?, atasan_id = ?, no_telp = ?, alamat = ? WHERE id = ?");
            $stmt->execute([$pangkat, $status_pegawai, $atasan_id, $no_telp, $alamat, $userId]);
            setFlash('success', 'Profil berhasil diperbarui!');
        }
        
        // Update session
        $_SESSION['status_pegawai'] = $status_pegawai;
        
    } catch (Exception $e) {
        setFlash('error', 'Terjadi kesalahan sistem.');
    }
    
    redirect(BASE_URL . '/pages/profil.php');

} elseif ($aksi === 'kirim_verifikasi') {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (empty($user['email'])) {
        setFlash('error', 'Anda belum menambahkan email!');
        redirect(BASE_URL . '/pages/profil.php');
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE users SET email_verify_token = ? WHERE id = ?")->execute([$token, $userId]);

    $mailer = getMailer();
    if ($mailer) {
        try {
            $mailer->addAddress($user['email']);
            $mailer->Subject = 'Verifikasi Email PESUT';
            $link = 'http://localhost' . BASE_URL . '/pages/verifikasi.php?token=' . $token;
            $mailer->isHTML(true);
            $mailer->Body = "Klik link ini untuk verifikasi email Anda: <a href='$link'>$link</a>";
            $mailer->send();
            setFlash('success', 'Link verifikasi berhasil dikirim ulang ke email Anda!');
        } catch (Exception $e) {
            setFlash('error', 'Gagal mengirim email verifikasi.');
        }
    } else {
        $link = 'http://localhost' . BASE_URL . '/pages/verifikasi.php?token=' . $token;
        setFlash('warning', 'Sistem email belum aktif. Gunakan link ini untuk testing lokal: ' . $link);
    }
    redirect(BASE_URL . '/pages/profil.php');

} elseif ($aksi === 'ubah_password_verified') {
    // Pastikan user sudah verifikasi email
    $stmt = $pdo->prepare("SELECT email, email_verified_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (empty($user['email']) || empty($user['email_verified_at'])) {
        setFlash('error', 'Anda harus verifikasi email terlebih dahulu sebelum dapat mengganti password!');
        redirect(BASE_URL . '/pages/profil.php');
        exit;
    }

    $passwordBaru = $_POST['password_baru'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    if (empty($passwordBaru) || empty($konfirmasi)) {
        setFlash('error', 'Semua kolom password wajib diisi!');
        redirect(BASE_URL . '/pages/profil.php');
        exit;
    }

    if ($passwordBaru !== $konfirmasi) {
        setFlash('error', 'Konfirmasi password tidak cocok!');
        redirect(BASE_URL . '/pages/profil.php');
        exit;
    }

    if (strlen($passwordBaru) < 6) {
        setFlash('error', 'Password baru minimal 6 karakter!');
        redirect(BASE_URL . '/pages/profil.php');
        exit;
    }

    // Update password baru tanpa perlu mengecek password lama
    $hashedPassword = password_hash($passwordBaru, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        setFlash('success', 'Password Anda berhasil diperbarui!');
    } catch (Exception $e) {
        setFlash('error', 'Terjadi kesalahan sistem saat memperbarui password.');
    }
    
    redirect(BASE_URL . '/pages/profil.php');
} else {
    redirect(BASE_URL . '/pages/profil.php');
}
?>
