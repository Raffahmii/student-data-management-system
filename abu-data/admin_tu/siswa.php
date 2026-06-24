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

// Kenaikan massal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kenaikan_massal') {
    $kelas_asal_id = (int)$_POST['kelas_asal'];
    $kelas_tujuan_id = (int)$_POST['kelas_tujuan'];
    $tahun_tujuan_id = (int)$_POST['tahun_ajaran_tujuan'];
    
    if ($kelas_asal_id <= 0 || $kelas_tujuan_id <= 0 || $tahun_tujuan_id <= 0) {
        header("Location: siswa.php?msg=error_kenaikan");
        exit;
    }
    
    $siswa_list = $pdo->prepare("SELECT * FROM siswa WHERE kelas_id = ? AND tahun_ajaran_id = ? AND status = 'Aktif'");
    $siswa_list->execute([$kelas_asal_id, $tahun_ajaran_aktif_id]);
    $siswa_rows = $siswa_list->fetchAll();
    
    if (empty($siswa_rows)) {
        header("Location: siswa.php?msg=error_no_siswa");
        exit;
    }
    
    $sukses = 0;
    $pdo->beginTransaction();
    try {
        foreach ($siswa_rows as $siswa) {
            $riwayat = $pdo->prepare("INSERT INTO riwayat_siswa (siswa_id, kelas_id, tahun_ajaran_id, status, created_at) VALUES (?, ?, ?, 'Lulus', NOW())");
            $riwayat->execute([$siswa['id'], $siswa['kelas_id'], $siswa['tahun_ajaran_id']]);
            $update = $pdo->prepare("UPDATE siswa SET status = 'Lulus', updated_at = NOW() WHERE id = ?");
            $update->execute([$siswa['id']]);
            $tangga_baru = ($siswa['tangga'] ?? 0) + 1;
            $insert = $pdo->prepare("INSERT INTO siswa (nisn, nama, tanggal_lahir, jenis_kelamin, alamat, no_hp, kelas_id, tahun_ajaran_id, tangga, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aktif', NOW(), NOW())");
            $insert->execute([
                $siswa['nisn'], $siswa['nama'], $siswa['tanggal_lahir'], $siswa['jenis_kelamin'],
                $siswa['alamat'], $siswa['no_hp'], $kelas_tujuan_id, $tahun_tujuan_id, $tangga_baru
            ]);
            $sukses++;
        }
        $pdo->commit();
        logActivity('KENAIKAN MASSAL', "Menindahkan {$sukses} siswa dari kelas asal ID={$kelas_asal_id}");
        header("Location: siswa.php?msg=kenaikan_success&sukses=$sukses");
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: siswa.php?msg=error");
    }
    exit;
}

// Kenaikan manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kenaikan_manual') {
    $id_siswa = (int)$_POST['id_siswa'];
    $kelas_tujuan = (int)$_POST['kelas_tujuan'];
    $tahun_tujuan = (int)$_POST['tahun_ajaran_tujuan'];
    
    $siswa = $pdo->prepare("SELECT * FROM siswa WHERE id = ? AND status = 'Aktif'");
    $siswa->execute([$id_siswa]);
    $data = $siswa->fetch();
    if (!$data) {
        header("Location: siswa.php?msg=error");
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        $riwayat = $pdo->prepare("INSERT INTO riwayat_siswa (siswa_id, kelas_id, tahun_ajaran_id, status, created_at) VALUES (?, ?, ?, 'Lulus', NOW())");
        $riwayat->execute([$data['id'], $data['kelas_id'], $data['tahun_ajaran_id']]);
        $update = $pdo->prepare("UPDATE siswa SET status = 'Lulus', updated_at = NOW() WHERE id = ?");
        $update->execute([$data['id']]);
        $tangga_baru = ($data['tangga'] ?? 0) + 1;
        $insert = $pdo->prepare("INSERT INTO siswa (nisn, nama, tanggal_lahir, jenis_kelamin, alamat, no_hp, kelas_id, tahun_ajaran_id, tangga, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aktif', NOW(), NOW())");
        $insert->execute([
            $data['nisn'], $data['nama'], $data['tanggal_lahir'], $data['jenis_kelamin'],
            $data['alamat'], $data['no_hp'], $kelas_tujuan, $tahun_tujuan, $tangga_baru
        ]);
        $pdo->commit();
        logActivity('KENAIKAN MANUAL', "Menindahkan siswa {$data['nama']}");
        header("Location: siswa.php?msg=kenaikan_success");
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: siswa.php?msg=error");
    }
    exit;
}

// Luluskan kelas 12
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'luluskan_kelas12') {
    $kelas_id = (int)$_POST['kelas_id'];
    if ($kelas_id > 0) {
        $siswa_list = $pdo->prepare("SELECT * FROM siswa WHERE kelas_id = ? AND tahun_ajaran_id = ? AND status = 'Aktif'");
        $siswa_list->execute([$kelas_id, $tahun_ajaran_aktif_id]);
        $siswa_rows = $siswa_list->fetchAll();
        $pdo->beginTransaction();
        try {
            foreach ($siswa_rows as $siswa) {
                $riwayat = $pdo->prepare("INSERT INTO riwayat_siswa (siswa_id, kelas_id, tahun_ajaran_id, status, created_at) VALUES (?, ?, ?, 'Lulus', NOW())");
                $riwayat->execute([$siswa['id'], $siswa['kelas_id'], $siswa['tahun_ajaran_id']]);
                $update = $pdo->prepare("UPDATE siswa SET status = 'Lulus', updated_at = NOW() WHERE id = ?");
                $update->execute([$siswa['id']]);
            }
            $pdo->commit();
            logActivity('LULUSKAN KELAS 12', "Meluluskan " . count($siswa_rows) . " siswa");
            header("Location: siswa.php?msg=lulus_success");
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: siswa.php?msg=error");
        }
    }
    exit;
}

// Hapus massal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hapus_massal') {
    $kelas_filter = (int)$_POST['kelas_filter'];
    if ($kelas_filter > 0) {
        $stmt = $pdo->prepare("DELETE FROM siswa WHERE kelas_id = ? AND tahun_ajaran_id = ?");
        $stmt->execute([$kelas_filter, $tahun_ajaran_aktif_id]);
        $count = $stmt->rowCount();
        logActivity('HAPUS MASSAL SISWA', "Menghapus {$count} siswa dari kelas ID={$kelas_filter}");
    } else {
        $stmt = $pdo->prepare("DELETE FROM siswa WHERE tahun_ajaran_id = ?");
        $stmt->execute([$tahun_ajaran_aktif_id]);
        $count = $stmt->rowCount();
        logActivity('HAPUS MASSAL SISWA', "Menghapus semua {$count} siswa");
    }
    header("Location: siswa.php?msg=hapus_success&count=$count");
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
$kelas_asal = $pdo->prepare("SELECT DISTINCT k.id, k.nama_kelas FROM kelas k JOIN siswa s ON s.kelas_id = k.id WHERE s.tahun_ajaran_id = ? AND s.status = 'Aktif' ORDER BY k.nama_kelas");
$kelas_asal->execute([$tahun_ajaran_aktif_id]);
$kelas_asal = $kelas_asal->fetchAll();
$all_tahun = $pdo->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY tahun DESC, semester DESC")->fetchAll();
$jk_list = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
$status_list = ['Aktif', 'Lulus', 'Dipindahkan', 'Mati'];
$total_siswa = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE tahun_ajaran_id = ?");
$total_siswa->execute([$tahun_ajaran_aktif_id]);
$total_siswa = $total_siswa->fetchColumn();

// Kelas by TA
$kelas_by_ta = [];
$all_kelas = $pdo->query("SELECT id, nama_kelas, tahun_ajaran_id FROM kelas")->fetchAll();
foreach ($all_kelas as $k) {
    $kelas_by_ta[$k['tahun_ajaran_id']][] = ['id' => $k['id'], 'nama_kelas' => $k['nama_kelas']];
}

$message = '';
$msg_type = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'deleted': $message = "Siswa berhasil dihapus."; $msg_type = 'success'; break;
        case 'kenaikan_success': $sukses = $_GET['sukses'] ?? 1; $message = "Kenaikan berhasil! {$sukses} siswa diproses."; $msg_type = 'success'; break;
        case 'lulus_success': $message = "Siswa kelas 12 berhasil diluluskan."; $msg_type = 'success'; break;
        case 'hapus_success': $count = $_GET['count'] ?? 0; $message = "Berhasil menghapus {$count} siswa."; $msg_type = 'success'; break;
        case 'error_no_siswa': $message = "Tidak ada siswa aktif di kelas asal."; $msg_type = 'warning'; break;
        default: $message = "Operasi berhasil."; $msg_type = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa - Admin TU</title>
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
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
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
        
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-Aktif { background: #e0f2e9; color: #1e6f3f; }
        .status-Lulus { background: #fff3e0; color: #b76e0b; }
        
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
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 500px;
            width: 90%;
            padding: 1.8rem;
        }
        .modal-content h3 { color: #0f172a; margin-bottom: 1rem; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 0.8rem; margin-top: 1.5rem; }
        
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
        <button class="btn btn-warning" id="btnKenaikanMassal"><i class="fas fa-arrow-up"></i></button>
        <button class="btn btn-warning" id="btnLuluskan12"><i class="fas fa-graduation-cap"></i></button>
        <button class="btn btn-danger" id="btnHapusMassal"><i class="fas fa-trash"></i></button>
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
                    <i class="fas fa-search"></i> Tampilkan
                </button>
                <a href="siswa.php" class="btn-reset">
                    <i class="fas fa-undo-alt"></i>
                </a>
            </div>
        </form>
    </div>

    <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr><th>Nama</th><th>NISN</th><th>Kelas</th><th>JK</th><th>Tanggal Lahir</th><th>Status</th><th>Aksi</th></tr>
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
                        <a href="#" class="naikkan-btn" data-id="<?= $sw['id'] ?>" data-nama="<?= htmlspecialchars($sw['nama']) ?>"><i class="fas fa-arrow-up"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($siswa_list)): ?>
        <div style="text-align: center; padding: 2rem; color: #94a3b8;">Tidak ada data siswa</div>
        <?php endif; ?>
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

<!-- Modal Kenaikan Massal -->
<div id="modalKenaikan" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-arrow-up"></i> Kenaikan Massal</h3>
        <form method="POST">
            <input type="hidden" name="action" value="kenaikan_massal">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Kelas Asal</label>
                <select name="kelas_asal" required style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($kelas_asal as $k): ?><option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Tahun Ajaran Tujuan</label>
                <select name="tahun_ajaran_tujuan" id="tahun_tujuan_massal" required style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="">Pilih</option>
                    <?php foreach ($all_tahun as $ta): ?><option value="<?= $ta['id'] ?>"><?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Kelas Tujuan</label>
                <select name="kelas_tujuan" id="kelas_tujuan_massal" required style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="">Pilih tahun ajaran dulu</option>
                </select>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Proses</button>
                <button type="button" class="btn btn-outline" id="closeMassal">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Luluskan Kelas 12 -->
<div id="modalLulus" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-graduation-cap"></i> Luluskan Kelas 12</h3>
        <form method="POST">
            <input type="hidden" name="action" value="luluskan_kelas12">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Pilih Kelas 12</label>
                <select name="kelas_id" required style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="">Pilih</option>
                    <?php foreach ($kelas_aktif as $k): if(strpos($k['nama_kelas'], 'XII') !== false): ?><option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option><?php endif; endforeach; ?>
                </select>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Luluskan</button>
                <button type="button" class="btn btn-outline" id="closeLulus">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Kenaikan Manual -->
<div id="modalManual" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-arrow-up"></i> Kenaikan Manual</h3>
        <form method="POST">
            <input type="hidden" name="action" value="kenaikan_manual">
            <input type="hidden" name="id_siswa" id="manual_id">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Siswa</label>
                <input type="text" id="manual_nama" readonly style="width:100%; padding:0.6rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:40px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Tahun Ajaran Tujuan</label>
                <select name="tahun_ajaran_tujuan" id="tahun_tujuan_manual" required style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="">Pilih</option>
                    <?php foreach ($all_tahun as $ta): ?><option value="<?= $ta['id'] ?>"><?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Kelas Tujuan</label>
                <select name="kelas_tujuan" id="kelas_tujuan_manual" required style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="">Pilih tahun ajaran dulu</option>
                </select>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Naikkan</button>
                <button type="button" class="btn btn-outline" id="closeManual">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus Massal -->
<div id="modalHapus" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-trash"></i> Hapus Massal</h3>
        <form method="POST">
            <input type="hidden" name="action" value="hapus_massal">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Filter Kelas (kosongkan untuk semua)</label>
                <select name="kelas_filter" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="0">-- Semua Kelas --</option>
                    <?php foreach ($kelas_aktif as $k): ?><option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-danger">Hapus</button>
                <button type="button" class="btn btn-outline" id="closeHapus">Batal</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const kelasByTA = <?php echo json_encode($kelas_by_ta); ?>;
    
    function populateKelas(selectId, tahunId, selectedId = null) {
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Pilih</option>';
        const list = kelasByTA[tahunId] || [];
        list.forEach(k => {
            const opt = document.createElement('option');
            opt.value = k.id;
            opt.textContent = k.nama_kelas;
            if (selectedId == k.id) opt.selected = true;
            select.appendChild(opt);
        });
    }

    document.getElementById('tahun_tujuan_massal')?.addEventListener('change', function() {
        if (this.value) populateKelas('kelas_tujuan_massal', this.value);
    });
    document.getElementById('tahun_tujuan_manual')?.addEventListener('change', function() {
        if (this.value) populateKelas('kelas_tujuan_manual', this.value);
    });

    const modalK = document.getElementById('modalKenaikan');
    const modalL = document.getElementById('modalLulus');
    const modalM = document.getElementById('modalManual');
    const modalH = document.getElementById('modalHapus');

    document.getElementById('btnKenaikanMassal').onclick = () => modalK.classList.add('show');
    document.getElementById('closeMassal').onclick = () => modalK.classList.remove('show');
    document.getElementById('btnLuluskan12').onclick = () => modalL.classList.add('show');
    document.getElementById('closeLulus').onclick = () => modalL.classList.remove('show');
    document.getElementById('btnHapusMassal').onclick = () => modalH.classList.add('show');
    document.getElementById('closeHapus').onclick = () => modalH.classList.remove('show');
    document.getElementById('closeManual').onclick = () => modalM.classList.remove('show');

    document.querySelectorAll('.naikkan-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            document.getElementById('manual_id').value = btn.dataset.id;
            document.getElementById('manual_nama').value = btn.dataset.nama;
            modalM.classList.add('show');
        };
    });

    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            Swal.fire({ title: 'Hapus Siswa?', text: `Yakin hapus "${btn.dataset.nama}"?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Hapus' }).then(r => { if(r.isConfirmed) window.location.href = `?hapus_id=${btn.dataset.id}`; });
        };
    });

    window.onclick = (e) => {
        if (e.target === modalK) modalK.classList.remove('show');
        if (e.target === modalL) modalL.classList.remove('show');
        if (e.target === modalM) modalM.classList.remove('show');
        if (e.target === modalH) modalH.classList.remove('show');
    };
</script>
</body>
</html>