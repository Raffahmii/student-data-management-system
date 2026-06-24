<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$error = '';

// Ambil semua tahun unik untuk dropdown
$stmt_tahun = $pdo->query("SELECT DISTINCT tahun FROM tahun_ajaran ORDER BY tahun DESC");
$tahun_list = $stmt_tahun->fetchAll();

// Ambil semua semester unik untuk dropdown
$stmt_semester = $pdo->query("SELECT DISTINCT semester FROM tahun_ajaran ORDER BY semester DESC");
$semester_list = $stmt_semester->fetchAll();

// Proses pemilihan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_tahun = trim($_POST['tahun'] ?? '');
    $selected_semester = trim($_POST['semester'] ?? '');
    
    if (empty($selected_tahun) || empty($selected_semester)) {
        $error = 'Pilih tahun dan semester terlebih dahulu.';
    } else {
        $stmt = $pdo->prepare("SELECT id, tahun, semester, status FROM tahun_ajaran WHERE tahun = ? AND semester = ?");
        $stmt->execute([$selected_tahun, $selected_semester]);
        $tahun = $stmt->fetch();
        
        if ($tahun) {
            $_SESSION['tahun_ajaran_id'] = $tahun['id'];
            $_SESSION['tahun_ajaran'] = $tahun['tahun'];
            $_SESSION['semester'] = $tahun['semester'];
            
            $role = $_SESSION['user_role'];
            switch ($role) {
                case 'admin_tu':
                    header('Location: ../admin_tu/dashboard.php');
                    break;
                case 'wakil_kepala_sekolah':
                    header('Location: ../wakil_kepsek/dashboard.php');
                    break;
                case 'kepala_sekolah':
                    header('Location: ../kepsek/dashboard.php');
                    break;
                case 'dapodik':
                    header('Location: ../dapodik/dashboard.php');
                    break;
                default:
                    header('Location: ../dashboard.php');
                    break;
            }
            exit;
        } else {
            $error = 'Kombinasi tahun dan semester tidak ditemukan.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Tahun Ajaran | Abu DataSiswa</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, #0b2b3b 0%, #1a4a6f 100%);
            position: relative;
            overflow-x: hidden;
        }

        /* ========== BACKGROUND PEGUNUNGAN ========== */
        .mountain-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        /* Gunung 1 (belakang) */
        .mountain-1 {
            position: absolute;
            bottom: -80px;
            left: -20%;
            width: 70%;
            height: 70%;
            background: linear-gradient(145deg, #1f5e6e, #0f3b4a);
            clip-path: polygon(20% 100%, 35% 40%, 50% 20%, 65% 40%, 80% 100%);
            opacity: 0.5;
            animation: floatSlow 20s infinite alternate ease-in-out;
        }

        /* Gunung 2 (tengah, lebih tinggi) */
        .mountain-2 {
            position: absolute;
            bottom: -100px;
            right: -10%;
            width: 80%;
            height: 80%;
            background: linear-gradient(135deg, #2d7a8c, #165a6b);
            clip-path: polygon(10% 100%, 25% 50%, 40% 30%, 55% 50%, 70% 25%, 85% 50%, 95% 100%);
            opacity: 0.6;
            animation: floatMedium 25s infinite alternate ease-in-out;
        }

        /* Gunung 3 (depan, lebih gelap) */
        .mountain-3 {
            position: absolute;
            bottom: -50px;
            left: 5%;
            width: 90%;
            height: 55%;
            background: linear-gradient(135deg, #1c6d82, #0e4c5e);
            clip-path: polygon(0% 100%, 15% 60%, 30% 45%, 45% 65%, 60% 40%, 75% 55%, 90% 35%, 100% 100%);
            opacity: 0.7;
            animation: floatFast 15s infinite alternate ease-in-out;
        }

        /* Matahari */
        .sun {
            position: absolute;
            top: 8%;
            right: 5%;
            width: 80px;
            height: 80px;
            background: radial-gradient(circle, #ffdd99, #ffaa66);
            border-radius: 50%;
            filter: blur(2px);
            opacity: 0.8;
            animation: pulseGlow 6s infinite alternate;
        }

        /* Awan */
        .cloud {
            position: absolute;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 60% 40% 50% 50%;
            filter: blur(15px);
            animation: drift 40s linear infinite;
        }
        .cloud-1 {
            width: 200px;
            height: 80px;
            top: 15%;
            left: -100px;
        }
        .cloud-2 {
            width: 300px;
            height: 100px;
            top: 30%;
            right: -150px;
            animation-duration: 50s;
            animation-direction: reverse;
        }
        .cloud-3 {
            width: 150px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-duration: 35s;
        }

        /* Animasi */
        @keyframes floatSlow {
            0% { transform: translateY(0px) translateX(-5px); }
            100% { transform: translateY(15px) translateX(5px); }
        }
        @keyframes floatMedium {
            0% { transform: translateY(0px) translateX(8px); }
            100% { transform: translateY(20px) translateX(-8px); }
        }
        @keyframes floatFast {
            0% { transform: translateY(0px) translateX(0px); }
            100% { transform: translateY(10px) translateX(10px); }
        }
        @keyframes pulseGlow {
            0% { opacity: 0.6; transform: scale(1); }
            100% { opacity: 1; transform: scale(1.05); }
        }
        @keyframes drift {
            0% { transform: translateX(0); }
            100% { transform: translateX(100vw); }
        }

        /* ========== CONTAINER & CARD (TIDAK BERUBAH) ========== */
        .container {
            position: relative;
            z-index: 10;
            max-width: 500px;
            width: 100%;
            margin: 1.5rem;
        }
        .card {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(8px);
            border-radius: 32px;
            padding: 2rem;
            box-shadow: 0 25px 45px -12px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.3);
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .header-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        .header-icon i {
            font-size: 1.8rem;
            color: white;
        }
        .header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #0f172a;
        }
        .header h1 span {
            color: #3b82f6;
        }
        .header p {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.3rem;
        }

        /* Info Tahun Aktif Saat Ini */
        .info-box {
            background: #f0f9ff;
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid #3b82f6;
        }
        .info-box i {
            font-size: 1.5rem;
            color: #3b82f6;
        }
        .info-box .info-text {
            flex: 1;
        }
        .info-box .info-text strong {
            display: block;
            color: #0f172a;
            font-weight: 700;
        }
        .info-box .info-text span {
            font-size: 0.75rem;
            color: #64748b;
        }

        /* Form 2 kolom (tahun & semester berdampingan) */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .form-group {
            flex: 1;
        }
        label {
            display: block;
            font-weight: 600;
            font-size: 0.75rem;
            color: #334155;
            margin-bottom: 0.5rem;
        }
        select {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 48px;
            border: 1px solid #e2e8f0;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            color: #1e293b;
            background: white;
            cursor: pointer;
            transition: 0.2s;
        }
        select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }

        /* Error */
        .error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #b91c1c;
            padding: 0.75rem 1rem;
            border-radius: 48px;
            text-align: center;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        /* Button */
        .btn-submit {
            background: #3b82f6;
            border: none;
            width: 100%;
            padding: 0.9rem;
            border-radius: 48px;
            font-weight: 600;
            font-size: 0.95rem;
            color: white;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        .btn-submit:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(59,130,246,0.2);
        }
        .btn-submit:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
        }

        /* Footer */
        .footer-note {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.7rem;
            color: #94a3b8;
        }
        .logout-link {
            text-align: right;
            margin-bottom: 1rem;
        }
        .logout-link a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.75rem;
            transition: 0.2s;
        }
        .logout-link a:hover {
            color: #3b82f6;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        body {
            animation: fadeIn 0.4s ease-out;
        }
        .fade-out {
            animation: fadeOut 0.3s ease-in forwards;
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-8px); }
        }

        /* Responsive */
        @media (max-width: 500px) {
            .card {
                padding: 1.5rem;
            }
            .header h1 {
                font-size: 1.3rem;
            }
            .header-icon {
                width: 45px;
                height: 45px;
            }
            .header-icon i {
                font-size: 1.5rem;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>

<!-- BACKGROUND PEGUNUNGAN -->
<div class="mountain-bg">
    <div class="mountain-1"></div>
    <div class="mountain-2"></div>
    <div class="mountain-3"></div>
    <div class="sun"></div>
    <div class="cloud cloud-1"></div>
    <div class="cloud cloud-2"></div>
    <div class="cloud cloud-3"></div>
</div>

<div class="container">
    <div class="card">
        <div class="logout-link">
            <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="header">
            <div class="header-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <h1>Pilih <span>Tahun Ajaran</span></h1>
            <p>Pilih tahun dan semester untuk melanjutkan</p>
        </div>

        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Informasi Tahun Ajaran yang Sedang Aktif -->
        <?php if (isset($_SESSION['tahun_ajaran_id'])): ?>
        <div class="info-box">
            <i class="fas fa-check-circle"></i>
            <div class="info-text">
                <strong><?= htmlspecialchars($_SESSION['tahun_ajaran']) ?> - <?= htmlspecialchars($_SESSION['semester']) ?></strong>
                <span>Sedang digunakan saat ini. Ganti di bawah jika perlu.</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form Pilihan Tahun dan Semester (2 dropdown berdampingan) -->
        <form method="POST" action="" id="formTahunAjaran">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Tahun</label>
                    <select name="tahun" id="tahunSelect" required>
                        <option value="">-- Pilih Tahun --</option>
                        <?php foreach ($tahun_list as $t): ?>
                            <option value="<?= htmlspecialchars($t['tahun']) ?>" 
                                <?= (isset($_SESSION['tahun_ajaran']) && $_SESSION['tahun_ajaran'] == $t['tahun']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['tahun']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-layer-group"></i> Semester</label>
                    <select name="semester" id="semesterSelect" required>
                        <option value="">-- Pilih Semester --</option>
                        <?php foreach ($semester_list as $s): ?>
                            <option value="<?= htmlspecialchars($s['semester']) ?>" 
                                <?= (isset($_SESSION['semester']) && $_SESSION['semester'] == $s['semester']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['semester']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="btnSubmit">
                <i class="fas fa-arrow-right"></i> Lanjut ke Dashboard
            </button>
        </form>

        
    </div>
</div>

<script>
    const tahunSelect = document.getElementById('tahunSelect');
    const semesterSelect = document.getElementById('semesterSelect');
    const btnSubmit = document.getElementById('btnSubmit');

    function toggleSubmitButton() {
        if (tahunSelect.value !== '' && semesterSelect.value !== '') {
            btnSubmit.disabled = false;
            btnSubmit.style.opacity = '1';
            btnSubmit.style.cursor = 'pointer';
        } else {
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = '0.6';
            btnSubmit.style.cursor = 'not-allowed';
        }
    }

    // Jalankan pertama kali
    toggleSubmitButton();

    // Event listener saat kedua select berubah
    tahunSelect.addEventListener('change', toggleSubmitButton);
    semesterSelect.addEventListener('change', toggleSubmitButton);

    // Animasi fade-out saat submit
    const form = document.getElementById('formTahunAjaran');
    form.addEventListener('submit', function(e) {
        if (tahunSelect.value === '' || semesterSelect.value === '') {
            e.preventDefault();
            return;
        }
        document.body.classList.add('fade-out');
    });
</script>
</body>
</html>