<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin_tu') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$error = '';

// Export
if (isset($_POST['export'])) {
    $type = $_POST['export_type'];
    $tahun_ajaran_id = isset($_POST['tahun_ajaran_id']) ? (int)$_POST['tahun_ajaran_id'] : 0;
    $kelas_id = isset($_POST['kelas_id']) ? (int)$_POST['kelas_id'] : 0;

    $filename = "export_{$type}_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    if ($type == 'guru') {
        fputcsv($output, ['ID', 'Nama', 'NIP', 'Mata Pelajaran', 'No HP', 'Status', 'Created At', 'Updated At']);
        $stmt = $pdo->query("SELECT id, nama, nip, mata_pelajaran, no_hp, status, created_at, updated_at FROM guru WHERE status = 'aktif' ORDER BY id");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $row);
    } 
    elseif ($type == 'kelas') {
        fputcsv($output, ['ID', 'Nama Kelas', 'Tingkat', 'Wali Kelas ID', 'Tahun Ajaran ID', 'Created At', 'Updated At']);
        $sql = "SELECT id, nama_kelas, tingkat, wali_kelas_id, tahun_ajaran_id, created_at, updated_at FROM kelas";
        if ($tahun_ajaran_id > 0) $sql .= " WHERE tahun_ajaran_id = $tahun_ajaran_id";
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $row);
    } 
    elseif ($type == 'siswa') {
        fputcsv($output, ['ID', 'NISN', 'Nama', 'Tanggal Lahir', 'Jenis Kelamin', 'Alamat', 'No HP', 'Kelas ID', 'Tahun Ajaran ID', 'Status', 'Created At', 'Updated At']);
        $sql = "SELECT id, nisn, nama, tanggal_lahir, jenis_kelamin, alamat, no_hp, kelas_id, tahun_ajaran_id, status, created_at, updated_at FROM siswa WHERE status = 'Aktif'";
        if ($tahun_ajaran_id > 0) $sql .= " AND tahun_ajaran_id = $tahun_ajaran_id";
        if ($kelas_id > 0) $sql .= " AND kelas_id = $kelas_id";
        $stmt = $pdo->query($sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['tanggal_lahir'] = date('Y-m-d', strtotime($row['tanggal_lahir']));
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

// Import
if (isset($_POST['import']) && isset($_FILES['import_file']) && $_FILES['import_file']['error'] == 0) {
    $type = $_POST['import_type'];
    $file = $_FILES['import_file']['tmp_name'];
    $handle = fopen($file, 'r');
    $firstRow = true;
    $successCount = 0;
    $errorCount = 0;

    if ($type == 'guru') {
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if ($firstRow) { $firstRow = false; continue; }
            if (count($data) < 6) continue;
            $nama = trim($data[1]);
            $nip = trim($data[2]);
            $mata_pelajaran = trim($data[3]);
            $no_hp = trim($data[4]);
            $status = trim($data[5]);
            if (!in_array($status, ['aktif', 'nonaktif'])) $status = 'aktif';
            if (empty($nama) || empty($nip)) continue;
            
            $cek = $pdo->prepare("SELECT id FROM guru WHERE nip = ?");
            $cek->execute([$nip]);
            if ($cek->fetch()) { $errorCount++; continue; }
            
            $stmt = $pdo->prepare("INSERT INTO guru (nama, nip, mata_pelajaran, no_hp, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            if ($stmt->execute([$nama, $nip, $mata_pelajaran, $no_hp, $status])) $successCount++;
            else $errorCount++;
        }
        $message = "Import guru: $successCount berhasil, $errorCount gagal";
    } 
    elseif ($type == 'kelas') {
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if ($firstRow) { $firstRow = false; continue; }
            if (count($data) < 6) continue;
            $nama_kelas = trim($data[1]);
            $tingkat = trim($data[2]);
            $wali_kelas_id = (int)$data[3] ?: null;
            $tahun_ajaran_id = (int)$data[4];
            if (empty($nama_kelas) || $tahun_ajaran_id <= 0) continue;
            if (!in_array($tingkat, ['X', 'XI', 'XII'])) $tingkat = 'X';
            
            $cek = $pdo->prepare("SELECT id FROM kelas WHERE nama_kelas = ? AND tahun_ajaran_id = ?");
            $cek->execute([$nama_kelas, $tahun_ajaran_id]);
            if ($cek->fetch()) { $errorCount++; continue; }
            
            $stmt = $pdo->prepare("INSERT INTO kelas (nama_kelas, tingkat, wali_kelas_id, tahun_ajaran_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            if ($stmt->execute([$nama_kelas, $tingkat, $wali_kelas_id, $tahun_ajaran_id])) $successCount++;
            else $errorCount++;
        }
        $message = "Import kelas: $successCount berhasil, $errorCount gagal";
    } 
    elseif ($type == 'siswa') {
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if ($firstRow) { $firstRow = false; continue; }
            if (count($data) < 11) continue;
            $nisn = trim($data[1]);
            $nama = trim($data[2]);
            $tanggal_lahir = trim($data[3]);
            $jk = trim($data[4]);
            $alamat = trim($data[5]);
            $no_hp = trim($data[6]);
            $kelas_id = (int)$data[7] ?: null;
            $tahun_ajaran_id = (int)$data[8];
            $status = trim($data[9]);
            if (empty($nisn) || empty($nama) || empty($tanggal_lahir) || $tahun_ajaran_id <= 0) continue;
            if (!in_array($status, ['Aktif', 'Lulus', 'Dipindahkan', 'Mati'])) $status = 'Aktif';
            
            $cek = $pdo->prepare("SELECT id FROM siswa WHERE nisn = ?");
            $cek->execute([$nisn]);
            if ($cek->fetch()) { $errorCount++; continue; }
            
            $tangga = 0;
            if ($kelas_id) {
                $kelas_info = $pdo->prepare("SELECT tingkat FROM kelas WHERE id = ?");
                $kelas_info->execute([$kelas_id]);
                $tingkat = $kelas_info->fetchColumn();
                if ($tingkat == 'X') $tangga = 1;
                elseif ($tingkat == 'XI') $tangga = 3;
                elseif ($tingkat == 'XII') $tangga = 5;
            }
            
            $stmt = $pdo->prepare("INSERT INTO siswa (nisn, nama, tanggal_lahir, jenis_kelamin, alamat, no_hp, kelas_id, tahun_ajaran_id, tangga, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?, NOW(), NOW())");
            if ($stmt->execute([$nisn, $nama, $tanggal_lahir, $jk, $alamat, $no_hp, $kelas_id, $tahun_ajaran_id, $tangga, $status])) $successCount++;
            else $errorCount++;
        }
        $message = "Import siswa: $successCount berhasil, $errorCount gagal";
    }
    fclose($handle);
    logActivity('IMPORT DATA', "Import {$type}: {$successCount} berhasil, {$errorCount} gagal");
}

// Ambil data untuk filter (hanya yang aktif)
$tahun_ajaran_list = $pdo->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY tahun DESC, semester DESC")->fetchAll();

// Ambil kelas berdasarkan tahun ajaran (untuk dynamic filter)
$kelas_by_ta = [];
$all_kelas = $pdo->query("SELECT k.id, k.nama_kelas, k.tahun_ajaran_id FROM kelas k")->fetchAll();
foreach ($all_kelas as $k) {
    $kelas_by_ta[$k['tahun_ajaran_id']][] = ['id' => $k['id'], 'nama_kelas' => $k['nama_kelas']];
}

// Hitung total data AKTIF saja untuk card stats
$total_guru = $pdo->query("SELECT COUNT(*) FROM guru WHERE status = 'aktif'")->fetchColumn();
$total_kelas = $pdo->query("SELECT COUNT(*) FROM kelas")->fetchColumn();
$total_siswa = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status = 'Aktif'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import / Export Data - Admin TU</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .import-export-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-title i {
            color: #0f172a;
            font-size: 1.3rem;
        }
        
        .stats-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            background: #f1f5f9;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon i {
            font-size: 1.3rem;
            color: #0f172a;
        }
        
        .stat-info h3 {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 5px 0;
        }
        
        .stat-info .number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        
        .two-columns {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .card-modern {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.2s;
        }
        
        .card-modern:hover {
            border-color: #cbd5e1;
        }
        
        .card-header {
            padding: 1.2rem 1.5rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-header h3 i {
            color: #0f172a;
            font-size: 1rem;
        }
        
        .card-header p {
            margin: 0.3rem 0 0 0;
            font-size: 0.7rem;
            color: #64748b;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .form-group label i {
            width: 18px;
            color: #64748b;
            margin-right: 5px;
        }
        
        .form-group select,
        .form-group input[type="file"] {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: white;
            color: #0f172a;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #0f172a;
        }
        
        .btn-modern {
            padding: 0.7rem 1.2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            width: 100%;
            font-family: 'Inter', sans-serif;
        }
        
        .btn-export, .btn-import {
            background: #0f172a;
            color: white;
        }
        
        .btn-export:hover, .btn-import:hover {
            background: #1e293b;
            transform: translateY(-1px);
        }
        
        .info-box {
            background: #f8fafc;
            border-left: 3px solid #0f172a;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            margin-top: 1rem;
        }
        
        .info-box i {
            color: #0f172a;
            margin-right: 0.5rem;
        }
        
        .info-box strong {
            color: #0f172a;
            font-size: 0.7rem;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .info-box p {
            margin: 0;
            font-size: 0.7rem;
            color: #475569;
            line-height: 1.5;
        }
        
        .format-example {
            background: #f8fafc;
            border-radius: 12px;
            padding: 0.8rem;
            margin-top: 0.8rem;
            font-family: monospace;
            font-size: 0.65rem;
            color: #475569;
            overflow-x: auto;
        }
        
        .format-example code {
            font-family: monospace;
        }
        
        hr {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 1rem 0;
        }
        
        .alert-modern {
            padding: 0.8rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            animation: slideInDown 0.3s ease;
            background: #f8fafc;
            border-left: 3px solid #0f172a;
            color: #0f172a;
        }
        
        .alert-modern i {
            font-size: 1rem;
            color: #0f172a;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .stats-section {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }
            
            .two-columns {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="import-export-container">
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-exchange-alt"></i>
                <span>Import / Export Data</span>
            </div>
        </div>

        

        <?php if ($message): ?>
        <div class="alert-modern">
            
            <span><?= htmlspecialchars($message) ?></span>
        </div>
        <?php endif; ?>

        <div class="two-columns">
            <!-- Export Panel -->
            <div class="card-modern">
                <div class="card-header">
                    <h3><i class="fas fa-download"></i> Export Data</h3>
                    <p>Ekspor data ke format CSV</p>
                </div>
                <div class="card-body">
                    <form method="POST" id="exportForm">
                        <div class="form-group">
                            <label><i class="fas fa-database"></i> Tipe Data</label>
                            <select name="export_type" id="export_type" required>
                                <option value="guru">Data Guru (Aktif)</option>
                                <option value="kelas">Data Kelas</option>
                                <option value="siswa">Data Siswa (Aktif)</option>
                            </select>
                        </div>
                        <div class="form-group" id="filter_tahun_group" style="display:none;">
                            <label><i class="fas fa-calendar-alt"></i> Filter Tahun Ajaran</label>
                            <select name="tahun_ajaran_id" id="filter_tahun_ajaran">
                                <option value="0">Semua Tahun Ajaran</option>
                                <?php foreach ($tahun_ajaran_list as $ta): ?>
                                <option value="<?= $ta['id'] ?>"><?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="filter_kelas_group" style="display:none;">
                            <label><i class="fas fa-building"></i> Filter Kelas</label>
                            <select name="kelas_id" id="filter_kelas">
                                <option value="0">Semua Kelas</option>
                            </select>
                        </div>
                        <button type="submit" name="export" class="btn-modern btn-export">
                            <i class="fas fa-file-csv"></i> Export Sekarang
                        </button>
                    </form>
                    <div class="info-box">
                        
                        <strong>Informasi Export</strong>
                        <p>File akan diekspor dalam format CSV (.csv). Data yang diekspor adalah data aktif (Guru dengan status aktif, Siswa dengan status Aktif).</p>
                    </div>
                </div>
            </div>

            <!-- Import Panel -->
            <div class="card-modern">
                <div class="card-header">
                    <h3><i class="fas fa-upload"></i> Import Data</h3>
                    <p>Import data dari file CSV</p>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="importForm">
                        <div class="form-group">
                            <label><i class="fas fa-database"></i> Tipe Data</label>
                            <select name="import_type" id="import_type" required>
                                <option value="guru">Data Guru</option>
                                <option value="kelas">Data Kelas</option>
                                <option value="siswa">Data Siswa</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-file-csv"></i> File CSV</label>
                            <input type="file" name="import_file" accept=".csv" required>
                        </div>
                        <button type="submit" name="import" class="btn-modern btn-import">
                            <i class="fas fa-upload"></i> Import Sekarang
                        </button>
                    </form>
                    
                    <hr>
                    
                    <div class="info-box">
                        <i class="fas fa-question-circle"></i>
                        <strong>Format CSV yang Didukung</strong>
                        <p>Baris pertama adalah header dan akan dilewati secara otomatis.</p>
                    </div>
                    
                    <div class="format-example" id="formatExample">
                        <code id="formatCode"></code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Data kelas berdasarkan tahun ajaran dari PHP
    const kelasByTA = <?php echo json_encode($kelas_by_ta); ?>;
    
    const exportType = document.getElementById('export_type');
    const importType = document.getElementById('import_type');
    const filterTahun = document.getElementById('filter_tahun_group');
    const filterKelas = document.getElementById('filter_kelas_group');
    const filterTahunAjaran = document.getElementById('filter_tahun_ajaran');
    const filterKelasSelect = document.getElementById('filter_kelas');
    const formatCode = document.getElementById('formatCode');
    
    // Function to populate kelas dropdown based on selected tahun ajaran
    function populateKelasDropdown() {
        const tahunId = filterTahunAjaran.value;
        filterKelasSelect.innerHTML = '<option value="0">Semua Kelas</option>';
        
        if (tahunId && tahunId !== '0' && kelasByTA[tahunId]) {
            kelasByTA[tahunId].forEach(kelas => {
                const option = document.createElement('option');
                option.value = kelas.id;
                option.textContent = kelas.nama_kelas;
                filterKelasSelect.appendChild(option);
            });
        }
    }
    
    // Format examples
    const formats = {
        guru: `Contoh Format Guru (CSV):\nID,Nama,NIP,Mata Pelajaran,No HP,Status,Created,Updated\n1,Dr. Ahmad Saifuddin,197501012005011001,Matematika,081234567890,aktif,2024-01-01,2024-01-01`,
        kelas: `Contoh Format Kelas (CSV):\nID,Nama Kelas,Tingkat,Wali Kelas ID,Tahun Ajaran ID,Created,Updated\n1,XII IPA 1,XII,5,1,2024-01-01,2024-01-01`,
        siswa: `Contoh Format Siswa (CSV):\nID,NISN,Nama,Tgl Lahir,JK,Alamat,No HP,Kelas ID,Tahun Ajaran ID,Status,Created,Updated\n1,1234567890,Andi Pratama,2006-05-15,L,Jl. Merdeka No.1,08123456789,1,1,Aktif,2024-01-01,2024-01-01`
    };
    
    function toggleFilters() {
        const val = exportType.value;
        if (val === 'kelas') { 
            filterTahun.style.display = 'block'; 
            filterKelas.style.display = 'none'; 
        }
        else if (val === 'siswa') { 
            filterTahun.style.display = 'block'; 
            filterKelas.style.display = 'block';
            // Populate kelas dropdown when showing
            populateKelasDropdown();
        }
        else { 
            filterTahun.style.display = 'none'; 
            filterKelas.style.display = 'none'; 
        }
    }
    
    function updateFormat() {
        const type = importType.value;
        formatCode.innerHTML = formats[type].replace(/\n/g, '<br>');
    }
    
    // Event listeners
    exportType.addEventListener('change', toggleFilters);
    importType.addEventListener('change', updateFormat);
    filterTahunAjaran.addEventListener('change', function() {
        if (exportType.value === 'siswa') {
            populateKelasDropdown();
        }
    });
    
    toggleFilters();
    updateFormat();
    
    // SweetAlert2 confirmation for export
    document.querySelector('button[name="export"]')?.addEventListener('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Konfirmasi Export',
            text: 'Yakin ingin mengekspor data?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0f172a',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Export!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('exportForm').submit();
            }
        });
    });
    
    // SweetAlert2 confirmation for import
    document.querySelector('button[name="import"]')?.addEventListener('click', function(e) {
        e.preventDefault();
        const fileInput = document.querySelector('input[name="import_file"]');
        if (!fileInput.files.length) {
            Swal.fire('Error!', 'Pilih file CSV terlebih dahulu!', 'error');
            return;
        }
        Swal.fire({
            title: 'Konfirmasi Import',
            text: 'Yakin ingin mengimpor data? Data dengan NIP/NISN yang sama akan dilewati.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0f172a',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Import!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('importForm').submit();
            }
        });
    });
    
    <?php if ($message): ?>
    Swal.fire({
        title: 'Import Selesai!',
        html: '<?= htmlspecialchars($message) ?>',
        icon: 'success',
        confirmButtonColor: '#0f172a'
    });
    <?php endif; ?>
</script>
</body>
</html>