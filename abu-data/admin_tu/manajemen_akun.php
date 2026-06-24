<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin_tu') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$msg_type = '';

// Tambah Akun
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $message = 'Semua field wajib diisi.';
        $msg_type = 'danger';
    } else {
        $cek = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $cek->execute([$email]);
        if ($cek->fetch()) {
            $message = 'Email sudah terdaftar.';
            $msg_type = 'danger';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            if ($stmt->execute([$name, $email, $password, $role])) {
                logActivity('TAMBAH USER', "Menambah user: {$email}, Role: {$role}");
                $message = 'Akun berhasil ditambahkan.';
                $msg_type = 'success';
            } else {
                $message = 'Gagal menambahkan akun.';
                $msg_type = 'danger';
            }
        }
    }
    header("Location: manajemen_akun.php?msg=" . urlencode($message) . "&type=$msg_type");
    exit;
}

// Edit Akun (termasuk password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $new_password = trim($_POST['new_password'] ?? '');

    if ($id <= 0 || empty($name) || empty($email) || empty($role)) {
        $message = 'Data tidak lengkap.';
        $msg_type = 'danger';
    } else {
        $cek = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $cek->execute([$email, $id]);
        if ($cek->fetch()) {
            $message = 'Email sudah digunakan akun lain.';
            $msg_type = 'danger';
        } else {
            // Jika password diisi, update password juga
            if (!empty($new_password)) {
                if (strlen($new_password) < 4) {
                    $message = 'Password minimal 4 karakter.';
                    $msg_type = 'danger';
                    header("Location: manajemen_akun.php?msg=" . urlencode($message) . "&type=$msg_type");
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, password = ?, updated_at = NOW() WHERE id = ?");
                $update = $stmt->execute([$name, $email, $role, $new_password, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, updated_at = NOW() WHERE id = ?");
                $update = $stmt->execute([$name, $email, $role, $id]);
            }
            if ($update) {
                logActivity('EDIT USER', "Mengedit user ID={$id}, Email={$email}" . (!empty($new_password) ? ", Password diubah" : ""));
                $message = 'Akun berhasil diperbarui.';
                $msg_type = 'success';
            } else {
                $message = 'Gagal memperbarui akun.';
                $msg_type = 'danger';
            }
        }
    }
    header("Location: manajemen_akun.php?msg=" . urlencode($message) . "&type=$msg_type");
    exit;
}

// Hapus Akun
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    if ($id == $_SESSION['user_id']) {
        $message = 'Anda tidak dapat menghapus akun sendiri.';
        $msg_type = 'danger';
    } else {
        $user_del = $pdo->prepare("SELECT email, role FROM users WHERE id = ?");
        $user_del->execute([$id]);
        $user_data = $user_del->fetch();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id])) {
            logActivity('HAPUS USER', "Menghapus user ID={$id}, Email={$user_data['email']}");
            $message = 'Akun berhasil dihapus.';
            $msg_type = 'success';
        } else {
            $message = 'Gagal menghapus akun.';
            $msg_type = 'danger';
        }
    }
    header("Location: manajemen_akun.php?msg=" . urlencode($message) . "&type=$msg_type");
    exit;
}

// Ambil pesan dari URL
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
    $msg_type = $_GET['type'] ?? 'info';
}

// Ambil semua user
$users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY id")->fetchAll();

// Role labels dan colors yang benar (sesuai database)
$role_labels = [
    'admin_tu' => 'Admin TU',
    'dapodik' => 'Admin Dapodik',
    'wakil_kepala_sekolah' => 'Wakil Kepala Sekolah',
    'kepala_sekolah' => 'Kepala Sekolah'
];
$role_colors = [
    'admin_tu' => '#1e6f3f',
    'dapodik' => '#0b5e7c',
    'wakil_kepala_sekolah' => '#b76e0b',
    'kepala_sekolah' => '#6d28d9'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Akun - Admin TU</title>
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
        .page-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
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
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 1px solid #e2e8f0; color: #475569; }
        .btn-outline:hover { border-color: #2563eb; color: #2563eb; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .data-table th {
            text-align: left;
            padding: 0.9rem 1rem;
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 0.85rem;
        }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover { background: #f8fafc; }
        
        .role-badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .action-icons a {
            color: #94a3b8;
            margin: 0 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .action-icons a:hover { color: #2563eb; }
        
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
        .modal-content h3 i { color: #2563eb; margin-right: 0.5rem; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 0.8rem; margin-top: 1.5rem; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.3rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
            font-family: 'Inter', sans-serif;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2563eb;
        }
        
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
        <div class="page-title"><i class="fas fa-users-cog" style="color: #2563eb;"></i> Manajemen Akun</div>
        <button class="btn btn-primary" id="btnTambah"><i class="fas fa-plus"></i> Tambah Akun</button>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): 
                    $role_label = $role_labels[$user['role']] ?? $user['role'];
                    $role_color = $role_colors[$user['role']] ?? '#64748b';
                ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><strong><?= htmlspecialchars($user['name']) ?></strong></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><span class="role-badge" style="background: <?= $role_color ?>20; color: <?= $role_color ?>;"><?= $role_label ?></span></td>
                    <td><?= date('d-m-Y', strtotime($user['created_at'])) ?></td>
                    <td class="action-icons">
                        <a href="#" class="edit-btn" data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>" data-email="<?= htmlspecialchars($user['email']) ?>" data-role="<?= $user['role'] ?>"><i class="fas fa-edit"></i></a>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <a href="#" class="delete-btn" data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>"><i class="fas fa-trash-alt"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div id="modalAkun" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Tambah Akun</h3>
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="editId" value="0">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="name" id="name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group" id="passwordGroup">
                <label>Password (min. 4 karakter)</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="role" required>
                    <option value="admin_tu">Admin TU</option>
                    <option value="dapodik">Admin Dapodik</option>
                    <option value="wakil_kepala_sekolah">Wakil Kepala Sekolah</option>
                    <option value="kepala_sekolah">Kepala Sekolah</option>
                </select>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-outline" id="closeModalAkun">Batal</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const modalAkun = document.getElementById('modalAkun');
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const editId = document.getElementById('editId');
    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const passwordGroup = document.getElementById('passwordGroup');
    const roleSelect = document.getElementById('role');

    function openAkunModal(title, action, id = 0, name = '', email = '', role = '') {
        modalTitle.innerHTML = `<i class="fas ${action === 'tambah' ? 'fa-user-plus' : 'fa-user-edit'}"></i> ${title}`;
        formAction.value = action;
        editId.value = id;
        nameInput.value = name;
        emailInput.value = email;
        roleSelect.value = role;
        
        if (action === 'edit') {
            passwordGroup.style.display = 'block';
            passwordInput.required = false;
            passwordInput.placeholder = 'Kosongkan jika tidak ingin mengubah password';
            passwordInput.value = '';
        } else {
            passwordGroup.style.display = 'block';
            passwordInput.required = true;
            passwordInput.placeholder = '';
        }
        modalAkun.classList.add('show');
    }

    function closeAkunModal() {
        modalAkun.classList.remove('show');
        formAction.value = 'tambah';
        editId.value = 0;
        nameInput.value = '';
        emailInput.value = '';
        passwordInput.value = '';
        roleSelect.value = 'admin_tu';
        passwordInput.required = true;
    }

    document.getElementById('btnTambah').onclick = () => openAkunModal('Tambah Akun', 'tambah');
    document.getElementById('closeModalAkun').onclick = closeAkunModal;

    // Edit button
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            openAkunModal('Edit Akun', 'edit', btn.dataset.id, btn.dataset.name, btn.dataset.email, btn.dataset.role);
        };
    });

    // Delete with SweetAlert2
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            const id = btn.dataset.id;
            const name = btn.dataset.name;
            Swal.fire({
                title: 'Hapus Akun?',
                text: `Yakin ingin menghapus akun "${name}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?delete_id=${id}`;
                }
            });
        };
    });

    // Close modal when clicking outside
    window.onclick = (e) => {
        if (e.target === modalAkun) closeAkunModal();
    };
</script>
</body>
</html>