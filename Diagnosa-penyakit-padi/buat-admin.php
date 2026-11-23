<?php
session_start();
require_once __DIR__ . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama       = trim($_POST['nama_lengkap'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';
    $role       = 'admin'; // Default role langsung diset sebagai admin

    if ($nama === '') {
        $error = 'Nama lengkap wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]{4,50}$/', $username)) {
        $error = 'Username harus 4–50 karakter dan hanya boleh memakai huruf, angka, titik, garis bawah, atau tanda hubung.';
    } elseif (strlen($password) < 6) {
        $error = 'Kata sandi minimal 6 karakter.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (nama_lengkap, username, password, role)
                 VALUES (?, ?, ?, ?)"
            );

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([
                $nama,
                $username,
                $passwordHash,
                $role
            ]);

            // Ambil ID akun yang baru saja dibuat
            $newUserId = $pdo->lastInsertId();

            // Set Session untuk Auto Login & langsung mengarah ke Dashboard (Sesuai Activity Diagram 3.6)
            $_SESSION['user_id']      = $newUserId;
            $_SESSION['username']     = $username;
            $_SESSION['nama_lengkap'] = $nama;
            $_SESSION['role']         = $role;

            header('Location: dashboard.php');
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'Username sudah digunakan. Silakan pilih username lain.';
            } else {
                error_log($e->getMessage());
                $error = 'Akun gagal dibuat. Periksa konfigurasi database.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Up Admin | SI PADI BREBES</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f7f0;
            color: #263b2b;
        }

        .card {
            width: min(440px, calc(100% - 30px));
            margin: 40px auto;
            padding: 28px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(25, 62, 32, 0.1);
        }

        h1 {
            color: #176b3a;
            font-size: 23px;
            margin-top: 0;
        }

        p {
            line-height: 1.6;
            font-size: 13px;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-size: 13px;
            font-weight: bold;
        }

        .password-row {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }

        .password-row input {
            flex: 1;
            min-width: 0;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccd8cd;
            border-radius: 7px;
            font-size: 14px;
            background: white;
        }

        input:focus {
            outline: 2px solid #a5d6a7;
            border-color: #176b3a;
        }

        button {
            border: 0;
            border-radius: 7px;
            background: #176b3a;
            color: white;
            cursor: pointer;
            font-size: 14px;
        }

        .toggle-password {
            width: auto;
            min-width: 45px;
            padding: 0 10px;
            margin: 0;
            background: #e8f5e9;
            border: 1px solid #ccd8cd;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border-radius: 7px;
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
            fill: #176b3a;
        }

        .submit-button {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            font-weight: bold;
        }

        button:hover {
            background: #10552d;
        }

        .toggle-password:hover {
            background: #d0edd2;
        }

        .error {
            color: #b42318;
            background: #fff0ef;
            border-radius: 7px;
            padding: 10px;
            font-size: 13px;
        }

        .note {
            background: #e8f5e9;
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            line-height: 1.5;
        }

        .footer-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }

        .footer-link a {
            color: #176b3a;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>

<body>
<div class="card">
    <h1>🌾 Pendaftaran Akun Admin</h1>

    <p>Isi data berikut untuk mendaftarkan akun Admin baru.</p>

    <div class="note">
        Gunakan kata sandi yang aman dan simpan data login Anda secara rapi.
    </div>

    <?php if ($error !== ''): ?>
        <p class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="post">
        <label for="nama_lengkap">Nama Lengkap</label>
        <input
            type="text"
            id="nama_lengkap"
            name="nama_lengkap"
            required
            maxlength="100"
            value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="username">Username</label>
        <input
            type="text"
            id="username"
            name="username"
            required
            minlength="4"
            maxlength="50"
            pattern="[a-zA-Z0-9_.-]+"
            autocomplete="username"
            value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="password">Kata Sandi</label>
        <div class="password-row">
            <input
                type="password"
                id="password"
                name="password"
                required
                minlength="6"
                autocomplete="new-password"
            >
            <button
                type="button"
                class="toggle-password"
                onclick="togglePassword('password', this)"
                aria-label="Lihat kata sandi"
            >
                <!-- Ikon Mata Tertutup (Default) -->
                <svg class="eye-off" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5 0-.55.1-1.07.27-1.54l1.35 1.35c-.08.38-.12.78-.12 1.19 0 2.21 1.79 4 4 4 .41 0 .81-.04 1.19-.12l1.35 1.35c-.47.17-.99.27-1.54.27zm6.5-7.48L19.9 11c-.56-1.37-1.42-2.58-2.5-3.52l1.08-1.08c1.37 1.2 2.47 2.72 3.02 4.4zM3.27 4.27L4.54 5.54C6.26 4.58 8.06 4 10 4c4.64 0 8.65 2.57 10.55 6.35-.45.91-1.06 1.74-1.78 2.46l1.27 1.27c1.02-.99 1.87-2.13 2.5-3.38C21.27 7.61 17 4.5 12 4.5c-1.94 0-3.74.58-5.46 1.54l1.27 1.27z"/></svg>
            </button>
        </div>

        <label for="konfirmasi">Ulangi Kata Sandi</label>
        <div class="password-row">
            <input
                type="password"
                id="konfirmasi"
                name="konfirmasi"
                required
                minlength="6"
                autocomplete="new-password"
            >
            <button
                type="button"
                class="toggle-password"
                onclick="togglePassword('konfirmasi', this)"
                aria-label="Lihat konfirmasi kata sandi"
            >
                <!-- Ikon Mata Tertutup (Default) -->
                <svg class="eye-off" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5 0-.55.1-1.07.27-1.54l1.35 1.35c-.08.38-.12.78-.12 1.19 0 2.21 1.79 4 4 4 .41 0 .81-.04 1.19-.12l1.35 1.35c-.47.17-.99.27-1.54.27zm6.5-7.48L19.9 11c-.56-1.37-1.42-2.58-2.5-3.52l1.08-1.08c1.37 1.2 2.47 2.72 3.02 4.4zM3.27 4.27L4.54 5.54C6.26 4.58 8.06 4 10 4c4.64 0 8.65 2.57 10.55 6.35-.45.91-1.06 1.74-1.78 2.46l1.27 1.27c1.02-.99 1.87-2.13 2.5-3.38C21.27 7.61 17 4.5 12 4.5c-1.94 0-3.74.58-5.46 1.54l1.27 1.27z"/></svg>
            </button>
        </div>

        <button type="submit" class="submit-button">
            Daftar
        </button>
    </form>

    <div class="footer-link">
        Sudah memiliki akun? <a href="login.php">Login di sini</a>
    </div>
</div>

<script>
// Menyimpan referensi timeout untuk masing-masing input
const passwordTimeouts = {};

function togglePassword(id, button) {
    const input = document.getElementById(id);
    
    // Hapus timeout sebelumnya jika tombol diklik lagi sebelum 3 detik
    if (passwordTimeouts[id]) {
        clearTimeout(passwordTimeouts[id]);
        delete passwordTimeouts[id];
    }

    if (input.type === 'password') {
        // Ubah jadi text (terlihat)
        input.type = 'text';
        button.innerHTML = `<svg class="eye-on" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>`;
        button.setAttribute('aria-label', 'Sembunyikan kata sandi');

        // Timer otomatis sembunyikan dalam 3 detik (3000 ms)
        passwordTimeouts[id] = setTimeout(() => {
            input.type = 'password';
            button.innerHTML = `<svg class="eye-off" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5 0-.55.1-1.07.27-1.54l1.35 1.35c-.08.38-.12.78-.12 1.19 0 2.21 1.79 4 4 4 .41 0 .81-.04 1.19-.12l1.35 1.35c-.47.17-.99.27-1.54.27zm6.5-7.48L19.9 11c-.56-1.37-1.42-2.58-2.5-3.52l1.08-1.08c1.37 1.2 2.47 2.72 3.02 4.4zM3.27 4.27L4.54 5.54C6.26 4.58 8.06 4 10 4c4.64 0 8.65 2.57 10.55 6.35-.45.91-1.06 1.74-1.78 2.46l1.27 1.27c1.02-.99 1.87-2.13 2.5-3.38C21.27 7.61 17 4.5 12 4.5c-1.94 0-3.74.58-5.46 1.54l1.27 1.27z"/></svg>`;
            button.setAttribute('aria-label', 'Lihat kata sandi');
            delete passwordTimeouts[id];
        }, 3000);

    } else {
        // Jika diklik manual saat sedang terbuka
        input.type = 'password';
        button.innerHTML = `<svg class="eye-off" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5 0-.55.1-1.07.27-1.54l1.35 1.35c-.08.38-.12.78-.12 1.19 0 2.21 1.79 4 4 4 .41 0 .81-.04 1.19-.12l1.35 1.35c-.47.17-.99.27-1.54.27zm6.5-7.48L19.9 11c-.56-1.37-1.42-2.58-2.5-3.52l1.08-1.08c1.37 1.2 2.47 2.72 3.02 4.4zM3.27 4.27L4.54 5.54C6.26 4.58 8.06 4 10 4c4.64 0 8.65 2.57 10.55 6.35-.45.91-1.06 1.74-1.78 2.46l1.27 1.27c1.02-.99 1.87-2.13 2.5-3.38C21.27 7.61 17 4.5 12 4.5c-1.94 0-3.74.58-5.46 1.54l1.27 1.27z"/></svg>`;
        button.setAttribute('aria-label', 'Lihat kata sandi');
    }
}
</script>
</body>
</html>