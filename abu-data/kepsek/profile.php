<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';
$pwd_success = '';
$pwd_error = '';

$upload_dir = __DIR__ . '/../uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$stmt = $pdo->prepare("SELECT id, name, email, role, foto FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) $error = "Data pengguna tidak ditemukan.";

// Update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile' && !$error) {
    $name = trim($_POST['name'] ?? $user['name']);
    $fotoName = $user['foto'] ?? 'default-avatar.png';

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $newName = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $targetPath = $upload_dir . $newName;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetPath)) {
                if ($user['foto'] && $user['foto'] != 'default-avatar.png' && file_exists($upload_dir . $user['foto'])) {
                    unlink($upload_dir . $user['foto']);
                }
                $fotoName = $newName;
            } else {
                $error = "Gagal mengupload file.";
            }
        } else {
            $error = "Format file tidak didukung. Gunakan JPG, PNG, atau WEBP.";
        }
    }

    if (empty($error)) {
        $sql = "UPDATE users SET name = ?, foto = ?, updated_at = NOW() WHERE id = ?";
        $pdo->prepare($sql)->execute([$name, $fotoName, $user_id]);
        $_SESSION['user_name'] = $name;
        $_SESSION['user_foto'] = $fotoName;
        logActivity('EDIT PROFIL', "User ID={$user_id} mengupdate profil");
        $success = "Profil berhasil diperbarui!";
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
}

// Hapus foto (kembalikan ke default)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_photo' && !$error) {
    if ($user['foto'] && $user['foto'] != 'default-avatar.png' && file_exists($upload_dir . $user['foto'])) {
        unlink($upload_dir . $user['foto']);
    }
    
    $sql = "UPDATE users SET foto = 'default-avatar.png', updated_at = NOW() WHERE id = ?";
    $pdo->prepare($sql)->execute([$user_id]);
    $_SESSION['user_foto'] = 'default-avatar.png';
    logActivity('HAPUS FOTO', "User ID={$user_id} menghapus foto profil (kembali ke default)");
    $success = "Foto profil telah dikembalikan ke default!";
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Ganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current = $stmt->fetch();

    if (!$current) {
        $pwd_error = 'User tidak ditemukan.';
    } elseif ($old_password !== $current['password']) {
        $pwd_error = 'Password lama tidak sesuai.';
    } elseif (strlen($new_password) < 4) {
        $pwd_error = 'Password baru minimal 4 karakter.';
    } elseif ($new_password !== $confirm_password) {
        $pwd_error = 'Konfirmasi password tidak cocok.';
    } else {
        $update = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        if ($update->execute([$new_password, $user_id])) {
            logActivity('UBAH PASSWORD', "User ID={$user_id} mengubah password");
            $pwd_success = 'Password berhasil diubah.';
        } else {
            $pwd_error = 'Gagal mengubah password.';
        }
    }
}

$role_label = match($user['role']) {
    'admin_tu' => 'Admin Tata Usaha',
    'dapodik' => 'Admin Dapodik',
    'wakil_kepala_sekolah' => 'Wakil Kepala Sekolah',
    'kepala_sekolah' => 'Kepala Sekolah',
    default => ucwords(str_replace('_', ' ', $user['role']))
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Abu DataSiswa</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        .profile-wrapper {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .profile-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            overflow: hidden;
        }
        
        .profile-cover {
            height: 100px;
            background: linear-gradient(135deg, #1e4a6b, #0d3550);
        }
        
        .profile-body {
            padding: 0 2rem 2rem;
            position: relative;
        }
        
        .profile-avatar-section {
            text-align: center;
            margin-top: -50px;
            margin-bottom: 1.5rem;
        }
        
        .avatar-container {
            width: 100px;
            height: 100px;
            margin: 0 auto;
            position: relative;
        }
        
        .avatar-preview {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            background: #f1f5f9;
        }
        
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #1e4a6b;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s;
            border: 2px solid white;
        }
        
        .avatar-upload-btn:hover {
            background: #0d3550;
            transform: scale(1.05);
        }
        
        .avatar-upload-btn i {
            color: white;
            font-size: 0.8rem;
        }
        
        #fotoInput {
            display: none;
        }
        
        .profile-name {
            text-align: center;
            margin-top: 0.5rem;
        }
        
        .profile-name h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        
        .profile-name .role-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0284c7;
            padding: 0.2rem 0.8rem;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 0.3rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .info-field {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1rem;
        }
        
        .info-field label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 0.3rem;
        }
        
        .info-field .info-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .info-field .info-value input {
            flex: 1;
            padding: 0.5rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
            font-size: 0.85rem;
        }
        
        .info-field .info-value input:focus {
            outline: none;
            border-color: #1e4a6b;
        }
        
        .info-field .info-value input:disabled {
            background: #f1f5f9;
            color: #64748b;
        }
        
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .btn-danger {
            background: transparent;
            border: 1px solid #dc2626;
            color: #dc2626;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
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
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 450px;
            width: 90%;
            padding: 1.8rem;
        }
        
        .modal-content h3 {
            margin-bottom: 1rem;
            color: #0f172a;
            font-size: 1.2rem;
        }
        
        .modal-content .form-group {
            margin-bottom: 1rem;
        }
        
        .modal-content .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.3rem;
        }
        
        .modal-content .form-group input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 0.8rem;
            margin-top: 1.5rem;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon input {
            padding-right: 2.5rem;
        }
        
        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
        }
        
        .toggle-password:hover {
            color: #1e4a6b;
        }
        
        @media (max-width: 640px) {
            .profile-body {
                padding: 0 1rem 1rem;
            }
            .info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .action-buttons {
                flex-direction: column;
            }
            .action-buttons .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="profile-wrapper">
        <div class="profile-card">
            <div class="profile-cover"></div>
            <div class="profile-body">
                <div class="profile-avatar-section">
                    <div class="avatar-container">
                        <div class="avatar-preview">
                            <img src="../uploads/<?= htmlspecialchars($user['foto'] ?? 'default-avatar.png') ?>" alt="Avatar" id="avatarPreview">
                        </div>
                        <label class="avatar-upload-btn" for="fotoInput">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" name="foto" id="fotoInput" accept="image/*" form="profileForm">
                    </div>
                    <div class="profile-name">
                        <h2><?= htmlspecialchars($user['name']) ?></h2>
                        <span class="role-badge"><?= htmlspecialchars($role_label) ?></span>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="info-grid">
                        <div class="info-field">
                            <label>Nama Lengkap</label>
                            <div class="info-value">
                                <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                        </div>
                        <div class="info-field">
                            <label>Email</label>
                            <div class="info-value">
                                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            </div>
                        </div>
                        <div class="info-field">
                            <label>Role</label>
                            <div class="info-value">
                                <input type="text" value="<?= htmlspecialchars($role_label) ?>" disabled>
                            </div>
                        </div>
                        <div class="info-field">
                            <label>Password</label>
                            <div class="info-value">
                                <button type="button" class="btn btn-outline" id="btnGantiPassword" style="padding: 0.4rem 1rem;">
                                    <i class="fas fa-key"></i> Ganti Password
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="action-buttons">
                        <?php if ($user['foto'] && $user['foto'] != 'default-avatar.png'): ?>
                        <button type="button" class="btn btn-danger" id="btnDeletePhoto">
                            <i class="fas fa-trash-alt"></i> Hapus Foto
                        </button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ganti Password -->
<div id="modalPassword" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-key" style="color: #1e4a6b;"></i> Ganti Password</h3>
        <form method="POST" id="passwordForm">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label>Password Lama</label>
                <div class="input-icon">
                    <input type="password" name="old_password" id="old_password" required>
                    <i class="fas fa-eye toggle-password" data-target="old_password"></i>
                </div>
            </div>
            <div class="form-group">
                <label>Password Baru</label>
                <div class="input-icon">
                    <input type="password" name="new_password" id="new_password" required>
                    <i class="fas fa-eye toggle-password" data-target="new_password"></i>
                </div>
            </div>
            <div class="form-group">
                <label>Konfirmasi Password Baru</label>
                <div class="input-icon">
                    <input type="password" name="confirm_password" id="confirm_password" required>
                    <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                </div>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Ganti Password</button>
                <button type="button" class="btn btn-outline" id="closeModalPwd">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Form tersembunyi untuk hapus foto -->
<form method="POST" id="deletePhotoForm">
    <input type="hidden" name="action" value="delete_photo">
</form>

<script>
    // Preview foto sebelum upload
    const fotoInput = document.getElementById('fotoInput');
    const avatarPreview = document.getElementById('avatarPreview');
    
    fotoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                avatarPreview.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Modal ganti password
    const modalPwd = document.getElementById('modalPassword');
    const btnGanti = document.getElementById('btnGantiPassword');
    const closePwd = document.getElementById('closeModalPwd');
    
    btnGanti.onclick = () => modalPwd.classList.add('show');
    closePwd.onclick = () => modalPwd.classList.remove('show');
    window.onclick = (e) => {
        if (e.target === modalPwd) modalPwd.classList.remove('show');
    };
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (input.type === 'password') {
                input.type = 'text';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });
    });
    
    // SweetAlert2 untuk hapus foto
    document.getElementById('btnDeletePhoto')?.addEventListener('click', function() {
        Swal.fire({
            title: 'Hapus Foto Profil?',
            text: "Foto akan dikembalikan ke default!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deletePhotoForm').submit();
            }
        });
    });
    
    // SweetAlert2 untuk notifikasi sukses/error
    <?php if ($success): ?>
    Swal.fire({
        title: 'Berhasil!',
        text: '<?= htmlspecialchars($success) ?>',
        icon: 'success',
        confirmButtonColor: '#1e4a6b'
    });
    <?php endif; ?>
    
    <?php if ($error): ?>
    Swal.fire({
        title: 'Gagal!',
        text: '<?= htmlspecialchars($error) ?>',
        icon: 'error',
        confirmButtonColor: '#dc2626'
    });
    <?php endif; ?>
    
    <?php if ($pwd_success): ?>
    Swal.fire({
        title: 'Berhasil!',
        text: '<?= htmlspecialchars($pwd_success) ?>',
        icon: 'success',
        confirmButtonColor: '#1e4a6b'
    });
    <?php endif; ?>
    
    <?php if ($pwd_error): ?>
    Swal.fire({
        title: 'Gagal!',
        text: '<?= htmlspecialchars($pwd_error) ?>',
        icon: 'error',
        confirmButtonColor: '#dc2626'
    });
    <?php endif; ?>
</script>
</body>
</html>