<?php
session_start();
require_once '../config/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            logActivity('LOGIN', "User {$user['name']} ({$user['email']}) login dengan role {$user['role']}");
            header('Location: ../tahun-ajaran/tahun.php');
            exit;
        } else {
            $error = 'Email atau password salah.';
        }
    } else {
        $error = 'Harap isi email dan password.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Abu DataSiswa</title>
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
            overflow: hidden;
        }

        /* ========== BACKGROUND PEGUNUNGAN (CSS MURNI) ========== */
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

        /* Matahari / bulan sederhana */
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

        /* Awan-awan tipis */
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

        /* Animasi pergerakan gunung (efek lambat) */
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

        /* ========== LOGIN CARD (TIDAK BERUBAH) ========== */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            margin: 1.5rem;
        }
        .login-card {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(8px);
            border-radius: 32px;
            padding: 2rem 2rem 2.2rem;
            box-shadow: 0 25px 45px -12px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.3);
        }

        /* Header */
        .brand {
            text-align: center;
            margin-bottom: 1.8rem;
        }
        .brand-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        .brand-icon i {
            font-size: 1.8rem;
            color: white;
        }
        .brand h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f172a;
        }
        .brand h1 span {
            color: #3b82f6;
        }
        .brand p {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.3rem;
        }

        /* Input Fields */
        .input-group {
            margin-bottom: 1.25rem;
        }
        .input-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.3rem;
            display: block;
        }
        .input-icon {
            position: relative;
        }
        .input-icon i:first-child {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            transition: 0.2s;
        }
        .input-icon input {
            width: 100%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 48px;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            color: #1e293b;
            font-weight: 500;
            transition: 0.2s;
        }
        .input-icon input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .input-icon input:focus + i:first-child {
            color: #3b82f6;
        }
        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 1rem;
            transition: 0.2s;
        }
        .toggle-password:hover {
            color: #3b82f6;
        }

        /* Options */
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem 0 1.6rem;
            font-size: 0.75rem;
        }
        .checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        .checkbox input {
            width: 14px;
            height: 14px;
            accent-color: #3b82f6;
            margin: 0;
        }
        .checkbox span {
            color: #475569;
            font-weight: 500;
        }
        .forgot-link a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-link a:hover {
            text-decoration: underline;
        }

        /* Button */
        .btn-login {
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
        .btn-login:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(59,130,246,0.2);
        }

        /* Error Alert */
        .error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #b91c1c;
            padding: 0.7rem 1rem;
            border-radius: 48px;
            text-align: center;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        /* Footer Info */
        .info-note {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.65rem;
            background: #f8fafc;
            padding: 0.7rem;
            border-radius: 30px;
            color: #64748b;
        }
        .back-link {
            text-align: center;
            margin-top: 0.8rem;
        }
        .back-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 500;
            transition: 0.2s;
        }
        .back-link a:hover {
            color: #3b82f6;
        }

        /* Animations (tetap) */
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

        /* Responsive (tetap) */
        @media (max-width: 480px) {
            .login-card {
                padding: 1.5rem;
            }
            .brand h1 {
                font-size: 1.5rem;
            }
            .brand-icon {
                width: 45px;
                height: 45px;
            }
            .brand-icon i {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<!-- BACKGROUND PEGUNUNGAN CSS -->
<div class="mountain-bg">
    <div class="mountain-1"></div>
    <div class="mountain-2"></div>
    <div class="mountain-3"></div>
    <div class="sun"></div>
    <div class="cloud cloud-1"></div>
    <div class="cloud cloud-2"></div>
    <div class="cloud cloud-3"></div>
</div>

<div class="login-container">
    <div class="login-card">
        <div class="brand">
            <div class="brand-icon">
                <i class="fas fa-database"></i>
            </div>
            <h1>Abu<span>DataSiswa</span></h1>
            <p>Masuk ke akun Anda</p>
        </div>

        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="input-group">
                <label>Email</label>
                <div class="input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="contoh@sekolah.id" required autofocus>
                </div>
            </div>
            <div class="input-group">
                <label>Password</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Masukkan password" required>
                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                </div>
            </div>

            <div class="login-options">
                <label class="checkbox">
                    <input type="checkbox" name="remember"> <span>Remember me</span>
                </label>
                
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-arrow-right-to-bracket"></i> Masuk
            </button>
        </form>

        
        <div class="back-link">
            <a id="backToHome"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
        </div>
    </div>
</div>

<script>
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });

    // Animasi transisi saat kembali ke beranda
    const backLink = document.getElementById('backToHome');
    backLink.addEventListener('click', function(e) {
        e.preventDefault();
        document.body.classList.add('fade-out');
        setTimeout(() => {
            window.location.href = '../index.php';
        }, 300);
    });
</script>
</body>
</html>