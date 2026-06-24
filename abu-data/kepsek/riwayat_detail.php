<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'kepala_sekolah') {
    header('Location: ../auth/login.php');
    exit;
}

$nisn = isset($_GET['nisn']) ? $_GET['nisn'] : '';
if (empty($nisn)) {
    header('Location: riwayat.php');
    exit;
}

$stmt = $pdo->prepare("SELECT nama, nisn FROM siswa WHERE nisn = ? LIMIT 1");
$stmt->execute([$nisn]);
$siswa = $stmt->fetch();
if (!$siswa) {
    header('Location: riwayat.php');
    exit;
}

// Ambil semua ID siswa dengan NISN tersebut
$ids_siswa = $pdo->prepare("SELECT id FROM siswa WHERE nisn = ?");
$ids_siswa->execute([$nisn]);
$siswa_ids = $ids_siswa->fetchAll(PDO::FETCH_COLUMN);
if (empty($siswa_ids)) {
    header('Location: riwayat.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($siswa_ids), '?'));
$sql = "SELECT rs.id, ta.tahun, ta.semester, k.nama_kelas, g.nama as wali_kelas, rs.status, rs.created_at
        FROM riwayat_siswa rs
        JOIN tahun_ajaran ta ON rs.tahun_ajaran_id = ta.id
        JOIN kelas k ON rs.kelas_id = k.id AND rs.tahun_ajaran_id = k.tahun_ajaran_id
        LEFT JOIN guru g ON k.wali_kelas_id = g.id
        WHERE rs.siswa_id IN ($placeholders)
        ORDER BY ta.tahun DESC, ta.semester DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($siswa_ids);
$riwayat_list = $stmt->fetchAll();

// Hitung total riwayat
$total_riwayat = count($riwayat_list);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Riwayat - <?= htmlspecialchars($siswa['nama']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .page-title i {
            color: #1e4a6b;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #e2e8f0;
            color: #475569;
        }
        .btn-outline:hover {
            border-color: #1e4a6b;
            color: #1e4a6b;
        }
        
        .info-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .info-item {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .info-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-weight: 700;
            color: #0f172a;
            font-size: 0.9rem;
        }
        
        .stats-mini {
            background: #f8fafc;
            border-radius: 16px;
            padding: 0.8rem 1.2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stats-mini i {
            color: #1e4a6b;
            font-size: 1.2rem;
        }
        .stats-mini span {
            color: #475569;
            font-size: 0.8rem;
        }
        .stats-mini strong {
            color: #0f172a;
            font-size: 1rem;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 0.9rem 0.8rem;
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            padding: 0.9rem 0.8rem;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 0.85rem;
        }
        .data-table tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-Aktif { background: #e0f2e9; color: #1e6f3f; }
        .status-Lulus { background: #fff3e0; color: #b76e0b; }
        .status-Dipindahkan { background: #e3f2fd; color: #0b5e7c; }
        .status-Mati { background: #f1f5f9; color: #475569; }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }
        
        @media (max-width: 768px) {
            .data-table {
                font-size: 0.75rem;
            }
            .data-table th, .data-table td {
                padding: 0.6rem 0.4rem;
            }
            .info-card {
                flex-direction: column;
                gap: 0.8rem;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-header">
        <div class="page-title">
            <i class="fas fa-history"></i>
            <span>Detail Riwayat Akademik</span>
        </div>
        <a href="riwayat.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <!-- Info Siswa -->
    <div class="info-card">
        <div class="info-item">
            <span class="info-label"><i class="fas fa-id-card"></i> NISN</span>
            <span class="info-value"><?= htmlspecialchars($siswa['nisn']) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label"><i class="fas fa-user"></i> Nama Lengkap</span>
            <span class="info-value"><?= htmlspecialchars($siswa['nama']) ?></span>
        </div>
    </div>

    <!-- Statistik Mini -->
    <div class="stats-mini">
        <i class="fas fa-chart-line"></i>
        <span>Total Riwayat Perpindahan / Kenaikan Kelas:</span>
        <strong><?= number_format($total_riwayat) ?></strong>
        <span>kali tercatat</span>
    </div>

    <!-- Tabel Riwayat (Read Only) -->
    <div style="background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th><i class="fas fa-calendar"></i> Tahun Ajaran</th>
                    <th><i class="fas fa-tag"></i> Semester</th>
                    <th><i class="fas fa-school"></i> Kelas</th>
                    <th><i class="fas fa-chalkboard-user"></i> Wali Kelas</th>
                    <th><i class="fas fa-chart-simple"></i> Status</th>
                    <th><i class="fas fa-clock"></i> Tanggal Catat</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total_riwayat > 0): ?>
                    <?php foreach ($riwayat_list as $r): 
                        $status_class = '';
                        switch ($r['status']) {
                            case 'Aktif': $status_class = 'status-Aktif'; break;
                            case 'Lulus': $status_class = 'status-Lulus'; break;
                            case 'Dipindahkan': $status_class = 'status-Dipindahkan'; break;
                            case 'Mati': $status_class = 'status-Mati'; break;
                            default: $status_class = 'status-Aktif';
                        }
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['tahun']) ?></strong></td>
                        <td><?= htmlspecialchars($r['semester']) ?></td>
                        <td><?= htmlspecialchars($r['nama_kelas']) ?></td>
                        <td><?= htmlspecialchars($r['wali_kelas'] ?? '-') ?></td>
                        <td><span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                        <td><?= date('d-m-Y', strtotime($r['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <p>Belum ada riwayat untuk siswa ini</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>



</div>
</body>
</html>