<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'dapodik') {
    header('Location: ../auth/login.php');
    exit;
}

$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($filter_user > 0) {
    $sql_count .= " AND al.user_id = :user_id";
    $sql_data .= " AND al.user_id = :user_id";
    $params[':user_id'] = $filter_user;
}

// Hapus semua log
if (isset($_GET['clear_all']) && $_GET['clear_all'] == 1) {
    $pdo->prepare("DELETE FROM audit_log")->execute();
    logActivity('HAPUS AUDIT LOG', "Menghapus semua audit log");
    $_SESSION['message'] = ['text' => 'Semua log berhasil dihapus.', 'type' => 'success'];
    header('Location: audit_log.php');
    exit;
}

// Filter & Pagination
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$filter_aksi = isset($_GET['filter_aksi']) ? $_GET['filter_aksi'] : '';
$search_desc = isset($_GET['search_desc']) ? trim($_GET['search_desc']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$sql_count = "SELECT COUNT(*) FROM audit_log al LEFT JOIN users u ON al.user_id = u.id WHERE 1=1";
$sql_data = "SELECT al.*, u.name, u.email FROM audit_log al LEFT JOIN users u ON al.user_id = u.id WHERE 1=1";
$params = [];

if ($filter_aksi !== '') {
    $sql_count .= " AND al.aksi = :aksi";
    $sql_data .= " AND al.aksi = :aksi";
    $params[':aksi'] = $filter_aksi;
}
if ($search_desc !== '') {
    $sql_count .= " AND al.deskripsi ILIKE :desc";
    $sql_data .= " AND al.deskripsi ILIKE :desc";
    $params[':desc'] = "%$search_desc%";
}
if ($start_date !== '') {
    $sql_count .= " AND al.created_at >= :start";
    $sql_data .= " AND al.created_at >= :start";
    $params[':start'] = $start_date;
}
if ($end_date !== '') {
    $sql_count .= " AND al.created_at <= :end";
    $sql_data .= " AND al.created_at <= :end";
    $params[':end'] = $end_date . ' 23:59:59';
}

$total = $pdo->prepare($sql_count);
foreach ($params as $k => $v) $total->bindValue($k, $v);
$total->execute();
$total_rows = $total->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql_data .= " ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql_data);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$aksi_list = $pdo->query("SELECT DISTINCT aksi FROM audit_log ORDER BY aksi")->fetchAll();

$message = '';
$msg_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message']['text'];
    $msg_type = $_SESSION['message']['type'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log - Admin TU</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
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
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
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
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
        .btn-primary { background: #1e4a6b; color: white; }
        .btn-primary:hover { background: #0d3550; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 1px solid #e2e8f0; color: #475569; }
        .btn-outline:hover { border-color: #1e4a6b; color: #1e4a6b; }
        
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
        
        .stats-row { margin-bottom: 1.5rem; }
        .total-badge {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            padding: 0.4rem 1rem;
            display: inline-block;
            font-size: 0.8rem;
            color: #475569;
        }
        .total-badge strong { color: #1e4a6b; }
        
        .log-table { width: 100%; border-collapse: collapse; }
        .log-table th {
            text-align: left;
            padding: 0.9rem 0.8rem;
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .log-table td {
            padding: 0.9rem 0.8rem;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 0.8rem;
        }
        .log-table tr:hover { background: #f8fafc; }
        
        .badge-aksi {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-tambah { background: #e0f2e9; color: #1e6f3f; }
        .badge-edit { background: #fff3e0; color: #b76e0b; }
        .badge-hapus { background: #fee2e2; color: #b91c1c; }
        .badge-login { background: #e3f2fd; color: #0b5e7c; }
        .badge-logout { background: #f1f5f9; color: #64748b; }
        .badge-import, .badge-export { background: #e6f3ff; color: #1e4a6b; }
        .badge-kenaikan { background: #ede9fe; color: #6d28d9; }
        
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
        
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 40px;
            margin-bottom: 1rem;
        }
        .alert-success { background: #e0f2e9; color: #1e6f3f; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-header">
        <div class="page-title"><i class="fas fa-clipboard-list" style="color: #1e4a6b;"></i> Audit Log</div>
        
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>


    <div class="filter-bar">
        <form method="GET">
            <div class="filter-grid">
                <div class="filter-field">
                    <label><i class="fas fa-tag"></i> Aksi</label>
                    <select name="filter_aksi">
                        <option value="">Semua Aksi</option>
                        <?php foreach ($aksi_list as $aksi): ?>
                            <option value="<?= htmlspecialchars($aksi['aksi']) ?>" <?= $filter_aksi == $aksi['aksi'] ? 'selected' : '' ?>><?= htmlspecialchars($aksi['aksi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-field">
                    <label><i class="fas fa-user"></i> Pengguna</label>
                    <select name="user_id">
                        <option value="0">Semua Pengguna</option>
                        <?php 
                        $user_list = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
                        foreach ($user_list as $user): 
                        ?>
                            <option value="<?= $user['id'] ?>" <?= ($filter_user ?? 0) == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-calendar-alt"></i> Tanggal Mulai</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-calendar-check"></i> Tanggal Selesai</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
                <a href="audit_log.php" class="btn-reset">
                    <i class="fas fa-undo-alt"></i>
                </a>
            </div>
        </form>
    </div>

    <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow-x: auto;">
        <table class="log-table">
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Deskripsi</th></tr></thead>
            <tbody>
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $log): 
                        $aksi_class = '';
                        $aksi_lower = strtolower(explode(' ', $log['aksi'])[0]);
                        switch ($aksi_lower) {
                            case 'tambah': $aksi_class = 'badge-tambah'; break;
                            case 'edit': $aksi_class = 'badge-edit'; break;
                            case 'hapus': $aksi_class = 'badge-hapus'; break;
                            case 'login': $aksi_class = 'badge-login'; break;
                            case 'logout': $aksi_class = 'badge-logout'; break;
                            case 'import': $aksi_class = 'badge-import'; break;
                            case 'export': $aksi_class = 'badge-export'; break;
                            case 'kenaikan': $aksi_class = 'badge-kenaikan'; break;
                            default: $aksi_class = 'badge-edit';
                        }
                    ?>
                    <tr>
                        <td><span style="font-family: monospace;"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></span></td>
                        <td><?= htmlspecialchars($log['email'] ?? 'Sistem') ?></td>
                        <td><span class="badge-aksi <?= $aksi_class ?>"><?= htmlspecialchars($log['aksi']) ?></span></td>
                        <td style="max-width: 450px;"><?= htmlspecialchars($log['deskripsi']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:2rem; color:#94a3b8;">Tidak ada data log</td></tr>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('clearAllBtn').addEventListener('click', () => {
        Swal.fire({
            title: 'Hapus Semua Log?',
            text: 'Tindakan ini akan menghapus seluruh catatan audit log. Data tidak dapat dipulihkan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Ya, hapus semua!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) window.location.href = '?clear_all=1';
        });
    });
</script>
</body>
</html>