<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

wajibRole(['admin']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_penyakit'])) {
    $_SESSION['csrf_penyakit'] = bin2hex(random_bytes(32));
}

function h($nilai)
{
    return htmlspecialchars((string) ($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

$pesan = '';
$jenisPesan = 'info';

/* =========================
   PROSES HAPUS PENYAKIT
========================= */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['aksi'] ?? '') === 'hapus'
) {
    $token = $_POST['csrf_token'] ?? '';
    $pilihan = $_POST['penyakit_id'] ?? [];

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_penyakit'], $token)
    ) {
        $pesan = 'Permintaan tidak valid. Silakan muat ulang halaman.';
        $jenisPesan = 'error';
    } elseif (!is_array($pilihan) || count($pilihan) === 0) {
        $pesan = 'Pilih minimal satu penyakit yang ingin dihapus.';
        $jenisPesan = 'error';
    } else {
        $ids = [];

        foreach ($pilihan as $nilai) {
            $id = filter_var($nilai, FILTER_VALIDATE_INT);

            if ($id !== false && $id > 0) {
                $ids[] = (int) $id;
            }
        }

        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            $pesan = 'Data penyakit yang dipilih tidak valid.';
            $jenisPesan = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                $stmtHapus = $pdo->prepare(
                    'DELETE FROM penyakit WHERE id = :id'
                );

                $jumlahTerhapus = 0;

                foreach ($ids as $id) {
                    $stmtHapus->execute(['id' => $id]);
                    $jumlahTerhapus += $stmtHapus->rowCount();
                }

                $pdo->commit();

                if ($jumlahTerhapus > 0) {
                    $pesan = $jumlahTerhapus .
                        ' data penyakit berhasil dihapus.';
                    $jenisPesan = 'success';
                } else {
                    $pesan = 'Tidak ada data penyakit yang dihapus.';
                    $jenisPesan = 'info';
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $pesan = 'Data penyakit tidak dapat dihapus. Pastikan penyakit tersebut tidak sedang digunakan oleh data kasus atau riwayat diagnosis.';
                $jenisPesan = 'error';
            }
        }
    }
}

/* =========================
   PESAN TAMBAH / EDIT
========================= */
if (isset($_GET['pesan'])) {
    switch ($_GET['pesan']) {
        case 'tambah':
            $pesan = 'Data penyakit berhasil ditambahkan.';
            $jenisPesan = 'success';
            break;

        case 'edit':
            $pesan = 'Data penyakit berhasil diperbarui.';
            $jenisPesan = 'success';
            break;

        case 'tidak_ditemukan':
            $pesan = 'Data penyakit tidak ditemukan.';
            $jenisPesan = 'error';
            break;
    }
}

/* =========================
   AMBIL DATA PENYAKIT
========================= */
$stmt = $pdo->query(
    'SELECT id, kode_penyakit, nama_penyakit, solusi
     FROM penyakit
     ORDER BY kode_penyakit ASC'
);

$penyakit = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalPenyakit = count($penyakit);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Penyakit - SI PADI BREBES</title>

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

        /* BANNER SAMA SEPERTI GEJALA */
        .welcome-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 30px;
            color: white;
            border-radius: 16px;
            background: linear-gradient(115deg, #176b3a, #31945a);
            overflow: hidden;
            margin-bottom: 22px;
        }

        .welcome-banner h1 {
            margin: 0 0 10px;
            font-size: 30px;
            color: white;
        }

        .welcome-banner p {
            max-width: 580px;
            margin: 0;
            color: #e2f3e6;
            font-size: 13px;
            line-height: 1.7;
        }

        .welcome-art {
            font-size: 65px;
            opacity: .9;
        }

        .sub-brand {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #c8dfcf;
            margin-bottom: 6px;
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
            font-family: inherit;
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

        .btn-hapus {
            background: #fff0ef;
            color: #b42318;
            border: 1px solid #f2c8c5;
        }

        .btn-hapus:hover {
            background: #ffe2df;
        }

        .btn-batal {
            background: #edf1ed;
            color: #34463a;
        }

        .btn-konfirmasi {
            background: #b42318;
            color: white;
        }

        .btn-konfirmasi:hover:not(:disabled) {
            background: #921c14;
        }

        .btn-konfirmasi:disabled {
            cursor: not-allowed;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            border-bottom: 1px solid #edf1ed;
        }

        .panel-top h2 {
            margin: 0;
            font-size: 18px;
            color: #176534;
        }

        .total {
            color: #176534;
            background: #e2f2e5;
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* PESAN */
        .alert {
            margin: 0 0 18px;
            padding: 13px 16px;
            border-radius: 9px;
            line-height: 1.5;
        }

        .alert.success {
            color: #176534;
            background: #e8f7ec;
        }

        .alert.error {
            color: #a12b25;
            background: #fff0ef;
        }

        .alert.info {
            color: #245b87;
            background: #edf6ff;
        }

        /* TABEL */
        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 820px;
        }

        th,
        td {
            padding: 15px 17px;
            text-align: left;
            border-bottom: 1px solid #edf1ed;
            vertical-align: middle;
            line-height: 1.5;
        }

        th {
            background: #e8f4e9;
            color: #245b32;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .4px;
            white-space: nowrap;
        }

        td {
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f0f8f1;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .kode {
            color: #24783b;
            font-weight: 700;
            white-space: nowrap;
        }

        .nama-cell {
            min-width: 200px;
        }

        .nama-text {
            display: block;
        }

        /* IKON EDIT */
        .edit-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 32px;
            width: 32px;
            height: 32px;
            border-radius: 7px;
            background: #edf7ee;
            color: #24783b;
            text-decoration: none;
            transition: background .2s ease;
        }

        .edit-icon:hover {
            background: #d8efdc;
        }

        .edit-icon svg {
            width: 16px;
            height: 16px;
        }

        .aksi-header,
        .aksi-cell {
            width: 75px;
            min-width: 75px;
            text-align: center;
            white-space: nowrap;
        }

        .aksi-cell .edit-icon {
            margin: 0 auto;
        }

        /* HAPUS BANYAK DATA */
        .checkbox-cell {
            display: none;
            width: 42px;
        }

        .mode-hapus .checkbox-cell {
            display: table-cell;
        }

        .checkbox-cell input {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #24783b;
        }

        .toolbar {
            display: flex;
            gap: 9px;
            align-items: center;
            flex-wrap: wrap;
        }

        .empty {
            text-align: center;
            color: #718075;
            padding: 38px 20px;
        }

        .footer {
            padding: 15px 22px;
            color: #718075;
            font-size: 12px;
            border-top: 1px solid #edf1ed;
        }

        [hidden] {
            display: none !important;
        }

        /* TOMBOL DI BAGIAN PALING BAWAH */
        .bottom-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 18px 22px;
            margin-top: 18px;
            border: 1px solid #e7eee7;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 5px 22px rgba(25, 70, 35, .04);
        }

        .bottom-left,
        .bottom-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* TAMPILAN HP */
        @media (max-width: 650px) {
            body {
                padding: 17px 12px;
            }

            .welcome-banner {
                padding: 22px;
            }

            .welcome-banner h1 {
                font-size: 23px;
            }

            .welcome-art {
                font-size: 48px;
            }

            .panel-top {
                padding: 16px;
            }

            .toolbar {
                width: 100%;
            }

            .bottom-actions {
                padding: 16px;
            }

            .bottom-left,
            .bottom-right {
                gap: 8px;
            }

            .bottom-actions .btn {
                padding: 10px 12px;
                font-size: 13px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <!-- BANNER -->
    <div class="welcome-banner">
        <div>
            <div class="sub-brand">SI PADI BREBES</div>
            <h1> Data Penyakit</h1>
        </div>

        <div class="welcome-art">🌾</div>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= h($jenisPesan) ?>">
            <?= h($pesan) ?>
        </div>
    <?php endif; ?>

    <!-- PANEL DAFTAR PENYAKIT -->
    <div class="panel" id="panelPenyakit">

        <div class="panel-top">
            <h2>Daftar Penyakit Tanaman Padi</h2>

            <span class="total">
                <?= (int) $totalPenyakit ?> Penyakit
            </span>
        </div>

        <form method="POST" id="formHapus">
            <input type="hidden" name="aksi" value="hapus">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION['csrf_penyakit']) ?>"
            >

            <div class="table-wrap">
                <table id="tabelPenyakit">
                    <thead>
                    <tr>
                        <th class="checkbox-cell">
                            <input
                                type="checkbox"
                                id="pilihSemua"
                                aria-label="Pilih semua penyakit"
                            >
                        </th>
                        <th>Kode</th>
                        <th>Nama Penyakit</th>
                        <th>Solusi / Pengendalian</th>
                        <th class="aksi-header">Aksi</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (empty($penyakit)): ?>
                        <tr>
                            <td colspan="5" class="empty">
                                Belum ada data penyakit.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($penyakit as $item): ?>
                            <tr>
                                <td class="checkbox-cell">
                                    <input
                                        type="checkbox"
                                        class="pilih-penyakit"
                                        name="penyakit_id[]"
                                        value="<?= (int) $item['id'] ?>"
                                        aria-label="Pilih <?= h($item['kode_penyakit']) ?>"
                                    >
                                </td>

                                <td class="kode">
                                    <?= h($item['kode_penyakit']) ?>
                                </td>

                                <td>
                                    <div class="nama-cell">
                                        <span class="nama-text">
                                            <?= h($item['nama_penyakit']) ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <?php if (trim((string) ($item['solusi'] ?? '')) !== ''): ?>
                                        <?= h($item['solusi']) ?>
                                    <?php else: ?>
                                        <span style="color:#9aa59c">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="aksi-cell">
                                    <a
                                        class="edit-icon"
                                        href="edit-penyakit.php?id=<?= (int) $item['id'] ?>"
                                        title="Edit penyakit <?= h($item['kode_penyakit']) ?>"
                                        aria-label="Edit penyakit <?= h($item['kode_penyakit']) ?>"
                                    >
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            aria-hidden="true"
                                        >
                                            <path d="M12 20h9"/>
                                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <div class="footer">
            SI PADI BREBES · Sistem Pakar Diagnosis Penyakit Tanaman Padi
        </div>
    </div>

    <!-- TOMBOL PALING BAWAH -->
    <div class="bottom-actions">

        <!-- KIRI: KEMBALI -->
        <div class="bottom-left">
            <a href="dashboard.php" class="btn btn-kembali">
                 Kembali
            </a>
        </div>

        <!-- KANAN: HAPUS DAN TAMBAH -->
        <div class="bottom-right">

            <button
                type="button"
                class="btn btn-hapus"
                id="tombolModeHapus"
            >
                Hapus Data Terpilih
            </button>

            <button
                type="button"
                class="btn btn-batal"
                id="tombolBatal"
                hidden
            >
                Batal
            </button>

            <button
                type="submit"
                form="formHapus"
                class="btn btn-konfirmasi"
                id="tombolKonfirmasi"
                hidden
            >
                Konfirmasi Hapus
            </button>

            <a href="tambah-penyakit.php" class="btn btn-tambah">
                Tambah Penyakit
            </a>

        </div>
    </div>

</div>

<script>
const panel = document.getElementById('panelPenyakit');
const tombolMode = document.getElementById('tombolModeHapus');
const tombolBatal = document.getElementById('tombolBatal');
const tombolKonfirmasi = document.getElementById('tombolKonfirmasi');
const pilihSemua = document.getElementById('pilihSemua');
const formHapus = document.getElementById('formHapus');

function daftarCheckbox() {
    return Array.from(document.querySelectorAll('.pilih-penyakit'));
}

function perbaruiPilihan() {
    const semua = daftarCheckbox();
    const terpilih = semua.filter(item => item.checked);

    pilihSemua.checked = semua.length > 0 &&
        terpilih.length === semua.length;

    pilihSemua.indeterminate = terpilih.length > 0 &&
        terpilih.length < semua.length;

    tombolKonfirmasi.disabled = terpilih.length === 0;
    tombolKonfirmasi.style.opacity =
        terpilih.length === 0 ? '0.55' : '1';
}

function keluarModeHapus() {
    panel.classList.remove('mode-hapus');
    tombolMode.hidden = false;
    tombolBatal.hidden = true;
    tombolKonfirmasi.hidden = true;
    pilihSemua.checked = false;
    pilihSemua.indeterminate = false;

    daftarCheckbox().forEach(item => {
        item.checked = false;
    });
}

tombolMode.addEventListener('click', function () {
    panel.classList.add('mode-hapus');
    tombolMode.hidden = true;
    tombolBatal.hidden = false;
    tombolKonfirmasi.hidden = false;
    perbaruiPilihan();
});

tombolBatal.addEventListener('click', keluarModeHapus);

pilihSemua.addEventListener('change', function () {
    daftarCheckbox().forEach(item => {
        item.checked = pilihSemua.checked;
    });

    perbaruiPilihan();
});

daftarCheckbox().forEach(item => {
    item.addEventListener('change', perbaruiPilihan);
});

formHapus.addEventListener('submit', function (event) {
    const terpilih = daftarCheckbox().filter(item => item.checked);

    if (terpilih.length === 0) {
        event.preventDefault();
        alert('Pilih minimal satu penyakit terlebih dahulu.');
        return;
    }

    if (!confirm(
        'Yakin ingin menghapus ' + terpilih.length +
        ' data penyakit terpilih? Data yang masih digunakan kasus atau diagnosis mungkin tidak dapat dihapus.'
    )) {
        event.preventDefault();
    }
});
</script>
</body>
</html>