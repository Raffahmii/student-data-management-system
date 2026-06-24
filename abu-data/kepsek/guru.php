<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'kepala_sekolah') {
    header('Location: ../auth/login.php');
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$base_sql = "FROM guru WHERE 1=1";
$params = [];
$count_params = [];

if ($search !== '') {
    $base_sql .= " AND (nama ILIKE :search OR nip ILIKE :search OR no_hp ILIKE :search OR mata_pelajaran ILIKE :search)";
    $params[':search'] = "%$search%";
    $count_params[':search'] = "%$search%";
}
if ($filter_status !== '') {
    $base_sql .= " AND status = :status";
    $params[':status'] = $filter_status;
    $count_params[':status'] = $filter_status;
}

$count_sql = "SELECT COUNT(*) " . $base_sql;
$stmt_count = $pdo->prepare($count_sql);
foreach ($count_params as $key => $val) $stmt_count->bindValue($key, $val);
$stmt_count->execute();
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT * " . $base_sql . " ORDER BY nama ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) $stmt->bindValue($key, $val);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$guru_list = $stmt->fetchAll();

$total_guru = $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();
$status_list = ['aktif', 'nonaktif'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Guru - Kepala Sekolah</title>
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
        .filter-field select,
        .filter-field input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.85rem;
            background: white;
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
        .status-aktif { background: #e0f2e9; color: #1e6f3f; }
        .status-nonaktif { background: #fee2e2; color: #b91c1c; }
        
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
        
        .footer-note {
            margin-top: 1rem;
            padding: 0.8rem;
            text-align: center;
            font-size: 0.7rem;
            color: #94a3b8;
            background: #f8fafc;
            border-radius: 12px;
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
            .data-table {
                font-size: 0.75rem;
            }
            .data-table th, .data-table td {
                padding: 0.6rem 0.4rem;
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
            <i class="fas fa-chalkboard-user"></i> Data Guru
        </div>
    </div>


    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET">
            <div class="filter-grid">
                
                <div class="filter-field">
                    <label><i class="fas fa-toggle-on"></i> Status</label>
                    <select name="status">
                        <option value="">Semua Status</option>
                        <?php foreach ($status_list as $st): ?>
                            <option value="<?= $st ?>" <?= $filter_status == $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
                <a href="guru.php" class="btn-reset">
                    <i class="fas fa-undo-alt"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabel Guru -->
    <div style="background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th><i class="fas fa-user"></i> Nama</th>
                    <th><i class="fas fa-id-card"></i> NIP</th>
                    <th><i class="fas fa-book"></i> Mata Pelajaran</th>
                    <th><i class="fas fa-phone"></i> No. HP</th>
                    <th><i class="fas fa-chart-simple"></i> Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($guru_list as $g): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($g['nama']) ?></strong></td>
                    <td><?= htmlspecialchars($g['nip']) ?></td>
                    <td><?= htmlspecialchars($g['mata_pelajaran'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($g['no_hp']) ?></td>
                    <td><span class="status-badge status-<?= $g['status'] ?>"><?= ucfirst($g['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($guru_list)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: #94a3b8;">
                        <i class="fas fa-folder-open"></i> Tidak ada data guru
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