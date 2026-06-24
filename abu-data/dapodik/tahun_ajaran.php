<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_role = $_SESSION['user_role'];
$is_admin_tu = ($user_role === 'admin_tu');

// CRUD untuk admin_tu
if ($is_admin_tu) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $tahun = trim($_POST['tahun'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $status = $_POST['status'] ?? 'tidak_aktif';

        if (empty($tahun) || empty($semester)) {
            $_SESSION['message'] = ['text' => 'Tahun dan semester harus diisi.', 'type' => 'danger'];
            header('Location: tahun_ajaran.php');
            exit;
        }

        if ($action === 'tambah') {
            $cek = $pdo->prepare("SELECT id FROM tahun_ajaran WHERE tahun = ? AND semester = ?");
            $cek->execute([$tahun, $semester]);
            if ($cek->fetch()) {
                $_SESSION['message'] = ['text' => 'Tahun ajaran sudah ada.', 'type' => 'danger'];
            } else {
                if ($status === 'aktif') {
                    $pdo->exec("UPDATE tahun_ajaran SET status = 'tidak_aktif' WHERE status = 'aktif'");
                }
                $insert = $pdo->prepare("INSERT INTO tahun_ajaran (tahun, semester, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                if ($insert->execute([$tahun, $semester, $status])) {
                    logActivity('TAMBAH TAHUN AJARAN', "Menambah tahun ajaran: {$tahun} - {$semester}");
                    $_SESSION['message'] = ['text' => 'Tahun ajaran berhasil ditambahkan.', 'type' => 'success'];
                } else {
                    $_SESSION['message'] = ['text' => 'Gagal menambahkan.', 'type' => 'danger'];
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $cek = $pdo->prepare("SELECT id FROM tahun_ajaran WHERE tahun = ? AND semester = ? AND id != ?");
            $cek->execute([$tahun, $semester, $id]);
            if ($cek->fetch()) {
                $_SESSION['message'] = ['text' => 'Tahun ajaran sudah ada.', 'type' => 'danger'];
            } else {
                $old = $pdo->prepare("SELECT status FROM tahun_ajaran WHERE id = ?");
                $old->execute([$id]);
                $old_status = $old->fetchColumn();
                if ($status === 'aktif' && $old_status !== 'aktif') {
                    $pdo->exec("UPDATE tahun_ajaran SET status = 'tidak_aktif' WHERE status = 'aktif'");
                }
                $update = $pdo->prepare("UPDATE tahun_ajaran SET tahun = ?, semester = ?, status = ?, updated_at = NOW() WHERE id = ?");
                if ($update->execute([$tahun, $semester, $status, $id])) {
                    logActivity('EDIT TAHUN AJARAN', "Mengedit tahun ajaran ID={$id}");
                    $_SESSION['message'] = ['text' => 'Tahun ajaran berhasil diperbarui.', 'type' => 'success'];
                } else {
                    $_SESSION['message'] = ['text' => 'Gagal memperbarui.', 'type' => 'danger'];
                }
            }
        }
        header('Location: tahun_ajaran.php');
        exit;
    }

    if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
        $id = (int)$_GET['delete_id'];
        $ta = $pdo->prepare("SELECT tahun, semester FROM tahun_ajaran WHERE id = ?");
        $ta->execute([$id]);
        $ta_data = $ta->fetch();
        if ($pdo->prepare("DELETE FROM tahun_ajaran WHERE id = ?")->execute([$id])) {
            logActivity('HAPUS TAHUN AJARAN', "Menghapus tahun ajaran: {$ta_data['tahun']} - {$ta_data['semester']}");
            $_SESSION['message'] = ['text' => 'Tahun ajaran dihapus.', 'type' => 'success'];
            if (isset($_SESSION['tahun_ajaran_id']) && $_SESSION['tahun_ajaran_id'] == $id) {
                unset($_SESSION['tahun_ajaran_id'], $_SESSION['tahun_ajaran'], $_SESSION['semester']);
            }
        }
        header('Location: tahun_ajaran.php');
        exit;
    }
}

// Pilih tahun ajaran
if (isset($_GET['pilih']) && is_numeric($_GET['pilih'])) {
    $id = (int)$_GET['pilih'];
    $stmt = $pdo->prepare("SELECT id, tahun, semester FROM tahun_ajaran WHERE id = ?");
    $stmt->execute([$id]);
    $ta = $stmt->fetch();
    if ($ta) {
        $_SESSION['tahun_ajaran_id'] = $ta['id'];
        $_SESSION['tahun_ajaran'] = $ta['tahun'];
        $_SESSION['semester'] = $ta['semester'];
        $_SESSION['message'] = ['text' => "Tahun ajaran berubah menjadi {$ta['tahun']} - {$ta['semester']}", 'type' => 'success'];
        
        $redirect = match($_SESSION['user_role']) {
            'admin_tu' => '../admin_tu/tahun_ajaran.php',
            'wakasek_kesiswaan' => '../wakil_kepsek/tahun_ajaran.php',
            'kepala_sekolah' => '../kepsek/tahun_ajaran.php',
            'admin_dapodik' => '../dapodik/tahun_ajaran.php',
            default => 'dashboard.php'
        };
        header('Location: ' . $redirect);
        exit;
    }
}

$tahun_ajaran_list = $pdo->query("SELECT * FROM tahun_ajaran ORDER BY tahun DESC, semester DESC")->fetchAll();

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
    <title>Manajemen Tahun Ajaran - Admin TU</title>
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
        
        .current-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #1e4a6b;
        }
        .current-info strong { color: #1e4a6b; }
        
        .ta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.2rem;
            margin-top: 1rem;
        }
        .ta-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.2rem;
            transition: all 0.2s;
        }
        .ta-card:hover {
            border-color: #1e4a6b;
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .ta-tahun {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0f172a;
        }
        .ta-semester {
            display: inline-block;
            background: #e6f3ff;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            color: #1e4a6b;
            margin-top: 0.5rem;
        }
        .ta-status {
            margin-top: 0.8rem;
            font-size: 0.7rem;
        }
        .badge-aktif { color: #1e6f3f; background: #e0f2e9; padding: 0.2rem 0.6rem; border-radius: 20px; display: inline-block; }
        .badge-nonaktif { color: #64748b; background: #f1f5f9; padding: 0.2rem 0.6rem; border-radius: 20px; display: inline-block; }
        
        .btn-pilih {
            background: #1e4a6b;
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.8rem;
            transition: 0.2s;
        }
        .btn-pilih:hover { background: #0d3550; }
        .btn-pilih-aktif {
            background: #1e6f3f;
            cursor: default;
        }
        .btn-pilih-aktif:hover { background: #1e6f3f; }
        
        .card-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.8rem;
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 1px solid #e2e8f0;
        }
        .card-actions a {
            color: #94a3b8;
            font-size: 0.75rem;
            text-decoration: none;
        }
        .card-actions a:hover { color: #1e4a6b; }
        
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
            max-width: 450px;
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
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-header">
        <div class="page-title"><i class="fas fa-calendar-alt" style="color: #1e4a6b;"></i> Tahun Ajaran</div>
        <?php if ($is_admin_tu): ?>
        <button class="btn btn-primary" id="btnTambah"><i class="fas fa-plus"></i> Tambah Tahun Ajaran</button>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['tahun_ajaran_id'])): ?>
    <?php else: ?>
    <div class="current-info">
        <i class="fas fa-info-circle"></i> Belum ada tahun ajaran yang dipilih. Silakan pilih salah satu di bawah.
    </div>
    <?php endif; ?>

    <div class="ta-grid">
        <?php foreach ($tahun_ajaran_list as $ta): 
            $is_active = (isset($_SESSION['tahun_ajaran_id']) && $_SESSION['tahun_ajaran_id'] == $ta['id']);
        ?>
        <div class="ta-card">
            <div class="ta-tahun"><?= htmlspecialchars($ta['tahun']) ?></div>
            <div class="ta-semester"><?= htmlspecialchars($ta['semester']) ?> Semester</div>
            <div class="ta-status">
                <?php if ($ta['status'] == 'aktif'): ?>
                    <span class="badge-aktif"><i class="fas fa-check-circle"></i> Aktif</span>
                <?php else: ?>
                    <span class="badge-nonaktif"><i class="fas fa-clock"></i> Tidak Aktif</span>
                <?php endif; ?>
            </div>
            <?php if ($is_active): ?>
                <a href="#" class="btn-pilih btn-pilih-aktif"><i class="fas fa-check"></i> Sedang Digunakan</a>
            <?php else: ?>
                <a href="?pilih=<?= $ta['id'] ?>" class="btn-pilih"><i class="fas fa-arrow-right"></i> Pilih Tahun Ini</a>
            <?php endif; ?>
            
            <?php if ($is_admin_tu): ?>
            <div class="card-actions">
                <a href="#" class="edit-btn" data-id="<?= $ta['id'] ?>" data-tahun="<?= htmlspecialchars($ta['tahun']) ?>" data-semester="<?= $ta['semester'] ?>" data-status="<?= $ta['status'] ?>"><i class="fas fa-edit"></i> Edit</a>
                <a href="#" class="delete-btn" data-id="<?= $ta['id'] ?>" data-nama="<?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?>"><i class="fas fa-trash"></i> Hapus</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($tahun_ajaran_list)): ?>
        <div style="text-align: center; padding: 2rem; color: #94a3b8; grid-column: 1/-1;">Belum ada data tahun ajaran</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($is_admin_tu): ?>
<div id="modalTahun" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Tambah Tahun Ajaran</h3>
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="editId" value="0">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Tahun (contoh: 2024/2025)</label>
                <input type="text" name="tahun" id="tahun" placeholder="2024/2025" required style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Semester</label>
                <select name="semester" id="semester" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                </select>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.3rem;">Status</label>
                <select name="status" id="status" style="width:100%; padding:0.6rem; border:1px solid #e2e8f0; border-radius:40px;">
                    <option value="aktif">Aktif</option>
                    <option value="tidak_aktif">Tidak Aktif</option>
                </select>
                <small style="font-size:0.7rem; color:#64748b;">Jika dipilih Aktif, tahun ajaran lain akan otomatis menjadi Tidak Aktif.</small>
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
    const modal = document.getElementById('modalTahun');
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const editId = document.getElementById('editId');
    const tahun = document.getElementById('tahun');
    const semester = document.getElementById('semester');
    const statusSelect = document.getElementById('status');

    function openModal(title, action, id=0, tahunVal='', semesterVal='Ganjil', statusVal='tidak_aktif') {
        modalTitle.innerText = title;
        formAction.value = action;
        editId.value = id;
        tahun.value = tahunVal;
        semester.value = semesterVal;
        statusSelect.value = statusVal;
        modal.classList.add('show');
    }

    document.getElementById('btnTambah').onclick = () => openModal('Tambah Tahun Ajaran', 'tambah');
    document.getElementById('closeModal').onclick = () => modal.classList.remove('show');

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            openModal('Edit Tahun Ajaran', 'edit', btn.dataset.id, btn.dataset.tahun, btn.dataset.semester, btn.dataset.status);
        };
    });

    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            Swal.fire({ title: 'Hapus Tahun Ajaran?', text: `Yakin hapus "${btn.dataset.nama}"?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Hapus' }).then(r => { if(r.isConfirmed) window.location.href = `?delete_id=${btn.dataset.id}`; });
        };
    });

    window.onclick = (e) => { if (e.target === modal) modal.classList.remove('show'); };
</script>
<?php endif; ?>
</body>
</html>