<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'kepala_sekolah') {
    header('Location: ../auth/login.php');
    exit;
}

// Proses export jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_type'])) {
    $export_type = $_POST['export_type'];
    $tahun_ajaran_id = isset($_POST['tahun_ajaran_id']) ? (int)$_POST['tahun_ajaran_id'] : 0;
    $kelas_id = isset($_POST['kelas_id']) ? (int)$_POST['kelas_id'] : 0;
    $status_filter = isset($_POST['status']) ? $_POST['status'] : '';
    
    logActivity('EXPORT DATA', "Export data {$export_type} oleh Kepala Sekolah");
    
    $filename = "export_{$export_type}_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($export_type === 'siswa') {
        // Header CSV Siswa
        fputcsv($output, ['ID', 'NISN', 'Nama', 'Tanggal Lahir', 'Jenis Kelamin', 'Alamat', 'No HP', 'Kelas', 'Status', 'Tangga']);
        
        $sql = "SELECT s.id, s.nisn, s.nama, s.tanggal_lahir, s.jenis_kelamin, s.alamat, s.no_hp, k.nama_kelas, s.status, s.tangga 
                FROM siswa s 
                LEFT JOIN kelas k ON s.kelas_id = k.id 
                WHERE 1=1";
        $params = [];
        if ($tahun_ajaran_id > 0) {
            $sql .= " AND s.tahun_ajaran_id = ?";
            $params[] = $tahun_ajaran_id;
        }
        if ($kelas_id > 0) {
            $sql .= " AND s.kelas_id = ?";
            $params[] = $kelas_id;
        }
        if ($status_filter !== '') {
            $sql .= " AND s.status = ?";
            $params[] = $status_filter;
        }
        $sql .= " ORDER BY s.nama";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['tanggal_lahir'] = date('Y-m-d', strtotime($row['tanggal_lahir']));
            fputcsv($output, $row);
        }
    } 
    elseif ($export_type === 'guru') {
        // Header CSV Guru
        fputcsv($output, ['ID', 'Nama', 'NIP', 'No HP', 'Mata Pelajaran', 'Status']);
        
        $sql = "SELECT id, nama, nip, no_hp, mata_pelajaran, status FROM guru WHERE 1=1";
        $params = [];
        if ($status_filter !== '') {
            $sql .= " AND status = ?";
            $params[] = $status_filter;
        }
        $sql .= " ORDER BY nama";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    }
    elseif ($export_type === 'kelas') {
        // Header CSV Kelas
        fputcsv($output, ['ID', 'Nama Kelas', 'Tingkat', 'Wali Kelas', 'Tahun Ajaran', 'Semester']);
        
        $sql = "SELECT k.id, k.nama_kelas, k.tingkat, g.nama as wali_kelas, ta.tahun, ta.semester 
                FROM kelas k 
                LEFT JOIN guru g ON k.wali_kelas_id = g.id 
                LEFT JOIN tahun_ajaran ta ON k.tahun_ajaran_id = ta.id 
                WHERE 1=1";
        $params = [];
        if ($tahun_ajaran_id > 0) {
            $sql .= " AND k.tahun_ajaran_id = ?";
            $params[] = $tahun_ajaran_id;
        }
        $sql .= " ORDER BY k.nama_kelas";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

// Data untuk dropdown filter
$tahun_ajaran_list = $pdo->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY tahun DESC, semester DESC")->fetchAll();
$kelas_list = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas")->fetchAll();
$status_siswa_list = ['Aktif', 'Lulus', 'Dipindahkan', 'Mati'];
$status_guru_list = ['aktif', 'nonaktif'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - Kepala Sekolah</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .export-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .export-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            transition: 0.2s;
        }
        .export-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }
        .export-icon {
            font-size: 2rem;
            color: #1e4a6b;
            margin-bottom: 1rem;
        }
        .export-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e4a6b;
            margin-bottom: 1rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.3rem;
        }
        .form-group select, .form-group input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border-radius: 40px;
            border: 1px solid #e2e8f0;
            font-size: 0.85rem;
        }
        .btn-export {
            background: #1e4a6b;
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            margin-top: 0.5rem;
            transition: 0.2s;
        }
        .btn-export:hover {
            background: #0d3550;
            transform: translateY(-2px);
        }
        .info-text {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 0.5rem;
            text-align: center;
        }
        .card-header {
            font-size: 1.1rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="card">
        <div class="card-header">
            <i class="fas fa-download"></i> Export Data
            
        </div>

        <div class="export-options">
            <!-- Export Siswa -->
            <div class="export-card">
                <div class="export-icon"><i class="fas fa-users"></i></div>
                <div class="export-title">Export Data Siswa</div>
                <form method="POST">
                    <input type="hidden" name="export_type" value="siswa">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Filter Tahun Ajaran</label>
                        <select name="tahun_ajaran_id">
                            <option value="0">-- Semua Tahun Ajaran --</option>
                            <?php foreach ($tahun_ajaran_list as $ta): ?>
                                <option value="<?= $ta['id'] ?>"><?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-school"></i> Filter Kelas</label>
                        <select name="kelas_id">
                            <option value="0">-- Semua Kelas --</option>
                            <?php foreach ($kelas_list as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Filter Status</label>
                        <select name="status">
                            <option value="">-- Semua Status --</option>
                            <?php foreach ($status_siswa_list as $st): ?>
                                <option value="<?= $st ?>"><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-export"><i class="fas fa-file-csv"></i> Export Siswa ke CSV</button>
                </form>
            </div>

            <!-- Export Guru -->
            <div class="export-card">
                <div class="export-icon"><i class="fas fa-chalkboard-user"></i></div>
                <div class="export-title">Export Data Guru</div>
                <form method="POST">
                    <input type="hidden" name="export_type" value="guru">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Filter Status</label>
                        <select name="status">
                            <option value="">-- Semua Status --</option>
                            <?php foreach ($status_guru_list as $st): ?>
                                <option value="<?= $st ?>"><?= ucfirst($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-export"><i class="fas fa-file-csv"></i> Export Guru ke CSV</button>
                </form>
            </div>

            <!-- Export Kelas -->
            <div class="export-card">
                <div class="export-icon"><i class="fas fa-school"></i></div>
                <div class="export-title">Export Data Kelas</div>
                <form method="POST">
                    <input type="hidden" name="export_type" value="kelas">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Filter Tahun Ajaran</label>
                        <select name="tahun_ajaran_id">
                            <option value="0">-- Semua Tahun Ajaran --</option>
                            <?php foreach ($tahun_ajaran_list as $ta): ?>
                                <option value="<?= $ta['id'] ?>"><?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-export"><i class="fas fa-file-csv"></i> Export Kelas ke CSV</button>
                </form>
            </div>
        </div>
        
    </div>
</div>
</body>
</html>