
<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

wajibRole(['admin']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_gejala'])) {
    $_SESSION['csrf_gejala'] = bin2hex(random_bytes(32));
}

$pesan = '';
$kode = '';
$nama = '';
$bobot = '1';
$keterangan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_gejala'], $token)) {
        $pesan = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $kode = strtoupper(trim($_POST['kode_gejala'] ?? ''));
        $nama = trim($_POST['nama_gejala'] ?? '');
        $bobot = $_POST['bobot'] ?? '1';
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($kode === '' || $nama === '') {
            $pesan = 'Kode dan nama gejala wajib diisi.';
        } elseif (!in_array($bobot, ['1', '3', '5'], true)) {
            $pesan = 'Bobot harus bernilai 1, 3, atau 5.';
        } else {
            $stmt = $conn->prepare(
                'SELECT id FROM gejala WHERE kode_gejala = ? LIMIT 1'
            );

            if (!$stmt) {
                $pesan = 'Gagal memeriksa kode gejala: ' . $conn->error;
            } else {
                $stmt->bind_param('s', $kode);
                $stmt->execute();
                $hasil = $stmt->get_result();

                if ($hasil->num_rows > 0) {
                    $pesan = 'Kode gejala sudah digunakan.';
                } else {
                    $stmtInsert = $conn->prepare(
                        'INSERT INTO gejala
                        (kode_gejala, nama_gejala, bobot, keterangan)
                        VALUES (?, ?, ?, ?)'
                    );

                    if (!$stmtInsert) {
                        $pesan = 'Gagal menyiapkan penyimpanan data.';
                    } else {
                        $nilaiBobot = (float) $bobot;
                        $stmtInsert->bind_param(
                            'ssds',
                            $kode,
                            $nama,
                            $nilaiBobot,
                            $keterangan
                        );

                        if ($stmtInsert->execute()) {
                            header('Location: gejala.php?pesan=tambah');
                            exit;
                        }

                        $pesan = 'Data gagal disimpan: ' . $stmtInsert->error;
                        $stmtInsert->close();
                    }
                }

                $stmt->close();
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
    <title>Tambah Gejala - SI PADI BREBES</title>
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
        <h1>Tambah Data Gejala</h1>
        <p>Tambahkan gejala penyakit tanaman padi ke dalam basis pengetahuan SI PADI BREBES.</p>
    </div>

    <div class="card">
        <?php if ($pesan !== ''): ?>
            <div class="alert"><?= htmlspecialchars($pesan) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_gejala']) ?>">

            <div class="field">
                <label for="kode_gejala">Kode Gejala</label>
                <input type="text" id="kode_gejala" name="kode_gejala"
                       placeholder="Contoh: G031"
                       value="<?= htmlspecialchars($kode) ?>" required>
                <div class="hint">Gunakan kode yang belum terdaftar, misalnya G031.</div>
            </div>

            <div class="field">
                <label for="nama_gejala">Nama / Deskripsi Gejala</label>
                <textarea id="nama_gejala" name="nama_gejala"
                          placeholder="Masukkan ciri-ciri gejala tanaman padi"
                          required><?= htmlspecialchars($nama) ?></textarea>
            </div>

            <div class="field">
                <label for="bobot">Bobot Gejala</label>
                <select id="bobot" name="bobot" required>
                    <option value="1" <?= $bobot === '1' ? 'selected' : '' ?>>1 - Biasa</option>
                    <option value="3" <?= $bobot === '3' ? 'selected' : '' ?>>3 - Sedang</option>
                    <option value="5" <?= $bobot === '5' ? 'selected' : '' ?>>5 - Penting</option>
                </select>
            </div>

            <div class="field">
                <label for="keterangan">Keterangan (Opsional)</label>
                <textarea id="keterangan" name="keterangan"
                          placeholder="Keterangan tambahan jika ada"><?= htmlspecialchars($keterangan) ?></textarea>
            </div>

            <div class="actions">
                <button class="btn primary" type="submit">Simpan Gejala</button>
                <a class="btn secondary" href="gejala.php">Kembali</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
