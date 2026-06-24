<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'wakil_kepala_sekolah') {
    header('Location: ../auth/login.php');
    exit;
}
if (!isset($_SESSION['tahun_ajaran_id'])) {
    header('Location: ../tahun-ajaran/tahun.php');
    exit;
}
$tahun_ajaran_aktif = $_SESSION['tahun_ajaran_id'];

$filter_tahun = isset($_GET['tahun_ajaran_id']) ? (int)$_GET['tahun_ajaran_id'] : 0;
if ($filter_tahun > 0) {
    $base_sql .= " AND rs.tahun_ajaran_id = :tahun";
    $params[':tahun'] = $filter_tahun;
}

$filter_wali = isset($_GET['wali_kelas']) ? (int)$_GET['wali_kelas'] : 0;
$filter_jk = isset($_GET['jenis_kelamin']) ? $_GET['jenis_kelamin'] : '';
$filter_kelas = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Query untuk mengambil daftar siswa beserta angkatan pertamanya
$base_sql = "SELECT s.nisn, s.nama, s.jenis_kelamin,
                MIN(ta.tahun) as angkatan_tahun,
                MIN(ta.semester) as angkatan_semester
             FROM siswa s
             JOIN riwayat_siswa rs ON s.id = rs.siswa_id
             JOIN tahun_ajaran ta ON rs.tahun_ajaran_id = ta.id
             LEFT JOIN kelas k ON rs.kelas_id = k.id
             LEFT JOIN guru g ON k.wali_kelas_id = g.id
             WHERE 1=1";
$params = [];

if ($filter_wali > 0) {
    $base_sql .= " AND k.wali_kelas_id = :wali";
    $params[':wali'] = $filter_wali;
}
if ($filter_jk !== '') {
    $base_sql .= " AND s.jenis_kelamin = :jk";
    $params[':jk'] = $filter_jk;
}
if ($filter_kelas > 0) {
    $base_sql .= " AND rs.kelas_id = :kelas";
    $params[':kelas'] = $filter_kelas;
}
if ($search !== '') {
    $base_sql .= " AND (s.nama ILIKE :search OR s.nisn ILIKE :search)";
    $params[':search'] = "%$search%";
}
$base_sql .= " GROUP BY s.nisn, s.nama, s.jenis_kelamin ORDER BY s.nama";

// Count total
$count_sql = "SELECT COUNT(*) FROM ($base_sql) AS sub";
$stmt_count = $pdo->prepare($count_sql);
foreach ($params as $k => $v) $stmt_count->bindValue($k, $v);
$stmt_count->execute();
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Get data dengan pagination
$sql = $base_sql . " LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$siswa_list = $stmt->fetchAll();

$guru_list = $pdo->query("SELECT id, nama FROM guru ORDER BY nama")->fetchAll();
$kelas_list = $pdo->query("SELECT id, nama_kelas FROM kelas WHERE tahun_ajaran_id = $tahun_ajaran_aktif ORDER BY nama_kelas")->fetchAll();
$jk_list = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Siswa - Admin TU</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .page-header { margin-bottom: 1.5rem; }
        .page-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
        
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
        .btn-primary { background: #1e4a6b; color: white; }
        .btn-primary:hover { background: #0d3550; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 1px solid #e2e8f0; color: #475569; }
        .btn-outline:hover { border-color: #1e4a6b; color: #1e4a6b; }
        
        /* Filter Bar Style */
        .filter-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.2rem;
        }
        .filter-field label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.3rem;
            letter-spacing: 0.3px;
        }
        .filter-field label i {
            width: 16px;
            color: #3b82f6;
            margin-right: 4px;
        }
        .filter-field select,
        .filter-field input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #e2e8f0;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            background: white;
        }
        .filter-field select:focus,
        .filter-field input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.8rem;
        }
        .btn-filter {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.2s;
        }
        .btn-filter:hover {
            background: #1d4ed8;
        }
        .btn-reset {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 1.2rem;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.2s;
        }
        .btn-reset:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .filter-actions {
                justify-content: stretch;
            }
            .btn-filter, .btn-reset {
                flex: 1;
                justify-content: center;
            }
        }

        .filter-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .filter-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
        }
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
            font-size: 0.85rem;
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
        }
        .data-table td {
            padding: 0.9rem 0.8rem;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 0.85rem;
        }
        .data-table tr:hover { background: #f8fafc; }
        
        .btn-detail {
            background: #e6f3ff;
            color: #1e4a6b;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-detail:hover { background: #1e4a6b; color: white; }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .pagination a, .pagination span {
            padding: 0.4rem 0.8rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            color: #334155;
            text-decoration: none;
        }
        .pagination .active {
            background: #1e4a6b;
            border-color: #1e4a6b;
            color: white;
        }
        
        .total-card {
            background: #f8fafc;
            border-radius: 30px;
            padding: 0.4rem 1rem;
            display: inline-block;
            margin-bottom: 1rem;
            font-size: 0.8rem;
        }
        .info-badge {
            background: #e0f2e9;
            color: #1e6f3f;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-header">
        <div class="page-title"><i class="fas fa-timeline" style="color: #1e4a6b;"></i> Riwayat Perpindahan Siswa</div>
    </div>

    <div class="filter-bar">
        <form method="GET">
            <div class="filter-grid">
                <div class="filter-field">
                    <label><i class="fas fa-chalkboard-user"></i> Wali Kelas</label>
                    <select name="wali_kelas">
                        <option value="0">Semua Wali Kelas</option>
                        <?php foreach ($guru_list as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $filter_wali == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-venus-mars"></i> Jenis Kelamin</label>
                    <select name="jenis_kelamin">
                        <option value="">Semua</option>
                        <?php foreach ($jk_list as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= $filter_jk == $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-school"></i> Kelas (Riwayat)</label>
                    <select name="kelas_id">
                        <option value="0">Semua Kelas</option>
                        <?php foreach ($kelas_list as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $filter_kelas == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-calendar-alt"></i> Tahun Ajaran</label>
                    <select name="tahun_ajaran_id">
                        <option value="0">Semua Tahun</option>
                        <?php 
                        $tahun_ajaran_list = $pdo->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY tahun DESC, semester DESC")->fetchAll();
                        foreach ($tahun_ajaran_list as $ta): 
                        ?>
                            <option value="<?= $ta['id'] ?>" <?= ($filter_tahun ?? 0) == $ta['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-search"></i> Cari</label>
                    <input type="text" name="search" placeholder="Nama atau NISN..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
                <a href="riwayat.php" class="btn-reset">
                    <i class="fas fa-undo-alt"></i>
                </a>
            </div>
        </form>
    </div>

    <div class="total-card"><i class="fas fa-database"></i> Total Siswa: <strong><?= $total_rows ?></strong></div>

    <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow-x: auto;">
        <table class="data-table">
            <thead><tr><th>NISN</th><th>Nama Siswa</th><th>Jenis Kelamin</th><th>Angkatan Masuk</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($siswa_list as $sw): ?>
                <tr>
                    <td><?= htmlspecialchars($sw['nisn']) ?></td>
                    <td><strong><?= htmlspecialchars($sw['nama']) ?></strong></td>
                    <td><?= $sw['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                    <td><span class="info-badge"><?= $sw['angkatan_tahun'] . ' ' . $sw['angkatan_semester'] ?></span></td>
                    <td><a href="riwayat_detail.php?nisn=<?= urlencode($sw['nisn']) ?>" class="btn-detail"><i class="fas fa-eye"></i> Lihat Riwayat</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($siswa_list)): ?>
                <tr><td colspan="5" style="text-align:center; padding:2rem; color:#94a3b8;">Tidak ada data riwayat</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>">&laquo;</a><?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?= $i == $page ? '<span class="active">'.$i.'</span>' : '<a href="?'.http_build_query(array_merge($_GET, ['page' => $i])).'">'.$i.'</a>' ?>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>">&raquo;</a><?php endif; ?>
    </div>
    <?php endif; ?>
    
</div>
</body>
</html>