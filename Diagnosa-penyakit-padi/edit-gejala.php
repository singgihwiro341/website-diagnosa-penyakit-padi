
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

function h($nilai)
{
    return htmlspecialchars((string) ($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: gejala.php?pesan=tidak_ditemukan');
    exit;
}

$pesan = '';
$jenisPesan = 'error';

/* =========================
   AMBIL DATA GEJALA
========================= */
$stmt = $pdo->prepare(
    'SELECT id, kode_gejala, nama_gejala, bobot, keterangan
     FROM gejala
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute(['id' => $id]);
$gejala = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gejala) {
    header('Location: gejala.php?pesan=tidak_ditemukan');
    exit;
}

/* =========================
   PROSES SIMPAN PERUBAHAN
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    $kode = strtoupper(trim($_POST['kode_gejala'] ?? ''));
    $nama = trim($_POST['nama_gejala'] ?? '');
    $bobotInput = $_POST['bobot'] ?? '';
    $keterangan = trim($_POST['keterangan'] ?? '');

    // Pertahankan input yang dikirim ketika validasi gagal.
    $gejala['kode_gejala'] = $kode;
    $gejala['nama_gejala'] = $nama;
    $gejala['bobot'] = $bobotInput;
    $gejala['keterangan'] = $keterangan;

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_gejala'], $token)
    ) {
        $pesan = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } elseif ($kode === '' || $nama === '' || $bobotInput === '') {
        $pesan = 'Kode gejala, nama gejala, dan bobot wajib diisi.';
    } elseif (!is_numeric($bobotInput)) {
        $pesan = 'Bobot harus berupa angka.';
    } elseif ((float) $bobotInput < 0 || (float) $bobotInput > 1) {
        $pesan = 'Bobot harus berada di antara 0 dan 1.';
    } else {
        try {
            // Periksa apakah kode digunakan oleh gejala lain.
            $stmtCek = $pdo->prepare(
                'SELECT id FROM gejala
                 WHERE kode_gejala = :kode AND id <> :id
                 LIMIT 1'
            );

            $stmtCek->execute([
                'kode' => $kode,
                'id' => $id
            ]);

            if ($stmtCek->fetch()) {
                $pesan = 'Kode gejala sudah digunakan. Gunakan kode lain.';
            } else {
                $stmtUpdate = $pdo->prepare(
                    'UPDATE gejala
                     SET kode_gejala = :kode,
                         nama_gejala = :nama,
                         bobot = :bobot,
                         keterangan = :keterangan
                     WHERE id = :id'
                );

                $stmtUpdate->execute([
                    'kode' => $kode,
                    'nama' => $nama,
                    'bobot' => (float) $bobotInput,
                    'keterangan' => $keterangan !== '' ? $keterangan : null,
                    'id' => $id
                ]);

                header('Location: gejala.php?pesan=edit');
                exit;
            }
        } catch (PDOException $e) {
            $pesan = 'Gagal menyimpan perubahan gejala. Periksa kembali data dan koneksi database.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Gejala - SI PADI BREBES</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 28px;
            background: #f3f7f3;
            color: #24352a;
            font-family: Arial, Helvetica, sans-serif;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 22px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 27px;
        }

        .header p {
            margin: 0;
            color: #718075;
            line-height: 1.5;
        }

        .panel {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 5px 22px rgba(25, 70, 35, .06);
            overflow: hidden;
        }

        .panel-top {
            padding: 20px 22px;
            border-bottom: 1px solid #edf1ed;
        }

        .panel-top h2 {
            margin: 0;
            font-size: 18px;
        }

        .form-body {
            padding: 24px 22px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #34463a;
        }

        .required {
            color: #b42318;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #dce5dc;
            border-radius: 8px;
            background: #fff;
            color: #24352a;
            font: inherit;
            font-size: 14px;
            outline: none;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #24783b;
            box-shadow: 0 0 0 3px rgba(36, 120, 59, .10);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .help {
            display: block;
            margin-top: 7px;
            color: #718075;
            font-size: 12px;
            line-height: 1.5;
        }

        .alert {
            margin-bottom: 20px;
            padding: 13px 16px;
            border-radius: 9px;
            color: #a12b25;
            background: #fff0ef;
            font-size: 14px;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            padding-top: 6px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 11px 16px;
            border: 0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-simpan {
            background: #24783b;
            color: white;
        }

        .btn-simpan:hover {
            background: #1b602e;
        }

        .btn-kembali {
            background: #edf1ed;
            color: #34463a;
        }

        .footer {
            padding: 15px 22px;
            color: #718075;
            font-size: 12px;
            border-top: 1px solid #edf1ed;
        }

        @media (max-width: 650px) {
            body {
                padding: 17px 12px;
            }

            .header h1 {
                font-size: 23px;
            }

            .form-body {
                padding: 18px 16px;
            }

            .actions .btn {
                flex: 1;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <div class="header">
        <h1>Edit Gejala</h1>
        <p>Perbarui informasi gejala untuk diagnosis penyakit tanaman padi.</p>
    </div>

    <div class="panel">
        <div class="panel-top">
            <h2>Form Edit Gejala</h2>
        </div>

        <div class="form-body">

            <?php if ($pesan !== ''): ?>
                <div class="alert">
                    <?= h($pesan) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?= h($_SESSION['csrf_gejala']) ?>">

                <div class="form-group">
                    <label for="kode_gejala">
                        Kode Gejala <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="kode_gejala"
                        name="kode_gejala"
                        value="<?= h($gejala['kode_gejala']) ?>"
                        maxlength="20"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="nama_gejala">
                        Nama Gejala <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="nama_gejala"
                        name="nama_gejala"
                        value="<?= h($gejala['nama_gejala']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="bobot">
                        Bobot Gejala <span class="required">*</span>
                    </label>

                    <select id="bobot" name="bobot" required>
                        <option value="1"
                            <?= (string) $gejala['bobot'] === '1' ? 'selected' : '' ?>>
                            1 - Biasa
                        </option>

                        <option value="3"
                            <?= (string) $gejala['bobot'] === '3' ? 'selected' : '' ?>>
                            3 - Sedang
                        </option>

                        <option value="5"
                            <?= (string) $gejala['bobot'] === '5' ? 'selected' : '' ?>>
                            5 - Penting
                        </option>
                    </select>

                    <span class="help">
                        Pilih bobot sesuai tingkat kepentingan gejala berdasarkan penilaian pakar.
                    </span>
                </div>

                <div class="form-group">
                    <label for="keterangan">Keterangan</label>

                    <textarea
                        id="keterangan"
                        name="keterangan"
                        placeholder="Masukkan keterangan tambahan jika diperlukan..."
                    ><?= h($gejala['keterangan']) ?></textarea>
                </div>

                <div class="actions">
                    <a href="gejala.php" class="btn btn-kembali">
                        Kembali
                    </a>

                    <button type="submit" class="btn btn-simpan">
                        Simpan Perubahan
                    </button>
                </div>
            </form>

        </div>

        <div class="footer">
            SI PADI BREBES · Sistem Pakar Diagnosis Penyakit Tanaman Padi
        </div>
    </div>

</div>
</body>
</html>
