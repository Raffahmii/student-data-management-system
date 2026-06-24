    <?php
    $current_page = basename($_SERVER['PHP_SELF']);
    $user_role = $_SESSION['user_role'] ?? '';
    ?>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-area">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="logo-text">
                    <span>DataSiswa</span>
                </div>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-info">
                <h4><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></h4>
                <span><?= htmlspecialchars($user_role) ?></span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-title">Utama</div>
                <a href="<?= $user_role == 'dapodik' ? '../dapodik/dashboard.php' : 'dashboard.php' ?>" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <?php if ($user_role == 'admin_tu'): ?>
            <div class="nav-section">
                <a href="siswa.php" class="nav-item <?= $current_page == 'siswa.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Siswa</span>
                </a>
                <a href="kelas.php" class="nav-item <?= $current_page == 'kelas.php' ? 'active' : '' ?>">
                    <i class="fas fa-school"></i>
                    <span>Kelas</span>
                </a>
                <a href="guru.php" class="nav-item <?= $current_page == 'guru.php' ? 'active' : '' ?>">
                    <i class="fas fa-chalkboard-user"></i>
                    <span>Guru</span>
                </a>
                
            </div>

            <div class="nav-section">
                
                <a href="riwayat.php" class="nav-item <?= $current_page == 'riwayat.php' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i>
                    <span>Riwayat Siswa</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-title">Sistem</div>
                <a href="audit_log.php" class="nav-item <?= $current_page == 'audit_log.php' ? 'active' : '' ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>Audit Log</span>
                </a>
                <a href="import_export.php" class="nav-item <?= $current_page == 'import_export.php' ? 'active' : '' ?>">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Import/Export</span>
                </a>
                <a href="manajemen_akun.php" class="nav-item <?= $current_page == 'manajemen_akun.php' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>Manajemen Akun</span>
                </a>
            </div>

            <?php elseif ($user_role == 'dapodik'): ?>
            <div class="nav-section">
                
                <a href="../dapodik/siswa.php" class="nav-item <?= $current_page == 'siswa.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Siswa</span>
                </a>
                <a href="import_export.php" class="nav-item <?= $current_page == 'import_export.php' ? 'active' : '' ?>">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Import/Export</span>
                </a>
                
                <a href="../dapodik/riwayat.php" class="nav-item <?= $current_page == 'riwayat.php' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i>
                    <span>Riwayat Siswa</span>
                </a>
                
            </div>
            <div class="nav-section">
                <div class="nav-title">Sistem</div>
                
                <a href="../dapodik/audit_log.php" class="nav-item <?= $current_page == 'audit_log.php' ? 'active' : '' ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>Audit Log</span>
                </a>
            </div>

            <?php elseif ($user_role == 'kepala_sekolah'): ?>
            <div class="nav-section">
                <div class="nav-title">Laporan</div>
                <a href="../kepsek/siswa.php" class="nav-item <?= $current_page == 'siswa.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Data Siswa</span>
                </a>
                <a href="../kepsek/kelas.php" class="nav-item <?= $current_page == 'kelas.php' ? 'active' : '' ?>">
                    <i class="fas fa-school"></i>
                    <span>Data Kelas</span>
                </a>
                <a href="../kepsek/guru.php" class="nav-item <?= $current_page == 'guru.php' ? 'active' : '' ?>">
                    <i class="fas fa-chalkboard-user"></i>
                    <span>Data Guru</span>
                </a>
                <a href="../kepsek/riwayat.php" class="nav-item <?= $current_page == 'riwayat.php' ? 'active' : '' ?>">
                    <i class="fas fa-chalkboard-user"></i>
                    <span>Riwayat Siswa</span>
                </a>
                <a href="../kepsek/export.php" class="nav-item <?= $current_page == 'export.php' ? 'active' : '' ?>">
                    <i class="fas fa-download"></i>
                    <span>Export Data</span>
                </a>
            </div>

            <?php elseif ($user_role == 'wakil_kepala_sekolah'): ?>
            <div class="nav-section">
                <div class="nav-title">Laporan</div>
                <a href="../wakil_kepsek/siswa.php" class="nav-item <?= $current_page == 'siswa.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Data Siswa</span>
                </a>
                <a href="../wakil_kepsek/kelas.php" class="nav-item <?= $current_page == 'kelas.php' ? 'active' : '' ?>">
                    <i class="fas fa-school"></i>
                    <span>Data Kelas</span>
                </a>
                <a href="../wakil_kepsek/riwayat.php" class="nav-item <?= $current_page == 'riwayat.php' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i>
                    <span>Riwayat Siswa</span>
                </a>
                <a href="../wakil_kepsek/export.php" class="nav-item <?= $current_page == 'export.php' ? 'active' : '' ?>">
                    <i class="fas fa-download"></i>
                    <span>Export Data</span>
                </a>
            </div>
            <?php endif; ?>

            <div class="nav-section">
                <div class="nav-title">Tahun Ajaran</div>
                <a href="tahun_ajaran.php" class="nav-item <?= $current_page == 'tahun_ajaran.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i>
                    <span>Tahun Ajaran</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-title">Akun</div>
                <a href="profile.php" class="nav-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i>
                    <span>Profil</span>
                </a>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Keluar</span>
                </a>
            </div>
        </nav>

    </aside>

    <style>
        /* ========== SIDEBAR MODERN (Scrollbar Hidden) ========== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: #94a3b8;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            /* HIDE SCROLLBAR tapi tetap bisa scroll */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
        }
        .sidebar::-webkit-scrollbar {
            display: none; /* Chrome/Safari/Opera */
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo-icon {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 10px;
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
            color: white;
        }
        .logo-text span {
            color: #3b82f6;
        }
        .sidebar-toggle {
            background: rgba(255,255,255,0.05);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #94a3b8;
            transition: 0.2s;
        }
        .sidebar-toggle:hover {
            background: rgba(59,130,246,0.2);
            color: #3b82f6;
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .user-avatar {
            width: 45px;
            height: 45px;
            background: rgba(59,130,246,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .user-avatar i {
            font-size: 1.8rem;
            color: #3b82f6;
        }
        .user-info h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
            margin-bottom: 2px;
        }
        .user-info span {
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem 0;
        }
        .nav-section {
            margin-bottom: 1.5rem;
        }
        .nav-title {
            padding: 0 1.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 0.8rem;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.7rem 1.5rem;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        .nav-item i {
            width: 22px;
            font-size: 1rem;
            text-align: center;
        }
        .nav-item:hover {
            background: rgba(59,130,246,0.1);
            color: #3b82f6;
        }
        .nav-item.active {
            background: rgba(59,130,246,0.15);
            color: #3b82f6;
            border-right: 3px solid #3b82f6;
        }

        .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            font-size: 0.7rem;
            text-align: center;
        }
        .footer-text i {
            margin-right: 5px;
            color: #3b82f6;
        }

        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s;
        }

        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>

    <script>
        const sidebarToggleBtn = document.getElementById('sidebarToggle');
        const sidebarEl = document.querySelector('.sidebar');
        if (sidebarToggleBtn && sidebarEl) {
            sidebarToggleBtn.addEventListener('click', () => {
                sidebarEl.classList.toggle('active');
            });
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && sidebarEl.classList.contains('active')) {
                    if (!sidebarEl.contains(e.target) && !sidebarToggleBtn.contains(e.target)) {
                        sidebarEl.classList.remove('active');
                    }
                }
            });
        }
    </script>