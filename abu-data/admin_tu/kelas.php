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

// Hapus kelas
if (isset($_GET['hapus_id']) && is_numeric($_GET['hapus_id'])) {
    $id = (int)$_GET['hapus_id'];
    $stmt = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
    $stmt->execute([$id]);
    $kelas_data = $stmt->fetch();
    $pdo->prepare("DELETE FROM kelas WHERE id = ?")->execute([$id]);
    if ($kelas_data) {
        logActivity('HAPUS KELAS', "Menghapus kelas {$kelas_data['nama_kelas']}");
    }
    header("Location: kelas.php?msg=deleted");
    exit;
}

// Tambah/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $nama_kelas = trim($_POST['nama_kelas'] ?? '');
    $wali_kelas_id = (int)($_POST['wali_kelas_id'] ?? 0);
    $tahun_ajaran_id = (int)($_POST['tahun_ajaran_id'] ?? 0);
    $tingkat = $_POST['tingkat'] ?? 'X';

    if (empty($nama_kelas) || $tahun_ajaran_id <= 0) {
        header("Location: kelas.php?msg=error");
        exit;
    }

    if ($action === 'tambah') {
        $stmt = $pdo->prepare("INSERT INTO kelas (nama_kelas, wali_kelas_id, tahun_ajaran_id, tingkat, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        if ($stmt->execute([$nama_kelas, $wali_kelas_id ?: null, $tahun_ajaran_id, $tingkat])) {
            logActivity('TAMBAH KELAS', "Menambah kelas: {$nama_kelas}");
            header("Location: kelas.php?msg=added");
        } else {
            header("Location: kelas.php?msg=error");
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE kelas SET nama_kelas=?, wali_kelas_id=?, tahun_ajaran_id=?, tingkat=?, updated_at=NOW() WHERE id=?");
        if ($stmt->execute([$nama_kelas, $wali_kelas_id ?: null, $tahun_ajaran_id, $tingkat, $id])) {
            logActivity('EDIT KELAS', "Mengedit kelas ID={$id}");
            header("Location: kelas.php?msg=updated");
        } else {
            header("Location: kelas.php?msg=error");
        }
    }
    exit;
}

// Filter & Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_tingkat = isset($_GET['tingkat']) ? $_GET['tingkat'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$base_query = "FROM kelas k LEFT JOIN guru g ON k.wali_kelas_id = g.id WHERE k.tahun_ajaran_id = :ta_id";
$params = [':ta_id' => $tahun_ajaran_aktif_id];
$count_params = [':ta_id' => $tahun_ajaran_aktif_id];

if ($search !== '') {
    $base_query .= " AND (k.nama_kelas ILIKE :search OR g.nama ILIKE :search)";
    $params[':search'] = "%$search%";
    $count_params[':search'] = "%$search%";
}
if ($filter_tingkat !== '') {
    $base_query .= " AND k.tingkat = :tingkat";
    $params[':tingkat'] = $filter_tingkat;
    $count_params[':tingkat'] = $filter_tingkat;
}

$count_sql = "SELECT COUNT(*) " . $base_query;
$stmt_count = $pdo->prepare($count_sql);
foreach ($count_params as $k => $v) $stmt_count->bindValue($k, $v);
$stmt_count->execute();
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT k.*, g.nama as wali_kelas " . $base_query . " ORDER BY k.nama_kelas LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$kelas_list = $stmt->fetchAll();

$guru_list = $pdo->query("SELECT id, nama FROM guru ORDER BY nama")->fetchAll();
$tahun_list = $pdo->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY tahun DESC")->fetchAll();
$tingkat_list = ['X', 'XI', 'XII'];

$filter_wali = isset($_GET['wali_kelas']) ? (int)$_GET['wali_kelas'] : 0;
$guru_list = $pdo->query("SELECT id, nama FROM guru ORDER BY nama")->fetchAll();
if ($filter_wali > 0) {
    $base_query .= " AND k.wali_kelas_id = :filter_wali";
    $params[':filter_wali'] = $filter_wali;
}

$message = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added': $message = "Kelas berhasil ditambahkan."; break;
        case 'updated': $message = "Kelas berhasil diperbarui."; break;
        case 'deleted': $message = "Kelas berhasil dihapus."; break;
        default: $message = "Terjadi kesalahan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kelas - Admin TU</title>
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
            background: #e0f2e9;
            color: #1e6f3f;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-header">
        <div class="page-title"><i class="fas fa-building" style="color: #1e4a6b;"></i> Manajemen Kelas</div>
        <button class="btn btn-primary" id="btnTambah"><i class="fas fa-plus"></i> Tambah Kelas</button>
    </div>

    <?php if ($message): ?>
    <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="filter-bar">
        <form method="GET">
            <div class="filter-grid">
                
                <div class="filter-field">
                    <label><i class="fas fa-layer-group"></i> Tingkat</label>
                    <select name="tingkat">
                        <option value="">Semua Tingkat</option>
                        <?php foreach ($tingkat_list as $t): ?>
                            <option value="<?= $t ?>" <?= $filter_tingkat == $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label><i class="fas fa-chalkboard-user"></i> Wali Kelas</label>
                    <select name="wali_kelas">
                        <option value="0">Semua Wali Kelas</option>
                        <?php foreach ($guru_list as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $filter_wali == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
                <a href="kelas.php" class="btn-reset">
                    <i class="fas fa-undo-alt"></i>
                </a>
            </div>
        </form>
    </div>

    

    <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr><th>Nama Kelas</th><th>Tingkat</th><th>Wali Kelas</th><th>Tahun Ajaran</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($kelas_list as $k): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($k['nama_kelas']) ?></strong></td>
                    <td><?= $k['tingkat'] ?></td>
                    <td><?= htmlspecialchars($k['wali_kelas'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($_SESSION['tahun_ajaran'] . ' - ' . $_SESSION['semester']) ?></td>
                    <td class="action-icons">
                        <a href="#" class="edit-btn" data-id="<?= $k['id'] ?>" data-nama="<?= htmlspecialchars($k['nama_kelas']) ?>" data-wali="<?= $k['wali_kelas_id'] ?>" data-tingkat="<?= $k['tingkat'] ?>"><i class="fas fa-edit"></i></a>
                        <a href="#" class="delete-btn" data-id="<?= $k['id'] ?>" data-nama="<?= htmlspecialchars($k['nama_kelas']) ?>"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($kelas_list)): ?>
        <div style="text-align: center; padding: 2rem; color: #94a3b8;">Tidak ada data kelas</div>
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

<!-- Modal Form -->
<div id="modalKelas" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Tambah Kelas</h3>
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="editId" value="0">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Nama Kelas</label>
                <input type="text" name="nama_kelas" id="namaKelas" required style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Tingkat</label>
                <select name="tingkat" id="tingkat" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="X">X</option><option value="XI">XI</option><option value="XII">XII</option>
                </select>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Wali Kelas</label>
                <select name="wali_kelas_id" id="waliKelas" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="0">- Tidak Ada -</option>
                    <?php foreach ($guru_list as $g): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Tahun Ajaran</label>
                <select name="tahun_ajaran_id" id="tahunAjaran" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <?php foreach ($tahun_list as $ta): ?><option value="<?= $ta['id'] ?>" <?= $ta['id'] == $tahun_ajaran_aktif_id ? 'selected' : '' ?>><?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-outline" id="closeModal">Batal</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const modal = document.getElementById('modalKelas');
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const editId = document.getElementById('editId');
    const namaKelas = document.getElementById('namaKelas');
    const tingkat = document.getElementById('tingkat');
    const waliKelas = document.getElementById('waliKelas');

    function openModal(title, action, id=0, nama='', tingkatVal='X', wali=0) {
        modalTitle.innerText = title;
        formAction.value = action;
        editId.value = id;
        namaKelas.value = nama;
        tingkat.value = tingkatVal;
        waliKelas.value = wali;
        modal.classList.add('show');
    }

    document.getElementById('btnTambah').onclick = () => openModal('Tambah Kelas', 'tambah');
    document.getElementById('closeModal').onclick = () => modal.classList.remove('show');

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            openModal('Edit Kelas', 'edit', btn.dataset.id, btn.dataset.nama, btn.dataset.tingkat, btn.dataset.wali);
        };
    });

    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            Swal.fire({ title: 'Hapus Kelas?', text: `Yakin hapus "${btn.dataset.nama}"?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Hapus' }).then(r => { if(r.isConfirmed) window.location.href = `?hapus_id=${btn.dataset.id}`; });
        };
    });

    window.onclick = (e) => { if (e.target === modal) modal.classList.remove('show'); };
</script>
</body>
</html>