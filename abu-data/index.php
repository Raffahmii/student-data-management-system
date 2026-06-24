<?php
session_start();
$loggedIn = isset($_SESSION['user_id']);

$dashboardPath = 'auth/login.php';
if ($loggedIn) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin_tu') $dashboardPath = 'admin_tu/dashboard.php';
    elseif ($role === 'dapodik') $dashboardPath = 'dapodik/dashboard.php';
    elseif ($role === 'wakil_kepala_sekolah') $dashboardPath = 'wakil_kepsek/dashboard.php';
    elseif ($role === 'kepala_sekolah') $dashboardPath = 'kepsek/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abu DataSiswa | Platform Manajemen Data Siswa</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            color: #1e293b;
            overflow-x: hidden;
        }

        /* ========== PRELOADER ========== */
        .preloader {
            position: fixed;
            inset: 0;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        .preloader.hide {
            opacity: 0;
            visibility: hidden;
        }
        .preloader-content {
            text-align: center;
        }
        .preloader-logo {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            animation: pulse 1.2s ease-in-out infinite;
        }
        .preloader-logo i {
            font-size: 1.8rem;
            color: white;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.9; }
        }
        .preloader-text {
            font-size: 0.8rem;
            letter-spacing: 2px;
            color: #94a3b8;
        }
        .preloader-dots {
            display: inline-flex;
            gap: 3px;
        }
        .preloader-dots span {
            width: 5px;
            height: 5px;
            background: #3b82f6;
            border-radius: 50%;
            animation: dotBlink 1.4s infinite;
        }
        .preloader-dots span:nth-child(2) { animation-delay: 0.2s; }
        .preloader-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes dotBlink {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        /* ========== MAIN CONTENT ========== */
        .main {
            opacity: 0;
            transition: opacity 0.6s ease;
        }
        .main.visible {
            opacity: 1;
        }

        .container {
            max-width: 1320px; /* diperbesar */
            margin: 0 auto;
            padding: 0 32px; /* padding lebih lega */
        }

        /* ========== NAVBAR ========== */
        .navbar {
            padding: 24px 0; /* lebih besar */
            position: relative;
            z-index: 100;
            transition: all 0.3s ease;
        }
        .navbar-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-icon i {
            font-size: 1.3rem;
            color: white;
        }
        .logo-text {
            font-weight: 800;
            font-size: 1.4rem;
            color: #0f172a;
        }
        .logo-text span {
            color: #3b82f6;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
        }
        .nav-links a {
            text-decoration: none;
            color: #475569;
            font-weight: 600;
            transition: 0.2s;
            font-size: 1rem;
        }
        .nav-links a:hover {
            color: #3b82f6;
        }
        .btn-login {
            background: #3b82f6;
            color: white !important;
            padding: 10px 24px;
            transition: 0.2s;
            border-radius: 40px;
        }
        .btn-login:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }

        /* ========== HERO ========== */
        .hero {
            padding: 80px 0 100px; /* diperbesar */
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }
        .hero-wrapper {
            display: flex;
            align-items: center;
            gap: 60px;
            flex-wrap: wrap;
        }
        .hero-content {
            flex: 1;
        }
        .hero-content h1 {
            font-size: 3.5rem; /* lebih besar */
            font-weight: 800;
            line-height: 1.2;
            color: #0f172a;
            margin-bottom: 24px;
        }
        .hero-content h1 .highlight {
            color: #3b82f6;
        }
        .hero-content p {
            font-size: 1.1rem;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 32px;
            max-width: 90%;
        }
        .hero-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
            padding: 14px 32px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 40px;
        }
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px -5px rgba(59,130,246,0.4);
        }
        .btn-outline {
            border: 1px solid #cbd5e1;
            background: white;
            color: #1e293b;
            padding: 14px 32px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: 0.2s;
            border-radius: 40px;
        }
        .btn-outline:hover {
            border-color: #3b82f6;
            color: #3b82f6;
            transform: translateY(-2px);
        }
        .hero-stats {
            display: flex;
            gap: 48px;
            margin-top: 48px;
        }
        .stat-item h4 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0f172a;
        }
        .stat-item p {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 6px;
        }
        .hero-image {
            flex: 1;
            position: relative;
        }
        .hero-image img {
            width: 100%;
            border-radius: 32px;
            box-shadow: 0 25px 40px -12px rgba(0,0,0,0.15);
        }

        /* ========== STATS MINI ========== */
        .stats-mini {
            padding: 60px 0; /* diperbesar */
            background: #f1f5f9;
        }
        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            text-align: center;
        }
        .stats-mini-item .number {
            font-size: 2.2rem; /* lebih besar */
            font-weight: 800;
            color: #3b82f6;
        }
        .stats-mini-item .label {
            font-size: 0.9rem;
            color: #475569;
            margin-top: 8px;
            font-weight: 500;
        }

        /* ========== SOLUTIONS ========== */
        .solutions {
            padding: 90px 0; /* diperbesar */
            background: white;
        }
        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }
        .section-header h2 {
            font-size: 2.2rem; /* lebih besar */
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 16px;
        }
        .section-header p {
            color: #475569;
            max-width: 650px;
            margin: 0 auto;
            font-size: 1rem;
        }
        .solutions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px; /* lebih besar */
        }
        .solution-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 36px 28px; /* lebih besar */
            transition: all 0.3s ease;
            border-radius: 20px;
        }
        .solution-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 20px 30px -12px rgba(59,130,246,0.15);
            transform: translateY(-5px);
        }
        .solution-icon {
            width: 60px;
            height: 60px;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            border-radius: 16px;
        }
        .solution-icon i {
            font-size: 1.8rem;
            color: #3b82f6;
        }
        .solution-card h3 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 14px;
        }
        .solution-card p {
            color: #475569;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* ========== FEATURES LIST ========== */
        .features-list {
            padding: 90px 0; /* diperbesar */
            background: #f8fafc;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 35px;
        }
        .feature-item {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            background: white;
            padding: 20px;
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        .feature-item:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.05);
        }
        .feature-icon-small {
            width: 50px;
            height: 50px;
            background: #e0f2fe;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 14px;
        }
        .feature-icon-small i {
            font-size: 1.4rem;
            color: #3b82f6;
        }
        .feature-text h4 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .feature-text p {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
        }

        /* ========== CTA ========== */
        .cta {
            padding: 90px 0; /* diperbesar */
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            text-align: center;
        }
        .cta h2 {
            font-size: 2.4rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .cta p {
            color: #94a3b8;
            margin-bottom: 32px;
            max-width: 650px;
            margin-left: auto;
            margin-right: auto;
            font-size: 1rem;
        }
        .btn-cta {
            background: #3b82f6;
            color: white;
            padding: 14px 38px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
            border-radius: 40px;
            font-size: 1rem;
        }
        .btn-cta:hover {
            background: #1d4ed8;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px -5px rgba(59,130,246,0.5);
        }

        /* ========== FOOTER ========== */
        .footer {
            background: #0f172a;
            color: #94a3b8;
            padding: 70px 0 40px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.5fr;
            gap: 50px;
            margin-bottom: 50px;
        }
        .footer-about p {
            font-size: 0.85rem;
            line-height: 1.6;
            margin: 18px 0;
        }
        .footer-about .social {
            display: flex;
            gap: 18px;
            margin-top: 25px;
        }
        .footer-about .social a {
            color: #94a3b8;
            font-size: 1.1rem;
            transition: 0.2s;
        }
        .footer-about .social a:hover {
            color: #3b82f6;
            transform: translateY(-2px);
        }
        .footer-col h4 {
            color: white;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 24px;
        }
        .footer-col ul {
            list-style: none;
        }
        .footer-col ul li {
            margin-bottom: 14px;
        }
        .footer-col ul li a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.85rem;
            transition: 0.2s;
        }
        .footer-col ul li a:hover {
            color: #3b82f6;
            padding-left: 5px;
        }
        .footer-bottom {
            text-align: center;
            padding-top: 35px;
            border-top: 1px solid #1e293b;
            font-size: 0.75rem;
        }

        /* ========== ANIMASI SCROLL REVEAL ========== */
        .fade-up {
            opacity: 0;
            transform: translateY(35px);
            transition: all 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .fade-up.revealed {
            opacity: 1;
            transform: translateY(0);
        }
        .fade-left {
            opacity: 0;
            transform: translateX(-30px);
            transition: all 0.7s ease;
        }
        .fade-left.revealed {
            opacity: 1;
            transform: translateX(0);
        }
        .fade-right {
            opacity: 0;
            transform: translateX(30px);
            transition: all 0.7s ease;
        }
        .fade-right.revealed {
            opacity: 1;
            transform: translateX(0);
        }
        .scale-in {
            opacity: 0;
            transform: scale(0.92);
            transition: all 0.5s ease;
        }
        .scale-in.revealed {
            opacity: 1;
            transform: scale(1);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1100px) {
            .container {
                max-width: 1140px;
            }
            .hero-content h1 {
                font-size: 2.8rem;
            }
        }
        @media (max-width: 900px) {
            .hero-wrapper {
                flex-direction: column;
            }
            .solutions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 40px;
            }
            .hero-content h1 {
                font-size: 2.4rem;
            }
        }
        @media (max-width: 600px) {
            .container {
                padding: 0 20px;
            }
            .solutions-grid {
                grid-template-columns: 1fr;
            }
            .features-grid {
                grid-template-columns: 1fr;
            }
            .stats-mini-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 25px;
            }
            .hero-stats {
                flex-direction: column;
                gap: 20px;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .footer-about .social {
                justify-content: center;
            }
            .nav-links {
                gap: 20px;
            }
            .hero-content h1 {
                font-size: 2rem;
            }
            .section-header h2 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>

<!-- PRELOADER -->
<div class="preloader" id="preloader">
    <div class="preloader-content">
        <div class="preloader-logo">
            <i class="fas fa-database"></i>
        </div>
        <div class="preloader-text">
            Abu DataSiswa<span class="preloader-dots"><span>.</span><span>.</span><span>.</span></span>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main" id="main">
    <!-- NAVBAR -->
    <div class="container">
        <div class="navbar fade-up" style="transition-delay: 0.1s;">
            <div class="navbar-inner">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-database"></i></div>
                    <div class="logo-text">Abu<span>DataSiswa</span></div>
                </div>
                <div class="nav-links">
                    <a href="#home">Beranda</a>
                    <a href="#solutions">Layanan</a>
                    <a href="#features">Fitur</a>
                    <?php if ($loggedIn): ?>
                        <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn-login">Dashboard</a>
                    <?php else: ?>
                        <a href="auth/login.php" class="btn-login">Masuk</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- HERO SECTION -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-wrapper">
                <div class="hero-content fade-left">
                    <h1>Kelola Data Siswa <span class="highlight">Lebih Mudah</span></h1>
                    <p>Platform sederhana dan aman untuk mengelola data siswa, riwayat akademik, dan laporan real-time.</p>
                    <div class="hero-buttons">
                        <?php if (!$loggedIn): ?>
                            <a href="auth/login.php" class="btn-primary">Mulai Sekarang <i class="fas fa-arrow-right"></i></a>
                            <a href="#solutions" class="btn-outline">Pelajari Lebih</a>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn-primary">Buka Dashboard <i class="fas fa-arrow-right"></i></a>
                        <?php endif; ?>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-item"><h4>10.000+</h4><p>Siswa Terkelola</p></div>
                        <div class="stat-item"><h4>50+</h4><p>Sekolah Mitra</p></div>
                        <div class="stat-item"><h4>99.9%</h4><p>Uptime</p></div>
                    </div>
                </div>
                <div class="hero-image fade-right">
                    <img src="https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=600&h=450&fit=crop" alt="Dashboard Preview">
                </div>
            </div>
        </div>
    </section>

    <!-- STATS MINI -->
    <div class="stats-mini">
        <div class="container">
            <div class="stats-mini-grid">
                <div class="stats-mini-item scale-in"><div class="number" data-target="1500">0</div><div class="label">Total Siswa Aktif</div></div>
                <div class="stats-mini-item scale-in"><div class="number" data-target="86">0</div><div class="label">Tenaga Pendidik</div></div>
                <div class="stats-mini-item scale-in"><div class="number" data-target="120">0</div><div class="label">Kelas Tersedia</div></div>
                <div class="stats-mini-item scale-in"><div class="number" data-target="6">0</div><div class="label">Tahun Berdiri</div></div>
            </div>
        </div>
    </div>

    <!-- SOLUTIONS SECTION -->
    <section class="solutions" id="solutions">
        <div class="container">
            <div class="section-header fade-up">
                <h2>Solusi Lengkap untuk Sekolah</h2>
                <p>Semua fitur yang Anda butuhkan dalam satu platform terintegrasi</p>
            </div>
            <div class="solutions-grid">
                <div class="solution-card fade-up">
                    <div class="solution-icon"><i class="fas fa-user-graduate"></i></div>
                    <h3>Manajemen Data Siswa</h3>
                    <p>Catat semua data siswa, dari NISN, alamat, hingga riwayat akademik dengan rapi dan terstruktur.</p>
                </div>
                <div class="solution-card fade-up" style="transition-delay: 0.1s;">
                    <div class="solution-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Kenaikan Kelas Otomatis</h3>
                    <p>Sistem kenaikan kelas berbasis 6 tangga semester, otomatis memindahkan siswa ke tingkat berikutnya.</p>
                </div>
                <div class="solution-card fade-up" style="transition-delay: 0.2s;">
                    <div class="solution-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>Keamanan Data Terjamin</h3>
                    <p>Audit trail lengkap untuk setiap aktivitas, role-based access control, dan backup data.</p>
                </div>
            </div>
        </div>
    </section>

     <!-- CTA SECTION -->
    <section class="cta">
        <div class="container">
            <div class="fade-up">
                <h2>Siap Mengelola Data Siswa Lebih Mudah?</h2>
                <p>Daftarkan sekolah Anda dan rasakan kemudahan mengelola data siswa dengan Abu DataSiswa.</p>
                <?php if (!$loggedIn): ?>
                    <a href="auth/login.php" class="btn-cta">Login Sekarang <i class="fas fa-arrow-right"></i></a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn-cta">Buka Dashboard <i class="fas fa-arrow-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- FEATURES LIST -->
    <section class="features-list" id="features">
        <div class="container">
            <div class="section-header fade-up">
                <h2>Fitur Unggulan</h2>
                <p>Dukung administrasi sekolah dengan fitur-fitur modern</p>
            </div>
            <div class="features-grid">
                <div class="feature-item fade-up"><div class="feature-icon-small"><i class="fas fa-users"></i></div><div class="feature-text"><h4>Manajemen Siswa</h4><p>Tambah, edit, hapus, kenaikan kelas massal, dan riwayat akademik</p></div></div>
                <div class="feature-item fade-up" style="transition-delay: 0.05s;"><div class="feature-icon-small"><i class="fas fa-school"></i></div><div class="feature-text"><h4>Manajemen Kelas</h4><p>Atur wali kelas, pembagian kelas, filter dan pencarian</p></div></div>
                <div class="feature-item fade-up" style="transition-delay: 0.1s;"><div class="feature-icon-small"><i class="fas fa-chalkboard-user"></i></div><div class="feature-text"><h4>Manajemen Guru</h4><p>Data guru, mata pelajaran, status aktif/nonaktif</p></div></div>
                <div class="feature-item fade-up" style="transition-delay: 0.15s;"><div class="feature-icon-small"><i class="fas fa-history"></i></div><div class="feature-text"><h4>Riwayat Siswa</h4><p>Catatan perpindahan kelas otomatis per semester</p></div></div>
                <div class="feature-item fade-up" style="transition-delay: 0.2s;"><div class="feature-icon-small"><i class="fas fa-chart-pie"></i></div><div class="feature-text"><h4>Dashboard Analitik</h4><p>Statistik real-time, grafik siswa per kelas</p></div></div>
                <div class="feature-item fade-up" style="transition-delay: 0.25s;"><div class="feature-icon-small"><i class="fas fa-file-import"></i></div><div class="feature-text"><h4>Import/Export CSV</h4><p>Migrasi data massal dengan format standar</p></div></div>
                <div class="feature-item fade-up" style="transition-delay: 0.3s;"><div class="feature-icon-small"><i class="fas fa-shield-alt"></i></div><div class="feature-text"><h4>Audit Trail</h4><p>Semua aktivitas terekam untuk keamanan data</p></div></div>
                <div class="feature-item fade-up" style="transition-delay: 0.35s;"><div class="feature-icon-small"><i class="fas fa-user-lock"></i></div><div class="feature-text"><h4>Multi Role Access</h4><p>Admin TU, Dapodik, Wakil Kepsek, Kepala Sekolah</p></div></div>
                <div class="feature-item fade-up" style="transition-delay: 0.4s;"><div class="feature-icon-small"><i class="fas fa-database"></i></div><div class="feature-text"><h4>Laporan Instan</h4><p>Generate laporan data siswa dan export ke CSV</p></div></div>
            </div>
        </div>
    </section>

   

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about fade-up">
                    <div class="logo" style="margin-bottom: 15px;">
                        <div class="logo-icon"><i class="fas fa-database"></i></div>
                        <div class="logo-text" style="color: white;">Abu<span style="color:#3b82f6;">DataSiswa</span></div>
                    </div>
                    <p>Platform manajemen data siswa modern untuk administrasi sekolah yang efisien dan transparan.</p>
                    <div class="social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-col fade-up" style="transition-delay: 0.05s;">
                    <h4>Fitur</h4>
                    <ul>
                        <li><a href="#">Manajemen Siswa</a></li>
                        <li><a href="#">Riwayat Siswa</a></li>
                        <li><a href="#">Import/Export Data</a></li>
                        <li><a href="#">Dashboard Analitik</a></li>
                    </ul>
                </div>
                <div class="footer-col fade-up" style="transition-delay: 0.1s;">
                    <h4>Dukungan</h4>
                    <ul>
                        <li><a href="#">Pusat Bantuan</a></li>
                        <li><a href="#">Dokumentasi</a></li>
                        <li><a href="#">API Reference</a></li>
                        <li><a href="#">Status Sistem</a></li>
                    </ul>
                </div>
                <div class="footer-col fade-up" style="transition-delay: 0.15s;">
                    <h4>Kontak</h4>
                    <ul>
                        <li><i class="fas fa-envelope"></i> info@abudatasiswa.id</li>
                        <li><i class="fas fa-phone-alt"></i> +62 21 1234 5678</li>
                        <li><i class="fas fa-map-marker-alt"></i> Jakarta, Indonesia</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom fade-up">
                <p>&copy; <?= date('Y') ?> Abu DataSiswa. All rights reserved. | <a href="#" style="color:#94a3b8;">Kebijakan Privasi</a> | <a href="#" style="color:#94a3b8;">Ketentuan Layanan</a></p>
            </div>
        </div>
    </footer>
</div>

<script>
    // Preloader
    const preloader = document.getElementById('preloader');
    const main = document.getElementById('main');
    window.addEventListener('load', () => {
        setTimeout(() => {
            preloader.classList.add('hide');
            main.classList.add('visible');
        }, 800);
    });

    // Navbar scroll
    window.addEventListener('scroll', () => {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 30) {
            navbar.style.position = 'sticky';
            navbar.style.top = '0';
            navbar.style.backgroundColor = 'rgba(255,255,255,0.98)';
            navbar.style.backdropFilter = 'blur(8px)';
            navbar.style.borderBottom = '1px solid #e2e8f0';
            navbar.style.padding = '16px 0';
        } else {
            navbar.style.position = 'relative';
            navbar.style.backgroundColor = 'transparent';
            navbar.style.backdropFilter = 'none';
            navbar.style.borderBottom = 'none';
            navbar.style.padding = '24px 0';
        }
    });

    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // Counter animation
    const counters = document.querySelectorAll('.number');
    let counted = false;
    const startCounters = () => {
        if (counted) return;
        counted = true;
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-target'));
            let current = 0;
            const increment = target / 60;
            const updateCounter = () => {
                current += increment;
                if (current < target) {
                    counter.innerText = Math.ceil(current);
                    requestAnimationFrame(updateCounter);
                } else counter.innerText = target;
            };
            updateCounter();
        });
    };
    const statsObserver = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) startCounters();
    }, { threshold: 0.3 });
    statsObserver.observe(document.querySelector('.stats-mini'));

    // Scroll Reveal for all elements with .fade-up, .fade-left, .fade-right, .scale-in
    const revealElements = document.querySelectorAll('.fade-up, .fade-left, .fade-right, .scale-in');
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -20px 0px' });
    revealElements.forEach(el => revealObserver.observe(el));
</script>
</body>
</html>