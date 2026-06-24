<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'wakil_kepala_sekolah') {
    header('Location: ../auth/login.php');
    exit;
}
if (!isset($_SESSION['tahun_ajaran_id'])) {
    header('Location: tahun_ajaran.php');
    exit;
}
$tahun_ajaran_id = (int)$_SESSION['tahun_ajaran_id'];

// Filter
$filter_kelas = isset($_GET['filter_kelas']) ? (int)$_GET['filter_kelas'] : 0;
$filter_wali = isset($_GET['filter_wali']) ? (int)$_GET['filter_wali'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$base_query = "FROM kelas k 
               LEFT JOIN guru g ON k.wali_kelas_id = g.id 
               LEFT JOIN tahun_ajaran ta ON k.tahun_ajaran_id = ta.id 
               WHERE k.tahun_ajaran_id = :tahun_ajaran_aktif";
$params = [':tahun_ajaran_aktif' => $tahun_ajaran_id];
$count_params = [':tahun_ajaran_aktif' => $tahun_ajaran_id];

if ($filter_kelas > 0) {
    $base_query .= " AND k.id = :filter_kelas";
    $params[':filter_kelas'] = $filter_kelas;
    $count_params[':filter_kelas'] = $filter_kelas;
}
if ($filter_wali > 0) {
    $base_query .= " AND k.wali_kelas_id = :filter_wali";
    $params[':filter_wali'] = $filter_wali;
    $count_params[':filter_wali'] = $filter_wali;
}

$count_sql = "SELECT COUNT(*) " . $base_query;
$stmt_count = $pdo->prepare($count_sql);
foreach ($count_params as $key => $val) $stmt_count->bindValue($key, $val);
$stmt_count->execute();
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT k.*, g.nama as wali_kelas, ta.tahun, ta.semester " . $base_query . " ORDER BY k.nama_kelas LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) $stmt->bindValue($key, $val);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$kelas_list = $stmt->fetchAll();

// Hitung jumlah siswa per kelas
$kelas_count = [];
foreach ($kelas_list as $kls) {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE kelas_id = ? AND tahun_ajaran_id = ? AND status = 'Aktif'");
    $count_stmt->execute([$kls['id'], $tahun_ajaran_id]);
    $kelas_count[$kls['id']] = $count_stmt->fetchColumn();
}

// Data untuk dropdown filter
$guru_list = $pdo->query("SELECT id, nama FROM guru ORDER BY nama")->fetchAll();
$kelas_list_all = $pdo->prepare("SELECT id, nama_kelas FROM kelas WHERE tahun_ajaran_id = ? ORDER BY nama_kelas");
$kelas_list_all->execute([$tahun_ajaran_id]);
$kelas_list_all = $kelas_list_all->fetchAll();

$tahun_aktif = $_SESSION['tahun_ajaran'] . ' - ' . $_SESSION['semester'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kelas - Kepala Sekolah</title>
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
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
        }
        .page-title i {
            color: #1e4a6b;
            margin-right: 0.5rem;
        }
        
        .filter-bar {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.2rem;
        }
        .filter-field label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-field label i {
            width: 16px;
            color: #1e4a6b;
            margin-right: 4px;
        }
        .filter-field select {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.85rem;
            background: white;
            cursor: pointer;
        }
        .filter-field select:focus {
            outline: none;
            border-color: #1e4a6b;
        }
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.8rem;
        }
        .btn-filter {
            background: #1e4a6b;
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.2s;
        }
        .btn-filter:hover {
            background: #0d3550;
            transform: translateY(-2px);
        }
        .btn-reset {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.2s;
        }
        .btn-reset:hover {
            background: #e2e8f0;
            color: #1e293b;
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
            background: #e6f3ff;
            border-radius: 30px;
            padding: 0.4rem 1rem;
            display: inline-block;
            margin-left: 1rem;
            font-size: 0.8rem;
            color: #1e4a6b;
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
        
        .student-count {
            background: #e6f3ff;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: #1e4a6b;
            display: inline-block;
        }
        
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
            font-size: 0.8rem;
        }
        .pagination .active {
            background: #1e4a6b;
            border-color: #1e4a6b;
            color: white;
        }
        
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .filter-actions {
                flex-direction: column;
            }
            .btn-filter, .btn-reset {
                justify-content: center;
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
            <i class="fas fa-school"></i> Data Kelas
        </div>
        <div class="info-badge">
            <i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($tahun_aktif) ?>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET">
            <div class="filter-grid">
                <div class="filter-field">
                    <label><i class="fas fa-building"></i> Nama Kelas</label>
                    <select name="filter_kelas">
                        <option value="0">Semua Kelas</option>
                        <?php foreach ($kelas_list_all as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $filter_kelas == $k['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-chalkboard-user"></i> Wali Kelas</label>
                    <select name="filter_wali">
                        <option value="0">Semua Wali Kelas</option>
                        <?php foreach ($guru_list as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $filter_wali == $g['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="kelas.php" class="btn-reset">
                    <i class="fas fa-undo-alt"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Tabel Kelas -->
    <div style="background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th><i class="fas fa-building"></i> Nama Kelas</th>
                    <th><i class="fas fa-chalkboard-user"></i> Wali Kelas</th>
                    <th><i class="fas fa-users"></i> Jumlah Siswa Aktif</th>
                    <th><i class="fas fa-calendar"></i> Tahun Ajaran</th>
                    <th><i class="fas fa-tag"></i> Semester</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kelas_list as $kls): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($kls['nama_kelas']) ?></strong></td>
                    <td><?= htmlspecialchars($kls['wali_kelas'] ?? '-') ?></td>
                    <td><span class="student-count"><?= number_format($kelas_count[$kls['id']] ?? 0) ?> siswa</span></td>
                    <td><?= htmlspecialchars($kls['tahun'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($kls['semester'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($kelas_list)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: #94a3b8;">
                        <i class="fas fa-folder-open"></i> Tidak ada data kelas
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>">&laquo; Sebelumnya</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?= $i == $page ? '<span class="active">'.$i.'</span>' : '<a href="?'.http_build_query(array_merge($_GET, ['page' => $i])).'">'.$i.'</a>' ?>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>">Selanjutnya &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
</body>
</html>