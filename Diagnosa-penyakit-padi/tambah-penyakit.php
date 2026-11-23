<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

wajibRole(['admin']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_tambah_penyakit'])) {
    $_SESSION['csrf_tambah_penyakit'] = bin2hex(random_bytes(32));
}

$pesan = '';
$kode = '';
$nama = '';
$solusi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_tambah_penyakit'], $token)
    ) {
        $pesan = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $kode = strtoupper(trim($_POST['kode_penyakit'] ?? ''));
        $nama = trim($_POST['nama_penyakit'] ?? '');
        $solusi = trim($_POST['solusi'] ?? '');

        if ($kode === '' || $nama === '' || $solusi === '') {
            $pesan = 'Semua kolom wajib diisi.';
        } else {
            try {
                $cek = $pdo->prepare(
                    'SELECT COUNT(*) FROM penyakit
                     WHERE kode_penyakit = ? OR nama_penyakit = ?'
                );
                $cek->execute([$kode, $nama]);

                if ((int) $cek->fetchColumn() > 0) {
                    $pesan = 'Kode atau nama penyakit sudah terdaftar.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO penyakit
                         (kode_penyakit, nama_penyakit, solusi)
                         VALUES (?, ?, ?)'
                    );

                    $stmt->execute([
                        $kode,
                        $nama,
                        $solusi
                    ]);

                    header('Location: penyakit.php?pesan=tambah');
                    exit;
                }
            } catch (PDOException $e) {
                $pesan = 'Data gagal disimpan. Periksa kembali data dan struktur database.';
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
    <title>Tambah Penyakit - SI PADI BREBES</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 30px 16px;
            font-family: Arial, sans-serif;
            background: #f3f7f3;
            color: #24352a;
        }
        .container {
            max-width: 720px;
            margin: 0 auto;
        }
        .header { margin-bottom: 22px; }
        .header h1 { margin: 0 0 8px; font-size: 26px; }
        .header p { margin: 0; color: #68776c; line-height: 1.5; }
        .card {
            background: white;
            padding: 26px;
            border-radius: 14px;
            box-shadow: 0 5px 22px rgba(25, 70, 35, .07);
        }
        .field { margin-bottom: 19px; }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        input, textarea, select {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #d5dfd6;
            border-radius: 8px;
            font: inherit;
            background: white;
            color: #24352a;
        }
        input:focus, textarea:focus, select:focus {
            outline: 2px solid #b6d9bd;
            border-color: #278343;
        }
        textarea { min-height: 105px; resize: vertical; }
        .hint { color: #718075; font-size: 13px; margin-top: 7px; }
        .alert {
            padding: 12px 14px;
            background: #fff0ef;
            color: #a12b25;
            border-radius: 8px;
            margin-bottom: 18px;
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 24px;
        }
        .btn {
            display: inline-block;
            border: 0;
            border-radius: 8px;
            padding: 12px 17px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .primary { background: #24783b; color: white; }
        .primary:hover { background: #1b602e; }
        .secondary { background: #edf1ed; color: #34463a; }
        @media (max-width: 500px) {
            .card { padding: 19px; }
            .header h1 { font-size: 23px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Tambah Data Penyakit</h1>
        <p>Tambahkan informasi penyakit tanaman padi ke dalam basis pengetahuan SI PADI BREBES.</p>
    </div>

    <div class="card">
        <?php if ($pesan !== ''): ?>
            <div class="alert"><?= htmlspecialchars($pesan) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_tambah_penyakit']) ?>">

            <div class="field">
                <label for="kode_penyakit">Kode Penyakit</label>
                <input type="text" id="kode_penyakit" name="kode_penyakit"
                       placeholder="Contoh: P011"
                       value="<?= htmlspecialchars($kode) ?>" maxlength="20" required>
                <div class="hint">Gunakan kode yang belum terdaftar, misalnya P011.</div>
            </div>

            <div class="field">
                <label for="nama_penyakit">Nama Penyakit</label>
                <input type="text" id="nama_penyakit" name="nama_penyakit"
                       placeholder="Masukkan nama penyakit"
                       value="<?= htmlspecialchars($nama) ?>" required>
            </div>

            <div class="field">
                <label for="solusi">Solusi / Pengendalian</label>
                <textarea id="solusi" name="solusi"
                          placeholder="Masukkan cara pengendalian atau penanganan penyakit..."
                          required><?= htmlspecialchars($solusi) ?></textarea>
            </div>

            <div class="actions">
                <button class="btn primary" type="submit">Simpan Penyakit</button>
                <a class="btn secondary" href="penyakit.php">Kembali</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>