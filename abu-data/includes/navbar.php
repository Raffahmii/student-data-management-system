<?php
$user_name = $_SESSION['user_name'] ?? 'Admin';
$user_role = $_SESSION['user_role'] ?? '';
$user_foto = $_SESSION['user_foto'] ?? 'default-avatar.png';

// Tentukan judul halaman berdasarkan file yang sedang dibuka
$current_file = basename($_SERVER['PHP_SELF']);
$page_title = 'Dashboard';
$page_subtitle = 'Kelola data siswa dan administrasi sekolah';

$page_mapping = [
    'dashboard.php' => ['Dashboard', 'Ringkasan data dan statistik sekolah'],
    'siswa.php' => ['Manajemen Siswa', 'Tambah, edit, hapus, dan kelola data siswa'],
    'kelas.php' => ['Manajemen Kelas', 'Kelola data kelas dan wali kelas'],
    'guru.php' => ['Manajemen Guru', 'Kelola data guru dan mata pelajaran'],
    'tahun_ajaran.php' => ['Tahun Ajaran', 'Kelola tahun ajaran dan semester'],
    'riwayat.php' => ['Riwayat Siswa', 'Lihat riwayat akademik siswa'],
    'riwayat_detail.php' => ['Detail Riwayat', 'Riwayat lengkap siswa'],
    'audit_log.php' => ['Audit Log', 'Catatan semua aktivitas pengguna'],
    'import_export.php' => ['Import / Export', 'Import dan export data CSV'],
    'manajemen_akun.php' => ['Manajemen Akun', 'Kelola akun pengguna sistem'],
    'profile.php' => ['Profil Saya', 'Kelola informasi profil Anda'],
];

if (isset($page_mapping[$current_file])) {
    $page_title = $page_mapping[$current_file][0];
    $page_subtitle = $page_mapping[$current_file][1];
}

$foto_path = '../uploads/' . $user_foto;
$use_fa_placeholder = (!file_exists($foto_path) || $user_foto == 'default-avatar.png');
?>
<header class="navbar">
    <div class="navbar-left">
        <div class="logo-area">
            <div class="logo-icon">
                <i class="fas fa-database"></i>
            </div>
            <div class="logo-text">
                Abu<span>Data</span>
            </div>
        </div>
        <div class="page-info">
            <h1><?= htmlspecialchars($page_title) ?></h1>
            <p><?= htmlspecialchars($page_subtitle) ?></p>
        </div>
    </div>
    <div class="navbar-right">
        <div class="profile-menu" id="profileMenu">
            <?php if ($use_fa_placeholder): ?>
                <div class="profile-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
            <?php else: ?>
                <img src="<?= $foto_path ?>" alt="Profile" class="profile-avatar-img">
            <?php endif; ?>
            <span class="profile-name"><?= htmlspecialchars(explode(' ', $user_name)[0]) ?></span>
            <i class="fas fa-chevron-down profile-caret"></i>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="profile.php">
                    <i class="fas fa-user"></i>
                    <span>Profil Saya</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Keluar</span>
                </a>
            </div>
        </div>
    </div>
</header>

<style>
    /* ========== NAVBAR ========== */
    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 2rem;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        background: transparent;
    }

    /* Bagian Kiri */
    .navbar-left {
        display: flex;
        align-items: flex-end;
        gap: 2rem;
        flex-wrap: wrap;
    }

    /* Logo */
    .logo-area {
        display: flex;
        align-items: center;
        gap: 8px;
        padding-right: 2rem;
        border-right: 1px solid #e2e8f0;
    }
    .logo-icon {
        width: 38px;
        height: 38px;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .logo-icon i {
        font-size: 1.2rem;
        color: white;
    }
    .logo-text {
        font-weight: 700;
        font-size: 1.2rem;
        color: #0f172a;
    }
    .logo-text span {
        color: #3b82f6;
    }

    /* Page Info (Judul Halaman) */
    .page-info h1 {
        font-size: 1.3rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        line-height: 1.3;
    }
    .page-info p {
        font-size: 0.7rem;
        color: #64748b;
        margin: 2px 0 0;
    }

    /* Bagian Kanan - Profile */
    .navbar-right {
        position: relative;
    }
    .profile-menu {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        padding: 6px 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
    }
    .profile-menu:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }
    .profile-avatar {
        width: 34px;
        height: 34px;
        background: #e0f2fe;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #3b82f6;
        font-size: 1.4rem;
    }
    .profile-avatar-img {
        width: 34px;
        height: 34px;
        object-fit: cover;
    }
    .profile-name {
        font-weight: 500;
        color: #1e293b;
        font-size: 0.85rem;
    }
    .profile-caret {
        font-size: 0.7rem;
        color: #94a3b8;
        transition: 0.2s;
    }
    .profile-menu:hover .profile-caret {
        color: #3b82f6;
    }

    /* Dropdown */
    .profile-dropdown {
        position: absolute;
        top: 52px;
        right: 0;
        background: white;
        width: 200px;
        display: none;
        flex-direction: column;
        box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
        z-index: 200;
    }
    .profile-dropdown a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        color: #1e293b;
        text-decoration: none;
        transition: 0.2s;
        font-size: 0.85rem;
    }
    .profile-dropdown a i {
        width: 20px;
        color: #64748b;
        font-size: 1rem;
    }
    .profile-dropdown a:hover {
        background: #f8fafc;
        color: #3b82f6;
    }
    .profile-dropdown a:hover i {
        color: #3b82f6;
    }
    .dropdown-divider {
        height: 1px;
        background: #e2e8f0;
        margin: 4px 0;
    }
    .profile-dropdown.show {
        display: flex;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .navbar {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
        }
        .navbar-left {
            width: 100%;
            justify-content: space-between;
            align-items: center;
        }
        .logo-area {
            border-right: none;
            padding-right: 0;
        }
        .page-info p {
            display: none;
        }
        .profile-name {
            display: none;
        }
    }
</style>

<script>
    const profileMenu = document.getElementById('profileMenu');
    const profileDropdown = document.getElementById('profileDropdown');
    
    if (profileMenu && profileDropdown) {
        profileMenu.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', () => {
            profileDropdown.classList.remove('show');
        });
    }
</script>