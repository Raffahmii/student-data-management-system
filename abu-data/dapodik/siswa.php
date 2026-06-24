<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'dapodik') {
    header('Location: ../auth/login.php');
    exit;
}
if (!isset($_SESSION['tahun_ajaran_id'])) {
    header('Location: ../tahun-ajaran/tahun.php');
    exit;
}
$tahun_ajaran_aktif_id = (int)$_SESSION['tahun_ajaran_id'];
$tahun_ajaran_aktif_nama = $_SESSION['tahun_ajaran'] . ' - ' . $_SESSION['semester'];

// Hapus siswa
if (isset($_GET['hapus_id']) && is_numeric($_GET['hapus_id'])) {
    $id = (int)$_GET['hapus_id'];
    $stmt = $pdo->prepare("SELECT nama, nisn FROM siswa WHERE id = ?");
    $stmt->execute([$id]);
    $siswa_data = $stmt->fetch();
    $pdo->prepare("DELETE FROM siswa WHERE id = ?")->execute([$id]);
    if ($siswa_data) {
        logActivity('HAPUS SISWA', "Menghapus siswa {$siswa_data['nama']} (NISN: {$siswa_data['nisn']})");
    }
    header("Location: siswa.php?msg=deleted");
    exit;
}

// Filter & Pagination
$selected_wali = isset($_GET['wali_kelas']) ? (int)$_GET['wali_kelas'] : 0;
$selected_jk = isset($_GET['jenis_kelamin']) ? $_GET['jenis_kelamin'] : '';
$selected_kelas = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$selected_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$base_query = "FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.tahun_ajaran_id = :ta_id";
$params = [':ta_id' => $tahun_ajaran_aktif_id];
$count_params = [':ta_id' => $tahun_ajaran_aktif_id];

if ($selected_wali > 0) {
    $base_query .= " AND k.wali_kelas_id = :wali";
    $params[':wali'] = $selected_wali;
    $count_params[':wali'] = $selected_wali;
}
if ($selected_jk !== '') {
    $base_query .= " AND s.jenis_kelamin = :jk";
    $params[':jk'] = $selected_jk;
    $count_params[':jk'] = $selected_jk;
}
if ($selected_kelas > 0) {
    $base_query .= " AND s.kelas_id = :kelas";
    $params[':kelas'] = $selected_kelas;
    $count_params[':kelas'] = $selected_kelas;
}
if ($selected_status !== '') {
    $base_query .= " AND s.status = :status";
    $params[':status'] = $selected_status;
    $count_params[':status'] = $selected_status;
}
if ($search !== '') {
    $base_query .= " AND (s.nama ILIKE :search OR s.nisn ILIKE :search)";
    $params[':search'] = "%$search%";
    $count_params[':search'] = "%$search%";
}

$count_sql = "SELECT COUNT(*) " . $base_query;
$stmt_count = $pdo->prepare($count_sql);
foreach ($count_params as $k => $v) $stmt_count->bindValue($k, $v);
$stmt_count->execute();
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT s.*, k.nama_kelas " . $base_query . " ORDER BY s.nama ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$siswa_list = $stmt->fetchAll();

// Data dropdown
$guru_list = $pdo->query("SELECT id, nama FROM guru ORDER BY nama")->fetchAll();
$kelas_aktif = $pdo->query("SELECT id, nama_kelas FROM kelas WHERE tahun_ajaran_id = $tahun_ajaran_aktif_id ORDER BY nama_kelas")->fetchAll();
$jk_list = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
$status_list = ['Aktif', 'Lulus', 'Dipindahkan', 'Mati'];
$total_siswa = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE tahun_ajaran_id = ?");
$total_siswa->execute([$tahun_ajaran_aktif_id]);
$total_siswa = $total_siswa->fetchColumn();

$message = '';
$msg_type = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'deleted': $message = "Siswa berhasil dihapus."; $msg_type = 'success'; break;
        case 'added': $message = "Siswa berhasil ditambahkan."; $msg_type = 'success'; break;
        case 'updated': $message = "Siswa berhasil diperbarui."; $msg_type = 'success'; break;
        default: $message = "Operasi berhasil."; $msg_type = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa - Dapodik</title>
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
        .btn-outline { background: transparent; border: 1px solid #e2e8f0; color: #334155; }
        .btn-outline:hover { border-color: #1e4a6b; color: #1e4a6b; }
        
        .action-bar {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        /* Filter Bar Style */
        .filter-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
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
            text-transform: uppercase;
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
            border-radius: 40px;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            background: white;
        }
        .filter-field select:focus,
        .filter-field input:focus {
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
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.2s;
        }
        .btn-filter:hover {
            background: #0d3550;
        }
        .btn-reset {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
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
        .status-Mati { background: #f1f5f9; color: #64748b; }
        
        .action-icons a {
            color: #94a3b8;
            margin: 0 4px;
        }
        .action-icons a:hover { color: #1e4a6b; }
        
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
        
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 40px;
            margin-bottom: 1rem;
        }
        .alert-success { background: #e0f2e9; color: #1e6f3f; border-left: 3px solid #1e6f3f; }
        .alert-warning { background: #fff3e0; color: #b76e0b; border-left: 3px solid #b76e0b; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-header">
        <div class="page-title"><i class="fas fa-users" style="color: #1e4a6b;"></i> Data Siswa</div>
        
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="action-bar">
        <a href="siswa_tambah.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Siswa</a>
    </div>

    <div class="filter-bar">
        <form method="GET">
            <div class="filter-grid">
                <div class="filter-field">
                    <label><i class="fas fa-chalkboard-user"></i> Wali Kelas</label>
                    <select name="wali_kelas">
                        <option value="0">Semua Wali Kelas</option>
                        <?php foreach ($guru_list as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $selected_wali == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-venus-mars"></i> Jenis Kelamin</label>
                    <select name="jenis_kelamin">
                        <option value="">Semua</option>
                        <?php foreach ($jk_list as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= $selected_jk == $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-school"></i> Kelas</label>
                    <select name="kelas_id">
                        <option value="0">Semua Kelas</option>
                        <?php foreach ($kelas_aktif as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $selected_kelas == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-tag"></i> Status</label>
                    <select name="status">
                        <option value="">Semua Status</option>
                        <?php foreach ($status_list as $st): ?>
                            <option value="<?= $st ?>" <?= $selected_status == $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Tampilkan
                </button>
                <a href="siswa.php" class="btn-reset">
                    <i class="fas fa-undo-alt"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>NISN</th>
                    <th>Kelas</th>
                    <th>JK</th>
                    <th>Tanggal Lahir</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($siswa_list as $sw): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($sw['nama']) ?></strong></td>
                    <td><?= htmlspecialchars($sw['nisn']) ?></td>
                    <td><?= htmlspecialchars($sw['nama_kelas'] ?? '-') ?></td>
                    <td><?= $sw['jenis_kelamin'] == 'L' ? 'Laki' : 'Perempuan' ?></td>
                    <td><?= date('d-m-Y', strtotime($sw['tanggal_lahir'])) ?></td>
                    <td><span class="status-badge status-<?= $sw['status'] ?>"><?= $sw['status'] ?></span></td>
                    <td class="action-icons">
                        <a href="siswa_edit.php?id=<?= $sw['id'] ?>"><i class="fas fa-edit"></i></a>
                        <a href="#" class="delete-btn" data-id="<?= $sw['id'] ?>" data-nama="<?= htmlspecialchars($sw['nama']) ?>"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($siswa_list)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: #94a3b8;">
                        <i class="fas fa-inbox"></i> Tidak ada data siswa
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>">&laquo;</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?= $i == $page ? '<span class="active">'.$i.'</span>' : '<a href="?'.http_build_query(array_merge($_GET, ['page' => $i])).'">'.$i.'</a>' ?>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>">&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            Swal.fire({ 
                title: 'Hapus Siswa?', 
                text: `Yakin hapus "${btn.dataset.nama}"?`, 
                icon: 'warning', 
                showCancelButton: true, 
                confirmButtonColor: '#ef4444', 
                confirmButtonText: 'Hapus' 
            }).then(r => { 
                if(r.isConfirmed) window.location.href = `?hapus_id=${btn.dataset.id}`; 
            });
        };
    });
</script>
</body>
</html>