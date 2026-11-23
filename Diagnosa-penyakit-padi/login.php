<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Jika user sudah login, arahkan ke dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan kata sandi wajib diisi.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, nama_lengkap, username, password, role
             FROM users
             WHERE username = ?
             LIMIT 1"
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Username atau kata sandi salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Sistem Pakar Padi</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #edf5ed;
            color: #263b2b;
        }
        .card {
            width: 100%;
            max-width: 410px;
            padding: 30px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 30px #193e2012;
        }
        .logo { font-size: 38px; }
        h1 { color: #176b3a; font-size: 24px; margin-bottom: 8px; }
        .desc { color: #647267; font-size: 14px; line-height: 1.6; }
        label {
            display: block;
            margin: 18px 0 7px;
            font-size: 14px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccd8cd;
            border-radius: 8px;
            font-size: 14px;
        }
        input:focus { outline: 2px solid #a7d8b1; }
        button {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            border: 0;
            border-radius: 8px;
            background: #176b3a;
            color: white;
            font-size: 15px;
            cursor: pointer;
        }
        button:hover { background: #12552e; }
        .error {
            margin-top: 16px;
            padding: 11px;
            border-radius: 7px;
            background: #fdecec;
            color: #a32121;
            font-size: 13px;
        }
        .footer {
            margin-top: 22px;
            text-align: center;
            color: #748076;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">🌾</div>
    <h1>Selamat Datang</h1>
    <p class="desc">
        Sistem Pakar Diagnosis Penyakit pada Tanaman Padi
        Kabupaten Brebes. Silakan login untuk melanjutkan.
    </p>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="post" action="login.php">
        <label for="username">Username</label>
        <input
            type="text"
            id="username"
            name="username"
            maxlength="50"
            autocomplete="username"
            required
        >

        <label for="password">Kata sandi</label>
        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">Login</button>
    </form>

    <div class="footer">
        Sistem Pakar Diagnosis Penyakit Tanaman Padi
    </div>
</div>
</body>
</html>