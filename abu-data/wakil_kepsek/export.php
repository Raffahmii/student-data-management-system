<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'wakil_kepala_sekolah') {
    header('Location: ../auth/login.php');
    exit;
}

// Proses export
if (isset($_POST['export'])) {
    $export_type = $_POST['export_type'] ?? 'siswa';
    $tahun_ajaran_id = isset($_POST['tahun_ajaran_id']) ? (int)$_POST['tahun_ajaran_id'] : 0;
    $kelas_id = isset($_POST['kelas_id']) ? (int)$_POST['kelas_id'] : 0;
    
    logActivity('EXPORT DATA', "Export data {$export_type} oleh wakil kepala sekolah");
    
    if ($export_type == 'siswa') {
        $filename = "export_siswa_" . date('Ymd_His') . ".csv";
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Header CSV Siswa
        fputcsv($output, ['ID', 'NISN', 'Nama', 'Tanggal Lahir', 'Jenis Kelamin', 'Alamat', 'No HP', 'Kelas', 'Tahun Ajaran', 'Status', 'Tanggal Dibuat']);
        
        $sql = "SELECT s.id, s.nisn, s.nama, s.tanggal_lahir, s.jenis_kelamin, s.alamat, s.no_hp, 
                       k.nama_kelas, ta.tahun, ta.semester, s.status, s.created_at
                FROM siswa s 
                LEFT JOIN kelas k ON s.kelas_id = k.id 
                LEFT JOIN tahun_ajaran ta ON s.tahun_ajaran_id = ta.id
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
        
        $sql .= " ORDER BY s.nama ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['tanggal_lahir'] = date('Y-m-d', strtotime($row['tanggal_lahir']));
            $row['tahun_ajaran'] = $row['tahun'] . ' - ' . $row['semester'];
            unset($row['tahun'], $row['semester']);
            fputcsv($output, $row);
        }
        fclose($output);
        
    } elseif ($export_type == 'kelas') {
        $filename = "export_kelas_" . date('Ymd_His') . ".csv";
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Header CSV Kelas
        fputcsv($output, ['ID', 'Nama Kelas', 'Tingkat', 'Wali Kelas', 'Tahun Ajaran', 'Semester', 'Jumlah Siswa', 'Created At']);
        
        $sql = "SELECT k.id, k.nama_kelas, k.tingkat, g.nama as wali_kelas, 
                       ta.tahun, ta.semester, k.created_at,
                       (SELECT COUNT(*) FROM siswa WHERE kelas_id = k.id AND tahun_ajaran_id = k.tahun_ajaran_id) as jumlah_siswa
                FROM kelas k
                LEFT JOIN guru g ON k.wali_kelas_id = g.id
                LEFT JOIN tahun_ajaran ta ON k.tahun_ajaran_id = ta.id
                WHERE 1=1";
        $params = [];
        
        if ($tahun_ajaran_id > 0) {
            $sql .= " AND k.tahun_ajaran_id = ?";
            $params[] = $tahun_ajaran_id;
        }
        
        $sql .= " ORDER BY k.nama_kelas ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['tahun_ajaran'] = $row['tahun'] . ' - ' . $row['semester'];
            unset($row['tahun'], $row['semester']);
            fputcsv($output, $row);
        }
        fclose($output);
    }
    exit;
}

// Data dropdown
$tahun_ajaran_list = $pdo->query("SELECT id, tahun, semester FROM tahun_ajaran ORDER BY tahun DESC, semester DESC")->fetchAll();
$kelas_list = $pdo->query("SELECT id, nama_kelas, tahun_ajaran_id FROM kelas ORDER BY nama_kelas")->fetchAll();

// Data kelas berdasarkan tahun ajaran untuk dynamic filter
$kelas_by_ta = [];
foreach ($kelas_list as $k) {
    $kelas_by_ta[$k['tahun_ajaran_id']][] = ['id' => $k['id'], 'nama_kelas' => $k['nama_kelas']];
}

// Statistik untuk info card
$total_siswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$total_kelas = $pdo->query("SELECT COUNT(*) FROM kelas")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - Wakil Kepala Sekolah</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .export-container {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .page-header {
            margin-bottom: 1.5rem;
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
            color: #1e4a6b;
        }
        
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-mini-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-mini-icon {
            width: 45px;
            height: 45px;
            background: #e6f3ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-mini-icon i {
            font-size: 1.2rem;
            color: #1e4a6b;
        }
        
        .stat-mini-info h4 {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            margin: 0 0 3px 0;
            text-transform: uppercase;
        }
        
        .stat-mini-info .number {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        
        .card-modern {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 1.5rem;
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
            color: #1e4a6b;
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
        
        .form-group select {
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
        
        .form-group select:focus {
            outline: none;
            border-color: #1e4a6b;
        }
        
        .radio-group {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.2rem;
            padding: 0.5rem 0;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            color: #334155;
            text-transform: none;
        }
        
        .radio-group input[type="radio"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #1e4a6b;
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
        
        .btn-export {
            background: #1e4a6b;
            color: white;
        }
        
        .btn-export:hover {
            background: #0d3550;
            transform: translateY(-1px);
        }
        
        .info-box {
            background: #f8fafc;
            border-left: 3px solid #1e4a6b;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            margin-top: 1rem;
        }
        
        .info-box i {
            color: #1e4a6b;
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
        
        .filter-section {
            transition: all 0.3s ease;
        }
        
        .filter-section.hidden {
            display: none;
        }
        
        @media (max-width: 768px) {
            .card-body {
                padding: 1rem;
            }
            .stats-mini {
                grid-template-columns: 1fr;
            }
            .radio-group {
                flex-direction: column;
                gap: 0.8rem;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="export-container">
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-download"></i>
                <span>Export Data</span>
            </div>
        </div>

        

        <div class="card-modern">
            <div class="card-header">
                <h3><i class="fas fa-file-csv"></i> Export Data</h3>
                
            </div>
            <div class="card-body">
                <form method="POST" id="exportForm">
                    <!-- Radio button pilih jenis export -->
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="export_type" value="siswa" checked> 
                            <i class="fas fa-user-graduate"></i> Data Siswa
                        </label>
                        <label>
                            <input type="radio" name="export_type" value="kelas"> 
                            <i class="fas fa-school"></i> Data Kelas
                        </label>
                    </div>
                    
                    <!-- Filter untuk Export Siswa -->
                    <div id="filter_siswa" class="filter-section">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Filter Tahun Ajaran</label>
                            <select name="tahun_ajaran_id" id="filter_tahun_ajaran">
                                <option value="0">Semua Tahun Ajaran</option>
                                <?php foreach ($tahun_ajaran_list as $ta): ?>
                                <option value="<?= $ta['id'] ?>"><?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Filter Kelas</label>
                            <select name="kelas_id" id="filter_kelas">
                                <option value="0">Semua Kelas</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Filter untuk Export Kelas -->
                    <div id="filter_kelas_data" class="filter-section hidden">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Filter Tahun Ajaran</label>
                            <select name="tahun_ajaran_id_kelas" id="filter_tahun_ajaran_kelas">
                                <option value="0">Semua Tahun Ajaran</option>
                                <?php foreach ($tahun_ajaran_list as $ta): ?>
                                <option value="<?= $ta['id'] ?>"><?= htmlspecialchars($ta['tahun'] . ' - ' . $ta['semester']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="export" class="btn-modern btn-export">
                        <i class="fas fa-file-csv"></i> Export Sekarang
                    </button>
                </form>
                
                <div class="info-box">
                    
                    <strong>Informasi Export</strong>
                    <p>File akan diekspor dalam format CSV (.csv) yang kompatibel dengan Microsoft Excel, Google Sheets, dan aplikasi spreadsheet lainnya.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Data kelas berdasarkan tahun ajaran untuk dynamic filter siswa
    const kelasByTA = <?php echo json_encode($kelas_by_ta); ?>;
    
    const radioSiswa = document.querySelector('input[value="siswa"]');
    const radioKelas = document.querySelector('input[value="kelas"]');
    const filterSiswa = document.getElementById('filter_siswa');
    const filterKelasData = document.getElementById('filter_kelas_data');
    const filterTahunAjaran = document.getElementById('filter_tahun_ajaran');
    const filterKelas = document.getElementById('filter_kelas');
    const filterTahunAjaranKelas = document.getElementById('filter_tahun_ajaran_kelas');
    
    // Toggle filter berdasarkan pilihan radio
    function toggleFilters() {
        if (radioSiswa.checked) {
            filterSiswa.classList.remove('hidden');
            filterKelasData.classList.add('hidden');
        } else {
            filterSiswa.classList.add('hidden');
            filterKelasData.classList.remove('hidden');
        }
    }
    
    // Populate kelas dropdown untuk filter siswa
    function populateKelasDropdown() {
        const tahunId = filterTahunAjaran.value;
        filterKelas.innerHTML = '<option value="0">Semua Kelas</option>';
        
        if (tahunId && tahunId !== '0' && kelasByTA[tahunId]) {
            kelasByTA[tahunId].forEach(kelas => {
                const option = document.createElement('option');
                option.value = kelas.id;
                option.textContent = kelas.nama_kelas;
                filterKelas.appendChild(option);
            });
        }
    }
    
    // Event listeners
    radioSiswa.addEventListener('change', toggleFilters);
    radioKelas.addEventListener('change', toggleFilters);
    filterTahunAjaran.addEventListener('change', populateKelasDropdown);
    
    // Untuk form submission, pastikan name yang sesuai
    document.getElementById('exportForm').addEventListener('submit', function(e) {
        if (radioKelas.checked) {
            // Hapus field yang tidak diperlukan untuk export kelas
            const tahunAjaranField = document.querySelector('select[name="tahun_ajaran_id"]');
            const kelasField = document.querySelector('select[name="kelas_id"]');
            if (tahunAjaranField) tahunAjaranField.disabled = true;
            if (kelasField) kelasField.disabled = true;
            
            // Gunakan nilai dari filter kelas
            const tahunKelasValue = filterTahunAjaranKelas.value;
            const hiddenTahunInput = document.createElement('input');
            hiddenTahunInput.type = 'hidden';
            hiddenTahunInput.name = 'tahun_ajaran_id';
            hiddenTahunInput.value = tahunKelasValue;
            this.appendChild(hiddenTahunInput);
        }
    });
    
    // Initial populate
    populateKelasDropdown();
    toggleFilters();
</script>
</body>
</html>