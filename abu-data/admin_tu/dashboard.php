<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin_tu') {
    header('Location: ../auth/login.php');
    exit;
}
if (!isset($_SESSION['tahun_ajaran_id'])) {
    header('Location: ../tahun-ajaran/tahun.php');
    exit;
}
$tahun_aktif_id = (int)$_SESSION['tahun_ajaran_id'];

// Statistik untuk Dapodik
$total_siswa = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE tahun_ajaran_id = ?");
$total_siswa->execute([$tahun_aktif_id]);
$total_siswa = $total_siswa->fetchColumn();

$total_kelas = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE tahun_ajaran_id = ?");
$total_kelas->execute([$tahun_aktif_id]);
$total_kelas = $total_kelas->fetchColumn();

$total_guru = $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();

$total_riwayat = $pdo->prepare("SELECT COUNT(*) FROM riwayat_siswa WHERE tahun_ajaran_id = ?");
$total_riwayat->execute([$tahun_aktif_id]);
$total_riwayat = $total_riwayat->fetchColumn();

// Statistik status siswa
$status_stats = [];
$status_sql = $pdo->prepare("SELECT status, COUNT(*) as total FROM siswa WHERE tahun_ajaran_id = ? GROUP BY status");
$status_sql->execute([$tahun_aktif_id]);
while ($row = $status_sql->fetch()) {
    $status_stats[$row['status']] = $row['total'];
}

// 5 Siswa Terbaru
$siswa_terbaru = $pdo->prepare("SELECT s.id, s.nisn, s.nama, s.tanggal_lahir, s.jenis_kelamin, k.nama_kelas, s.created_at 
    FROM siswa s 
    LEFT JOIN kelas k ON s.kelas_id = k.id 
    WHERE s.tahun_ajaran_id = ? 
    ORDER BY s.id DESC LIMIT 5");
$siswa_terbaru->execute([$tahun_aktif_id]);
$siswa_terbaru = $siswa_terbaru->fetchAll();

// 5 Aktivitas Terbaru
$recent_logs = $pdo->query("SELECT al.*, u.name FROM audit_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 5")->fetchAll();

// Distribusi siswa per kelas
$kelas_stats = $pdo->prepare("SELECT k.nama_kelas, COUNT(s.id) as jumlah 
    FROM kelas k 
    LEFT JOIN siswa s ON s.kelas_id = k.id AND s.tahun_ajaran_id = k.tahun_ajaran_id 
    WHERE k.tahun_ajaran_id = ? 
    GROUP BY k.id 
    ORDER BY k.nama_kelas");
$kelas_stats->execute([$tahun_aktif_id]);
$kelas_stats = $kelas_stats->fetchAll();

$tahun_aktif = $_SESSION['tahun_ajaran'] . ' - ' . $_SESSION['semester'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Dapodik</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: #1e4a6b;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            background: #e6f3ff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .stat-icon i { font-size: 1.5rem; color: #1e4a6b; }
        .stat-value { font-size: 2rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem; }
        .stat-label { color: #64748b; font-size: 0.85rem; font-weight: 500; }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        .dashboard-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .card-header h3 {
            color: #0f172a;
            font-weight: 700;
            font-size: 1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header h3 i { color: #1e4a6b; }
        .card-body { padding: 1.25rem; }
        
        .status-list { list-style: none; padding: 0; margin: 0; }
        .status-list li {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        .status-list li:last-child { border-bottom: none; }
        .status-badge {
            padding: 0.2rem 0.7rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-aktif { background: #e0f2e9; color: #1e6f3f; }
        .badge-lulus { background: #fff3e0; color: #b76e0b; }
        
        .student-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .student-item:last-child { border-bottom: none; }
        .student-info h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #0f172a;
            margin: 0 0 3px 0;
        }
        .student-info p {
            font-size: 0.7rem;
            color: #64748b;
            margin: 0;
        }
        .student-class {
            background: #f1f5f9;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            color: #1e4a6b;
        }
        
        .log-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .log-item:last-child { border-bottom: none; }
        .log-time { font-size: 0.7rem; color: #94a3b8; }
        .log-text { color: #334155; font-size: 0.85rem; margin-top: 0.25rem; }
        
        .kelas-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }
        .kelas-item {
            background: #f8fafc;
            padding: 0.5rem 0.75rem;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .kelas-name {
            font-size: 0.8rem;
            font-weight: 500;
            color: #0f172a;
        }
        .kelas-count {
            font-size: 0.7rem;
            font-weight: 600;
            color: #1e4a6b;
            background: white;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
        }
        .quick-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
        }
        .quick-btn:hover {
            background: #e6f3ff;
            border-color: #1e4a6b;
            transform: translateY(-2px);
        }
        .quick-btn i { font-size: 1.2rem; color: #1e4a6b; display: block; margin-bottom: 0.3rem; }
        .quick-btn span { font-size: 0.75rem; color: #334155; font-weight: 500; }
        
        @media (max-width: 768px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: repeat(2, 1fr); }
            .kelas-list { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?= number_format($total_siswa) ?></div>
            <div class="stat-label">Total Siswa</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-school"></i></div>
            <div class="stat-value"><?= number_format($total_kelas) ?></div>
            <div class="stat-label">Total Kelas</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
            <div class="stat-value"><?= number_format($total_guru) ?></div>
            <div class="stat-label">Total Guru</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-history"></i></div>
            <div class="stat-value"><?= number_format($total_riwayat) ?></div>
            <div class="stat-label">Riwayat Perpindahan</div>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Status Siswa -->
        <div class="dashboard-card">
            <div class="card-header"><h3><i class="fas fa-chart-pie"></i> Status Siswa</h3></div>
            <div class="card-body">
                <ul class="status-list">
                    <li><span><i class="fas fa-circle" style="color:#1e6f3f; font-size:0.6rem;"></i> Aktif</span><strong><?= number_format($status_stats['Aktif'] ?? 0) ?></strong></li>
                    <li><span><i class="fas fa-circle" style="color:#b76e0b; font-size:0.6rem;"></i> Lulus</span><strong><?= number_format($status_stats['Lulus'] ?? 0) ?></strong></li>
                    <li><span><i class="fas fa-circle" style="color:#0b5e7c; font-size:0.6rem;"></i> Dipindahkan</span><strong><?= number_format($status_stats['Dipindahkan'] ?? 0) ?></strong></li>
                    <li><span><i class="fas fa-circle" style="color:#64748b; font-size:0.6rem;"></i> Lainnya</span><strong><?= number_format(($status_stats['Mati'] ?? 0)) ?></strong></li>
                </ul>
            </div>
        </div>

        <!-- Distribusi Kelas -->
        <div class="dashboard-card">
            <div class="card-header"><h3><i class="fas fa-building"></i> Distribusi Kelas</h3></div>
            <div class="card-body">
                <div class="kelas-list">
                    <?php foreach ($kelas_stats as $ks): ?>
                    <div class="kelas-item">
                        <span class="kelas-name"><?= htmlspecialchars($ks['nama_kelas']) ?></span>
                        <span class="kelas-count"><?= $ks['jumlah'] ?> siswa</span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($kelas_stats)): ?>
                    <div class="kelas-item">
                        <span class="kelas-name">Belum ada data kelas</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Siswa Terbaru -->
        <div class="dashboard-card">
            <div class="card-header"><h3><i class="fas fa-user-plus"></i> Siswa Terbaru</h3></div>
            <div class="card-body">
                <?php foreach ($siswa_terbaru as $siswa): ?>
                <div class="student-item">
                    <div class="student-info">
                        <h4><?= htmlspecialchars($siswa['nama']) ?></h4>
                        <p>NISN: <?= htmlspecialchars($siswa['nisn']) ?> | <?= date('d/m/Y', strtotime($siswa['tanggal_lahir'])) ?></p>
                    </div>
                    <div class="student-class"><?= htmlspecialchars($siswa['nama_kelas'] ?? 'Belum ada kelas') ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($siswa_terbaru)): ?>
                <div class="log-text" style="text-align: center; padding: 1rem; color: #94a3b8;">Belum ada data siswa</div>
                <?php endif; ?>
                <a href="siswa.php" style="display: block; text-align: center; margin-top: 1rem; padding: 0.5rem; background: #f8fafc; border-radius: 12px; text-decoration: none; font-size: 0.8rem; color: #1e4a6b; font-weight: 500;">
                    <i class="fas fa-arrow-right"></i> Lihat semua siswa
                </a>
            </div>
        </div>

        <!-- Aktivitas Terbaru -->
        <div class="dashboard-card">
            <div class="card-header"><h3><i class="fas fa-clock"></i> Aktivitas Terbaru</h3></div>
            <div class="card-body">
                <?php foreach ($recent_logs as $log): ?>
                <div class="log-item">
                    <div class="log-time"><i class="far fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></div>
                    <div class="log-text"><strong><?= htmlspecialchars($log['name'] ?? 'Sistem') ?></strong> - <?= htmlspecialchars($log['deskripsi']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($recent_logs)): ?>
                <div class="log-text" style="text-align: center; padding: 1rem; color: #94a3b8;">Belum ada aktivitas</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tahun Ajaran Aktif -->
    <div class="dashboard-card" style="margin-top: 1.5rem;">
        <div class="card-header"><h3><i class="fas fa-calendar-alt"></i> Tahun Ajaran Aktif</h3></div>
        <div class="card-body">
            <div style="font-size: 1.2rem; font-weight: 700; color: #1e4a6b; margin-bottom: 0.5rem;"><?= htmlspecialchars($tahun_aktif) ?></div>
            <p style="color: #64748b; font-size: 0.8rem; margin-bottom: 1rem;">Data yang ditampilkan berdasarkan tahun ajaran ini.</p>
            <a href="tahun_ajaran.php" style="color: #1e4a6b; text-decoration: none; font-size: 0.8rem;"><i class="fas fa-exchange-alt"></i> Ganti tahun ajaran</a>
        </div>
    </div>

    <!-- Aksi Cepat -->
    <div class="dashboard-card" style="margin-top: 1.5rem;">
        <div class="card-header"><h3><i class="fas fa-bolt"></i> Aksi Cepat</h3></div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="siswa.php" class="quick-btn"><i class="fas fa-user-graduate"></i><span>Data Siswa</span></a>
                <a href="riwayat.php" class="quick-btn"><i class="fas fa-history"></i><span>Riwayat</span></a>
                <a href="audit_log.php" class="quick-btn"><i class="fas fa-shield-alt"></i><span>Audit Log</span></a>
                <a href="../tahun-ajaran/tahun.php" class="quick-btn"><i class="fas fa-calendar"></i><span>Tahun Ajaran</span></a>
                <a href="../dapodik/export.php" class="quick-btn"><i class="fas fa-download"></i><span>Export Data</span></a>
                <a href="profile.php" class="quick-btn"><i class="fas fa-user-circle"></i><span>Profil Saya</span></a>
            </div>
        </div>
    </div>

</div>
</body>
</html>