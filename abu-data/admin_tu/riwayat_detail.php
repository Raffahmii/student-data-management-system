<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin_tu') {
    header('Location: ../auth/login.php');
    exit;
}

$nisn = isset($_GET['nisn']) ? $_GET['nisn'] : '';
if (empty($nisn)) {
    header('Location: riwayat.php');
    exit;
}

// Ambil data siswa berdasarkan NISN
$stmt = $pdo->prepare("SELECT nama, nisn FROM siswa WHERE nisn = ? LIMIT 1");
$stmt->execute([$nisn]);
$siswa = $stmt->fetch();
if (!$siswa) {
    header('Location: riwayat.php');
    exit;
}

// Ambil semua ID siswa dengan NISN yang sama (untuk riwayat)
$ids_siswa = $pdo->prepare("SELECT id FROM siswa WHERE nisn = ?");
$ids_siswa->execute([$nisn]);
$siswa_ids = $ids_siswa->fetchAll(PDO::FETCH_COLUMN);
if (empty($siswa_ids)) {
    header('Location: riwayat.php');
    exit;
}
$siswa_id_ref = $siswa_ids[0];

// Tambah riwayat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $tahun_ajaran_id = (int)$_POST['tahun_ajaran_id'];
    $kelas_id = (int)$_POST['kelas_id'];
    $status = $_POST['status'];
    
    $cek = $pdo->prepare("SELECT id FROM kelas WHERE id = ? AND tahun_ajaran_id = ?");
    $cek->execute([$kelas_id, $tahun_ajaran_id]);
    if (!$cek->fetch()) {
        header("Location: riwayat_detail.php?nisn=" . urlencode($nisn) . "&msg=error");
        exit;
    }
    
    $cekDuplikat = $pdo->prepare("SELECT id FROM riwayat_siswa WHERE siswa_id = ? AND tahun_ajaran_id = ?");
    $cekDuplikat->execute([$siswa_id_ref, $tahun_ajaran_id]);
    if ($cekDuplikat->fetch()) {
        header("Location: riwayat_detail.php?nisn=" . urlencode($nisn) . "&msg=duplicate");
        exit;
    }
    
    $sql = "INSERT INTO riwayat_siswa (siswa_id, kelas_id, tahun_ajaran_id, status, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$siswa_id_ref, $kelas_id, $tahun_ajaran_id, $status])) {
        logActivity('TAMBAH RIWAYAT', "Menambah riwayat untuk siswa NISN={$nisn}");
        header("Location: riwayat_detail.php?nisn=" . urlencode($nisn) . "&msg=added");
    } else {
        header("Location: riwayat_detail.php?nisn=" . urlencode($nisn) . "&msg=error");
    }
    exit;
}

// Edit riwayat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $riwayat_id = (int)$_POST['riwayat_id'];
    $tahun_ajaran_id = (int)$_POST['tahun_ajaran_id'];
    $kelas_id = (int)$_POST['kelas_id'];
    $status = $_POST['status'];
    
    $sql = "UPDATE riwayat_siswa SET tahun_ajaran_id = ?, kelas_id = ?, status = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$tahun_ajaran_id, $kelas_id, $status, $riwayat_id])) {
        logActivity('EDIT RIWAYAT', "Mengedit riwayat ID={$riwayat_id}");
        header("Location: riwayat_detail.php?nisn=" . urlencode($nisn) . "&msg=updated");
    } else {
        header("Location: riwayat_detail.php?nisn=" . urlencode($nisn) . "&msg=error");
    }
    exit;
}

// Hapus riwayat
if (isset($_GET['hapus_riwayat_id']) && is_numeric($_GET['hapus_riwayat_id'])) {
    $hapus_id = (int)$_GET['hapus_riwayat_id'];
    $pdo->prepare("DELETE FROM riwayat_siswa WHERE id = ?")->execute([$hapus_id]);
    logActivity('HAPUS RIWAYAT', "Menghapus riwayat ID={$hapus_id}");
    header("Location: riwayat_detail.php?nisn=" . urlencode($nisn) . "&msg=deleted");
    exit;
}

// Ambil data riwayat
$placeholders = implode(',', array_fill(0, count($siswa_ids), '?'));
$sql = "SELECT rs.id, ta.tahun, ta.semester, k.nama_kelas, g.nama as wali_kelas, rs.status, rs.created_at,
               ta.id as tahun_ajaran_id, k.id as kelas_id
        FROM riwayat_siswa rs
        JOIN tahun_ajaran ta ON rs.tahun_ajaran_id = ta.id
        JOIN kelas k ON rs.kelas_id = k.id
        LEFT JOIN guru g ON k.wali_kelas_id = g.id
        WHERE rs.siswa_id IN ($placeholders)
        ORDER BY ta.tahun DESC, ta.semester DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($siswa_ids);
$riwayat_list = $stmt->fetchAll();

$all_kelas = $pdo->query("SELECT id, nama_kelas, tahun_ajaran_id FROM kelas ORDER BY tahun_ajaran_id, nama_kelas")->fetchAll();
$kelas_by_ta = [];
foreach ($all_kelas as $k) {
    $kelas_by_ta[$k['tahun_ajaran_id']][] = ['id' => $k['id'], 'nama_kelas' => $k['nama_kelas']];
}
$tahun_ajaran_list = $pdo->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY tahun DESC, semester DESC")->fetchAll();

$message = '';
$msg_type = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added': $message = 'Riwayat berhasil ditambahkan.'; $msg_type = 'success'; break;
        case 'updated': $message = 'Riwayat berhasil diperbarui.'; $msg_type = 'success'; break;
        case 'deleted': $message = 'Riwayat berhasil dihapus.'; $msg_type = 'success'; break;
        case 'duplicate': $message = 'Siswa sudah memiliki riwayat untuk tahun ajaran ini.'; $msg_type = 'danger'; break;
        default: $message = 'Terjadi kesalahan.'; $msg_type = 'danger';
    }
}
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
        
        .page-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .page-header h1 { font-size: 1.3rem; font-weight: 700; color: #0f172a; }
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
        .btn-success { background: #1e6f3f; color: white; }
        .btn-success:hover { background: #166534; transform: translateY(-2px); }
        
        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .info-item { display: flex; align-items: baseline; gap: 0.5rem; }
        .info-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; }
        .info-value { font-weight: 600; color: #1e4a6b; }
        
        .form-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
        }
        .form-card h4 { color: #0f172a; margin-bottom: 1rem; font-size: 0.9rem; }
        .form-inline { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; }
        .form-group { flex: 1; min-width: 150px; }
        .form-group label { display: block; font-size: 0.7rem; color: #64748b; margin-bottom: 0.3rem; }
        .form-group select { width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 40px; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 0.9rem 0.8rem; background: #f8fafc; color: #475569; font-weight: 600; font-size: 0.75rem; }
        .data-table td { padding: 0.9rem 0.8rem; border-bottom: 1px solid #e2e8f0; color: #334155; font-size: 0.85rem; }
        .status-badge { display: inline-block; padding: 0.2rem 0.7rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-Aktif { background: #e0f2e9; color: #1e6f3f; }
        .status-Lulus { background: #fff3e0; color: #b76e0b; }
        .action-icons a { color: #94a3b8; margin: 0 4px; }
        .action-icons a:hover { color: #1e4a6b; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 24px; max-width: 450px; width: 90%; padding: 1.8rem; }
        .modal-content h3 { color: #0f172a; margin-bottom: 1rem; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 0.8rem; margin-top: 1.5rem; }
        
        .alert { padding: 0.8rem 1rem; border-radius: 40px; margin-bottom: 1rem; }
        .alert-success { background: #e0f2e9; color: #1e6f3f; }
        .alert-danger { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-header">
        <a href="riwayat.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="info-card">
        <div class="info-item"><span class="info-label">NISN</span><span class="info-value"><?= htmlspecialchars($siswa['nisn']) ?></span></div>
        <div class="info-item"><span class="info-label">Nama Lengkap</span><span class="info-value"><?= htmlspecialchars($siswa['nama']) ?></span></div>
    </div>

    <!-- Form Tambah Riwayat -->
    <div class="form-card">
        <h4><i class="fas fa-plus-circle"></i> Tambah Riwayat Baru</h4>
        <form method="POST">
            <input type="hidden" name="action" value="tambah">
            <div class="form-inline">
                <div class="form-group"><label>Tahun Ajaran</label><select name="tahun_ajaran_id" id="tahun_tambah" required><option value="">Pilih</option><?php foreach ($tahun_ajaran_list as $ta): ?><option value="<?= $ta['id'] ?>"><?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Kelas</label><select name="kelas_id" id="kelas_tambah" required disabled><option value="">Pilih tahun ajaran dulu</option></select></div>
                <div class="form-group"><label>Status</label><select name="status"><option>Aktif</option><option>Lulus</option><option>Dipindahkan</option><option>Mati</option></select></div>
                <div class="form-group"><button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan</button></div>
            </div>
        </form>
    </div>

    <!-- Tabel Riwayat -->
    <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow-x: auto;">
        <table class="data-table">
            <thead><tr><th>Tahun Ajaran</th><th>Semester</th><th>Kelas</th><th>Wali Kelas</th><th>Status</th><th>Tgl Catat</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($riwayat_list as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['tahun']) ?></td>
                    <td><?= htmlspecialchars($r['semester']) ?></td>
                    <td><?= htmlspecialchars($r['nama_kelas']) ?></td>
                    <td><?= htmlspecialchars($r['wali_kelas'] ?? '-') ?></td>
                    <td><span class="status-badge status-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
                    <td><?= date('d-m-Y', strtotime($r['created_at'])) ?></td>
                    <td class="action-icons">
                        <a href="#" class="edit-btn" data-id="<?= $r['id'] ?>" data-tahun="<?= $r['tahun_ajaran_id'] ?>" data-kelas="<?= $r['kelas_id'] ?>" data-status="<?= $r['status'] ?>"><i class="fas fa-edit"></i></a>
                        <a href="#" class="delete-btn" data-id="<?= $r['id'] ?>" data-nama="<?= htmlspecialchars($r['tahun'] . ' ' . $r['semester'] . ' - ' . $r['nama_kelas']) ?>"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($riwayat_list)): ?>
                <tr><td colspan="7" style="text-align:center; padding:2rem; color:#94a3b8;">Belum ada riwayat</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="modal">
    <div class="modal-content">
        <h3>Edit Riwayat</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="riwayat_id" id="edit_id">
            <div style="margin-bottom:1rem;"><label>Tahun Ajaran</label><select name="tahun_ajaran_id" id="edit_tahun" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;"><?php foreach ($tahun_ajaran_list as $ta): ?><option value="<?= $ta['id'] ?>"><?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?></option><?php endforeach; ?></select></div>
            <div style="margin-bottom:1rem;"><label>Kelas</label><select name="kelas_id" id="edit_kelas" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;"></select></div>
            <div style="margin-bottom:1rem;"><label>Status</label><select name="status" id="edit_status" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;"><option>Aktif</option><option>Lulus</option><option>Dipindahkan</option><option>Mati</option></select></div>
            <div class="modal-buttons"><button type="submit" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-outline" id="closeModal">Batal</button></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const kelasByTA = <?php echo json_encode($kelas_by_ta); ?>;
    function populateKelas(select, tahunId, selectedKelasId = null) {
        select.innerHTML = '';
        const list = kelasByTA[tahunId] || [];
        if (list.length === 0) { select.innerHTML = '<option value="">Tidak ada kelas</option>'; return; }
        list.forEach(k => { const opt = document.createElement('option'); opt.value = k.id; opt.textContent = k.nama_kelas; if (selectedKelasId == k.id) opt.selected = true; select.appendChild(opt); });
    }
    
    // Form tambah
    const tahunTambah = document.getElementById('tahun_tambah');
    const kelasTambah = document.getElementById('kelas_tambah');
    if (tahunTambah) {
        tahunTambah.addEventListener('change', function() {
            if (this.value) { kelasTambah.disabled = false; populateKelas(kelasTambah, this.value); } 
            else { kelasTambah.disabled = true; kelasTambah.innerHTML = '<option value="">Pilih tahun ajaran dulu</option>'; }
        });
    }
    
    // Modal edit
    const modal = document.getElementById('modalEdit');
    const editId = document.getElementById('edit_id');
    const editTahun = document.getElementById('edit_tahun');
    const editKelas = document.getElementById('edit_kelas');
    const editStatus = document.getElementById('edit_status');
    
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            editId.value = btn.dataset.id;
            editTahun.value = btn.dataset.tahun;
            editStatus.value = btn.dataset.status;
            populateKelas(editKelas, btn.dataset.tahun, btn.dataset.kelas);
            modal.classList.add('show');
        };
    });
    
    editTahun.addEventListener('change', function() { populateKelas(editKelas, this.value); });
    document.getElementById('closeModal').onclick = () => modal.classList.remove('show');
    
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            Swal.fire({ title: 'Hapus Riwayat?', text: `Yakin hapus "${btn.dataset.nama}"?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Hapus' }).then(r => {
                if(r.isConfirmed) window.location.href = `?nisn=<?= urlencode($nisn) ?>&hapus_riwayat_id=${btn.dataset.id}`;
            });
        };
    });
    
    window.onclick = (e) => { if (e.target === modal) modal.classList.remove('show'); };
</script>
</body>
</html>