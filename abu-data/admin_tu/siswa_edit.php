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
$tahun_ajaran_id = (int)$_SESSION['tahun_ajaran_id'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: siswa.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM siswa WHERE id = ?");
$stmt->execute([$id]);
$siswa = $stmt->fetch();
if (!$siswa) {
    header('Location: siswa.php');
    exit;
}

$kelas_list = $pdo->prepare("SELECT id, nama_kelas FROM kelas WHERE tahun_ajaran_id = ? ORDER BY nama_kelas");
$kelas_list->execute([$tahun_ajaran_id]);
$kelas_list = $kelas_list->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nisn = trim($_POST['nisn'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $alamat = trim($_POST['alamat'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $kelas_id = (int)($_POST['kelas_id'] ?? 0);
    $status = $_POST['status'] ?? 'Aktif';

    if (empty($nisn) || empty($nama) || empty($tanggal_lahir) || empty($jenis_kelamin) || $kelas_id <= 0) {
        $error = 'Harap isi semua field wajib.';
    } else {
        $cek = $pdo->prepare("SELECT id FROM siswa WHERE nisn = ? AND id != ?");
        $cek->execute([$nisn, $id]);
        if ($cek->fetch()) {
            $error = 'NISN sudah digunakan oleh siswa lain.';
        } else {
            $update = $pdo->prepare("UPDATE siswa SET nisn=?, nama=?, tanggal_lahir=?, jenis_kelamin=?, alamat=?, no_hp=?, kelas_id=?, status=?, updated_at=NOW() WHERE id=?");
            if ($update->execute([$nisn, $nama, $tanggal_lahir, $jenis_kelamin, $alamat, $no_hp, $kelas_id, $status, $id])) {
                logActivity('EDIT SISWA', "Mengedit siswa ID={$id}, Nama={$nama}");
                header("Location: siswa.php?msg=updated");
                exit;
            } else {
                $error = 'Gagal memperbarui siswa.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Siswa - Admin TU</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .form-container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.4rem;
            text-transform: uppercase;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
            font-size: 0.9rem;
        }
        .form-control:focus {
            outline: none;
            border-color: #1e4a6b;
            box-shadow: 0 0 0 2px rgba(30,74,107,0.1);
        }
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .btn {
            padding: 0.7rem 1.5rem;
            border-radius: 40px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        .btn-primary {
            background: #1e4a6b;
            color: white;
        }
        .btn-primary:hover {
            background: #0d3550;
            transform: translateY(-2px);
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #e2e8f0;
            color: #475569;
        }
        .btn-outline:hover {
            border-color: #1e4a6b;
            color: #1e4a6b;
        }
        .alert-danger {
            background: #fee2e2;
            border-left: 3px solid #ef4444;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            color: #b91c1c;
            font-size: 0.85rem;
        }
        .page-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
        }
        .page-header a {
            color: #64748b;
            text-decoration: none;
        }
        .page-header a:hover { color: #1e4a6b; }
        .required::after {
            content: '*';
            color: #ef4444;
            margin-left: 4px;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="page-header">
        <a href="siswa.php"><i class="fas fa-arrow-left"></i> Kembali</a>
        <h1><i class="fas fa-user-edit" style="color: #1e4a6b;"></i> Edit Data Siswa</h1>
    </div>

    <div class="form-container">
        <?php if ($error): ?>
            <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="required">NISN</label>
                <input type="text" name="nisn" class="form-control" required value="<?= htmlspecialchars($siswa['nisn']) ?>">
            </div>
            <div class="form-group">
                <label class="required">Nama Lengkap</label>
                <input type="text" name="nama" class="form-control" required value="<?= htmlspecialchars($siswa['nama']) ?>">
            </div>
            <div class="form-group">
                <label class="required">Tanggal Lahir</label>
                <input type="date" name="tanggal_lahir" class="form-control" required value="<?= htmlspecialchars($siswa['tanggal_lahir']) ?>">
            </div>
            <div class="form-group">
                <label class="required">Jenis Kelamin</label>
                <select name="jenis_kelamin" class="form-control" required>
                    <option value="L" <?= $siswa['jenis_kelamin'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= $siswa['jenis_kelamin'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>
            <div class="form-group">
                <label>Alamat</label>
                <textarea name="alamat" class="form-control"><?= htmlspecialchars($siswa['alamat']) ?></textarea>
            </div>
            <div class="form-group">
                <label>No. HP</label>
                <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($siswa['no_hp']) ?>">
            </div>
            <div class="form-group">
                <label class="required">Kelas</label>
                <select name="kelas_id" class="form-control" required>
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($kelas_list as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $siswa['kelas_id'] == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="Aktif" <?= $siswa['status'] == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="Lulus" <?= $siswa['status'] == 'Lulus' ? 'selected' : '' ?>>Lulus</option>
                    <option value="Dipindahkan" <?= $siswa['status'] == 'Dipindahkan' ? 'selected' : '' ?>>Dipindahkan</option>
                    <option value="Mati" <?= $siswa['status'] == 'Mati' ? 'selected' : '' ?>>Mati</option>
                </select>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                <a href="siswa.php" class="btn btn-outline"><i class="fas fa-times"></i> Batal</a>
            </div>
        </form>
    </div>
    
</div>
</body>
</html>