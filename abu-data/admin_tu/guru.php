<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin_tu') {
    header('Location: ../auth/login.php');
    exit;
}

$filter_mapel = isset($_GET['mata_pelajaran']) ? $_GET['mata_pelajaran'] : '';
if ($filter_mapel !== '') {
    $base_sql .= " AND mata_pelajaran = :mapel";
    $params[':mapel'] = $filter_mapel;
}

// Hapus guru
if (isset($_GET['hapus_id']) && is_numeric($_GET['hapus_id'])) {
    $id = (int)$_GET['hapus_id'];
    $stmt = $pdo->prepare("SELECT nama, nip FROM guru WHERE id = ?");
    $stmt->execute([$id]);
    $guru_data = $stmt->fetch();
    
    $cek = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE wali_kelas_id = ?");
    $cek->execute([$id]);
    if ($cek->fetchColumn() > 0) {
        header("Location: guru.php?msg=error_has_kelas");
    } else {
        $pdo->prepare("DELETE FROM guru WHERE id = ?")->execute([$id]);
        if ($guru_data) {
            logActivity('HAPUS GURU', "Menghapus guru {$guru_data['nama']} (NIP: {$guru_data['nip']})");
        }
        header("Location: guru.php?msg=deleted");
    }
    exit;
}

// Tambah/Edit guru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $nama = trim($_POST['nama'] ?? '');
    $nip = trim($_POST['nip'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $mata_pelajaran = trim($_POST['mata_pelajaran'] ?? '');
    $status = $_POST['status'] ?? 'aktif';

    if (empty($nama) || empty($nip)) {
        header("Location: guru.php?msg=error_empty");
        exit;
    }

    if ($action === 'tambah') {
        $cek = $pdo->prepare("SELECT id FROM guru WHERE nip = ?");
        $cek->execute([$nip]);
        if ($cek->fetch()) {
            header("Location: guru.php?msg=error_nip_exists");
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO guru (nama, nip, no_hp, mata_pelajaran, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        if ($stmt->execute([$nama, $nip, $no_hp, $mata_pelajaran, $status])) {
            logActivity('TAMBAH GURU', "Menambah guru: {$nama}, NIP: {$nip}");
            header("Location: guru.php?msg=added");
        } else {
            header("Location: guru.php?msg=error");
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $cek = $pdo->prepare("SELECT id FROM guru WHERE nip = ? AND id != ?");
        $cek->execute([$nip, $id]);
        if ($cek->fetch()) {
            header("Location: guru.php?msg=error_nip_exists");
            exit;
        }
        $stmt = $pdo->prepare("UPDATE guru SET nama=?, nip=?, no_hp=?, mata_pelajaran=?, status=?, updated_at=NOW() WHERE id=?");
        if ($stmt->execute([$nama, $nip, $no_hp, $mata_pelajaran, $status, $id])) {
            logActivity('EDIT GURU', "Mengedit guru ID={$id}");
            header("Location: guru.php?msg=updated");
        } else {
            header("Location: guru.php?msg=error");
        }
    }
    exit;
}

// Hapus semua guru
if (isset($_POST['action']) && $_POST['action'] === 'hapus_semua') {
    $cek = $pdo->query("SELECT COUNT(*) FROM kelas WHERE wali_kelas_id IS NOT NULL")->fetchColumn();
    if ($cek > 0) {
        header("Location: guru.php?msg=error_has_kelas_all");
    } else {
        $pdo->exec("DELETE FROM guru");
        logActivity('HAPUS SEMUA GURU', "Menghapus semua data guru");
        header("Location: guru.php?msg=deleted_all");
    }
    exit;
}

// Replace data dari CSV
if (isset($_POST['action']) && $_POST['action'] === 'replace_data' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');
    $firstRow = true;
    $successCount = 0;
    
    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM guru");
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if ($firstRow) { $firstRow = false; continue; }
            if (count($data) < 5) continue;
            $nama = trim($data[0]);
            $nip = trim($data[1]);
            $no_hp = trim($data[2]);
            $mata_pelajaran = trim($data[3]);
            $status = trim($data[4]);
            if (empty($nama) || empty($nip)) continue;
            if (!in_array($status, ['aktif', 'nonaktif'])) $status = 'aktif';
            
            $stmt = $pdo->prepare("INSERT INTO guru (nama, nip, no_hp, mata_pelajaran, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            if ($stmt->execute([$nama, $nip, $no_hp, $mata_pelajaran, $status])) $successCount++;
        }
        $pdo->commit();
        logActivity('REPLACE GURU', "Replace data guru dari CSV, {$successCount} berhasil");
        header("Location: guru.php?msg=replace_success&count=$successCount");
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: guru.php?msg=error");
    }
    exit;
}

// Filter & Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
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
foreach ($count_params as $k => $v) $stmt_count->bindValue($k, $v);
$stmt_count->execute();
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT * " . $base_sql . " ORDER BY nama ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$guru_list = $stmt->fetchAll();

$total_guru = $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();

$message = '';
$msg_type = '';
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    if ($msg === 'added') { $message = "Guru berhasil ditambahkan."; $msg_type = 'success'; }
    elseif ($msg === 'updated') { $message = "Guru berhasil diperbarui."; $msg_type = 'success'; }
    elseif ($msg === 'deleted') { $message = "Guru berhasil dihapus."; $msg_type = 'success'; }
    elseif ($msg === 'deleted_all') { $message = "Semua guru berhasil dihapus."; $msg_type = 'success'; }
    elseif ($msg === 'replace_success') { $count = $_GET['count'] ?? 0; $message = "Replace data berhasil! {$count} guru diimport."; $msg_type = 'success'; }
    elseif ($msg === 'error_has_kelas') { $message = "Tidak dapat menghapus guru karena masih menjadi wali kelas."; $msg_type = 'danger'; }
    elseif ($msg === 'error_has_kelas_all') { $message = "Tidak dapat menghapus semua guru karena ada yang menjadi wali kelas."; $msg_type = 'danger'; }
    elseif ($msg === 'error_nip_exists') { $message = "NIP sudah terdaftar."; $msg_type = 'danger'; }
    elseif ($msg === 'error_empty') { $message = "Nama dan NIP wajib diisi."; $msg_type = 'danger'; }
    else { $message = "Terjadi kesalahan."; $msg_type = 'danger'; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Guru - Admin TU</title>
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 1px solid #e2e8f0; color: #475569; }
        .btn-outline:hover { border-color: #1e4a6b; color: #1e4a6b; }
        
        .action-bar {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-aktif { background: #e0f2e9; color: #1e6f3f; }
        .status-nonaktif { background: #fee2e2; color: #b91c1c; }
        
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
        .alert-success { background: #e0f2e9; color: #1e6f3f; }
        .alert-danger { background: #fee2e2; color: #b91c1c; }
        
        .info-text { font-size: 0.7rem; color: #64748b; margin-top: 0.5rem; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-header">
        <div class="page-title"><i class="fas fa-chalkboard-user" style="color: #1e4a6b;"></i> Manajemen Guru</div>
        <button class="btn btn-primary" id="btnTambah"><i class="fas fa-plus"></i> Tambah Guru</button>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="action-bar">
        <button class="btn btn-danger" id="btnHapusSemua"><i class="fas fa-trash"></i> Hapus Semua</button>
        <button class="btn btn-warning" id="btnReplace"><i class="fas fa-exchange-alt"></i></button>
    </div>

    

    <div class="filter-bar">
        <form method="GET">
            <div class="filter-grid">
                
                <div class="filter-field">
                    <label><i class="fas fa-toggle-on"></i> Status</label>
                    <select name="status">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?= $filter_status == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $filter_status == 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-chalkboard"></i> Mata Pelajaran</label>
                    <select name="mata_pelajaran">
                        <option value="">Semua Mapel</option>
                        <?php 
                        // Ambil daftar mata pelajaran unik dari database
                        $mapel_list = $pdo->query("SELECT DISTINCT mata_pelajaran FROM guru WHERE mata_pelajaran IS NOT NULL AND mata_pelajaran != '' ORDER BY mata_pelajaran")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($mapel_list as $mapel): 
                        ?>
                            <option value="<?= htmlspecialchars($mapel) ?>" <?= ($filter_mapel ?? '') == $mapel ? 'selected' : '' ?>><?= htmlspecialchars($mapel) ?></option>
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

    <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr><th>Nama</th><th>NIP</th><th>Mata Pelajaran</th><th>No. HP</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($guru_list as $g): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($g['nama']) ?></strong></td>
                    <td><?= htmlspecialchars($g['nip']) ?></td>
                    <td><?= htmlspecialchars($g['mata_pelajaran'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($g['no_hp']) ?></td>
                    <td><span class="status-badge status-<?= $g['status'] ?>"><?= ucfirst($g['status']) ?></span></td>
                    <td class="action-icons">
                        <a href="#" class="edit-btn" data-id="<?= $g['id'] ?>" data-nama="<?= htmlspecialchars($g['nama']) ?>" data-nip="<?= $g['nip'] ?>" data-no_hp="<?= $g['no_hp'] ?>" data-mapel="<?= htmlspecialchars($g['mata_pelajaran'] ?? '') ?>" data-status="<?= $g['status'] ?>"><i class="fas fa-edit"></i></a>
                        <a href="#" class="delete-btn" data-id="<?= $g['id'] ?>" data-nama="<?= htmlspecialchars($g['nama']) ?>"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($guru_list)): ?>
        <div style="text-align: center; padding: 2rem; color: #94a3b8;">Tidak ada data guru</div>
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

<!-- Modal Tambah/Edit -->
<div id="modalGuru" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Tambah Guru</h3>
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="editId" value="0">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Nama Lengkap *</label>
                <input type="text" name="nama" id="nama" required style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">NIP *</label>
                <input type="text" name="nip" id="nip" required style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Mata Pelajaran</label>
                <input type="text" name="mata_pelajaran" id="mapel" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">No. HP</label>
                <input type="text" name="no_hp" id="no_hp" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Status</label>
                <select name="status" id="status" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-outline" id="closeModal">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Replace CSV -->
<div id="modalReplace" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-file-upload"></i> Replace Data Guru</h3>
        <p style="color: #64748b; font-size: 0.8rem;">Upload file CSV. Semua data guru akan dihapus dan diganti dengan data dari CSV.</p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="replace_data">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">File CSV</label>
                <input type="file" name="csv_file" accept=".csv" required style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
            </div>
            <div class="info-text">Format: Nama,NIP,No HP,Mata Pelajaran,Status (Baris pertama header dilewati)</div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Upload & Replace</button>
                <button type="button" class="btn btn-outline" id="closeReplace">Batal</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const modalGuru = document.getElementById('modalGuru');
    const modalReplace = document.getElementById('modalReplace');
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const editId = document.getElementById('editId');
    const nama = document.getElementById('nama');
    const nip = document.getElementById('nip');
    const mapel = document.getElementById('mapel');
    const no_hp = document.getElementById('no_hp');
    const statusSelect = document.getElementById('status');

    function openModal(title, action, id=0, namaVal='', nipVal='', mapelVal='', nohpVal='', statusVal='aktif') {
        modalTitle.innerText = title;
        formAction.value = action;
        editId.value = id;
        nama.value = namaVal;
        nip.value = nipVal;
        mapel.value = mapelVal;
        no_hp.value = nohpVal;
        statusSelect.value = statusVal;
        modalGuru.classList.add('show');
    }

    document.getElementById('btnTambah').onclick = () => openModal('Tambah Guru', 'tambah');
    document.getElementById('closeModal').onclick = () => modalGuru.classList.remove('show');

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            openModal('Edit Guru', 'edit', btn.dataset.id, btn.dataset.nama, btn.dataset.nip, btn.dataset.mapel, btn.dataset.no_hp, btn.dataset.status);
        };
    });

    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            Swal.fire({ title: 'Hapus Guru?', text: `Yakin hapus "${btn.dataset.nama}"?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Hapus' }).then(r => { if(r.isConfirmed) window.location.href = `?hapus_id=${btn.dataset.id}`; });
        };
    });

    document.getElementById('btnHapusSemua').onclick = () => {
        Swal.fire({ title: 'Hapus Semua Guru?', text: 'Tindakan ini tidak dapat dibatalkan!', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Ya, hapus semua!' }).then(r => {
            if(r.isConfirmed) { let form = document.createElement('form'); form.method='POST'; form.innerHTML='<input type="hidden" name="action" value="hapus_semua">'; document.body.appendChild(form); form.submit(); }
        });
    };

    document.getElementById('btnReplace').onclick = () => modalReplace.classList.add('show');
    document.getElementById('closeReplace').onclick = () => modalReplace.classList.remove('show');

    window.onclick = (e) => {
        if (e.target === modalGuru) modalGuru.classList.remove('show');
        if (e.target === modalReplace) modalReplace.classList.remove('show');
    };
</script>
</body>
</html>