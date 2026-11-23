<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

wajibRole(['admin']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$asalHalaman = $_GET['from'] ?? $_POST['from'] ?? 'kasus';

$linkKembali = ($asalHalaman === 'dashboard')
    ? 'dashboard.php'
    : 'kasus.php';

function h($nilai)
{
    return htmlspecialchars((string) ($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

$penyakit = $pdo->query(
    "SELECT id, kode_penyakit, nama_penyakit
     FROM penyakit ORDER BY kode_penyakit"
)->fetchAll();

$gejala = $pdo->query(
    "SELECT id, kode_gejala, nama_gejala
     FROM gejala ORDER BY kode_gejala"
)->fetchAll();

$pesan = '';
$error = '';
$jenisPesan = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = strtoupper(trim($_POST['kode_kasus'] ?? ''));
    $penyakitId = (int) ($_POST['penyakit_id'] ?? 0);
    $sumber = trim($_POST['sumber_data'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    $gejalaDipilih = $_POST['gejala'] ?? [];

    if (
        !preg_match('/^[A-Z0-9-]{1,20}$/', $kode) ||
        $penyakitId < 1 ||
        !is_array($gejalaDipilih) ||
        count($gejalaDipilih) === 0
    ) {
        $error = 'Isi kode kasus, pilih penyakit, dan pilih minimal satu gejala.';
    } else {
        $cek = $pdo->prepare(
            "SELECT id FROM penyakit WHERE id = ?"
        );
        $cek->execute([$penyakitId]);

        if (!$cek->fetch()) {
            $error = 'Penyakit yang dipilih tidak ditemukan.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO kasus
                    (kode_kasus, penyakit_id, sumber_data, catatan, status_validasi)
                    VALUES (?, ?, ?, ?, 'draft')"
                );

                $stmt->execute([
                    $kode,
                    $penyakitId,
                    $sumber !== '' ? $sumber : null,
                    $catatan !== '' ? $catatan : null
                ]);

                $kasusId = (int) $pdo->lastInsertId();

                $stmtGejala = $pdo->prepare(
                    "INSERT INTO kasus_gejala (kasus_id, gejala_id)
                     VALUES (?, ?)"
                );

                $cekGejala = $pdo->prepare(
                    "SELECT id FROM gejala WHERE id = ?"
                );

                foreach ($gejalaDipilih as $idGejala) {
                    $idGejala = filter_var(
                        $idGejala,
                        FILTER_VALIDATE_INT
                    );

                    if (!$idGejala) {
                        throw new RuntimeException('Gejala tidak valid.');
                    }

                    $cekGejala->execute([$idGejala]);

                    if (!$cekGejala->fetch()) {
                        throw new RuntimeException('Gejala tidak ditemukan.');
                    }

                    $stmtGejala->execute([$kasusId, $idGejala]);
                }

                $pdo->commit();
                $pesan = 'Kasus berhasil disimpan sebagai draft. Kasus belum boleh digunakan untuk diagnosis sampai divalidasi oleh pakar.';
                $jenisPesan = 'success';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($e instanceof PDOException &&
                    $e->getCode() === '23000') {
                    $error = 'Kode kasus sudah digunakan. Gunakan kode lain.';
                } else {
                    error_log($e->getMessage());
                    $error = 'Kasus gagal disimpan. Periksa data dan coba lagi.';
                }
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
    <title>Tambah Kasus CBR - SI PADI BREBES</title>
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
            max-width: 1250px;
            margin: 0 auto;
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 27px;
            color: #176534;
        }

        .header p {
            margin: 0;
            color: #718075;
            line-height: 1.5;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* TOMBOL */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 11px 15px;
            border: 0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s ease;
        }

        .btn-kembali {
            background: #e2f2e5;
            color: #176534;
            border: 1px solid #b9dfc1;
        }

        .btn-kembali:hover {
            background: #cce9d2;
        }

        .btn-tambah {
            background: #24783b;
            color: white;
        }

        .btn-tambah:hover {
            background: #1b602e;
        }

        /* PANEL */
        .panel {
            background: white;
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
            color: #176534;
        }

        .panel-body {
            padding: 22px;
        }

        /* FORM & INPUT */
        label {
            display: block;
            margin: 18px 0 7px;
            font-weight: 600;
            font-size: 14px;
            color: #24352a;
        }

        input[type="text"], select, textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd8cc;
            border-radius: 8px;
            font: inherit;
            font-size: 14px;
            color: #24352a;
            background: #fff;
        }

        input[type="text"]:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #24783b;
            box-shadow: 0 0 0 3px rgba(36, 120, 59, 0.1);
        }

        .gejala-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #edf1ed;
            border-radius: 8px;
            padding: 12px;
            background: #fbfdfb;
        }

        .gejala {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px solid #edf1ed;
        }

        .gejala:last-child {
            border-bottom: 0;
        }

        .gejala input[type="checkbox"] {
            width: 16px;
            height: 16px;
            margin-top: 3px;
            cursor: pointer;
            accent-color: #24783b;
        }

        .gejala label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
            line-height: 1.5;
        }

        /* ALERT / CATATAN */
        .alert {
            margin: 0 0 18px;
            padding: 13px 16px;
            border-radius: 9px;
            line-height: 1.5;
            font-size: 14px;
        }

        .alert.success {
            color: #176534;
            background: #e8f7ec;
        }

        .alert.error {
            color: #a12b25;
            background: #fff0ef;
        }

        .catatan {
            background: #fdf6e2;
            color: #7a5800;
            padding: 14px 16px;
            border-radius: 9px;
            line-height: 1.5;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .footer {
            padding: 15px 22px;
            color: #718075;
            font-size: 12px;
            border-top: 1px solid #edf1ed;
        }

        /* TAMPILAN HP */
        @media (max-width: 650px) {
            body {
                padding: 17px 12px;
            }

            .header h1 {
                font-size: 23px;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <div>
            <h1>Tambah Kasus CBR</h1>
            <p>Masukkan kasus penyakit padi beserta gejalanya.</p>
        </div>

        <div class="header-actions">
            <a href="<?= h($linkKembali) ?>" class="btn btn-kembali">
                Kembali
            </a>
        </div>
    </div>

    <div class="panel">
        <div class="panel-top">
            <h2>Form Input Kasus Baru</h2>
        </div>

        <div class="panel-body">
            <div class="catatan">
                Kasus baru otomatis berstatus <strong>draft</strong>. Pastikan penyakit dan gejalanya sesuai hasil wawancara pakar atau sumber yang dapat diverifikasi.
            </div>

            <?php if ($pesan !== ''): ?>
                <div class="alert success">
                    <?= h($pesan) ?> 
                    <a href="kasus.php" style="color: #176534; font-weight: bold; text-decoration: underline;">Lihat daftar kasus</a>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <label for="kode_kasus">Kode Kasus</label>
                <input
                    type="text"
                    id="kode_kasus"
                    name="kode_kasus"
                    placeholder="Contoh: K001"
                    maxlength="20"
                    value="<?= h($_POST['kode_kasus'] ?? '') ?>"
                    required
                >

                <label for="penyakit_id">Penyakit</label>
                <select id="penyakit_id" name="penyakit_id" required>
                    <option value="">-- Pilih penyakit --</option>
                    <?php foreach ($penyakit as $item): ?>
                        <option value="<?= (int) $item['id'] ?>" <?= ((int)($_POST['penyakit_id'] ?? 0) === (int)$item['id']) ? 'selected' : '' ?>>
                            <?= h($item['kode_penyakit'] . ' - ' . $item['nama_penyakit']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="sumber_data">Sumber Data</label>
                <input
                    type="text"
                    id="sumber_data"
                    name="sumber_data"
                    placeholder="Contoh: wawancara penyuluh, tanggal wawancara"
                    value="<?= h($_POST['sumber_data'] ?? '') ?>"
                >

                <label>Gejala pada Kasus</label>
                <div class="gejala-list">
                    <?php foreach ($gejala as $item): ?>
                        <div class="gejala">
                            <input
                                type="checkbox"
                                id="g<?= (int) $item['id'] ?>"
                                name="gejala[]"
                                value="<?= (int) $item['id'] ?>"
                                <?= (isset($_POST['gejala']) && in_array((string)$item['id'], $_POST['gejala'], true)) ? 'checked' : '' ?>
                            >
                            <label for="g<?= (int) $item['id'] ?>">
                                <strong><?= h($item['kode_gejala']) ?></strong> - <?= h($item['nama_gejala']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <label for="catatan">Catatan Tambahan</label>
                <textarea
                    id="catatan"
                    name="catatan"
                    rows="3"
                    placeholder="Catatan hasil pengumpulan data (opsional)"
                ><?= h($_POST['catatan'] ?? '') ?></textarea>

                <button type="submit" class="btn btn-tambah" style="margin-top: 24px; padding: 12px 20px; font-size: 15px;">
                    Simpan Kasus sebagai Draft
                </button>
            </form>
        </div>

        <div class="footer">
            SI PADI BREBES · Sistem Pakar Diagnosis Penyakit Tanaman Padi
        </div>
    </div>
</div>
</body>
</html>