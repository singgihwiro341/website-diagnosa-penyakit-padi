<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

wajibRole(['admin']);

/*
|--------------------------------------------------------------------------
| FUNGSI BANTU
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| TOKEN CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_gejala'])) {
    $_SESSION['csrf_gejala'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_gejala'];

/*
|--------------------------------------------------------------------------
| PESAN NOTIFIKASI
|--------------------------------------------------------------------------
*/

$pesan = '';
$jenisPesan = '';

if (isset($_GET['pesan'])) {
    switch ($_GET['pesan']) {
        case 'tambah':
            $pesan = 'Data gejala berhasil ditambahkan.';
            $jenisPesan = 'success';
            break;

        case 'edit':
            $pesan = 'Data gejala berhasil diperbarui.';
            $jenisPesan = 'success';
            break;

        case 'tidak_ditemukan':
            $pesan = 'Data gejala tidak ditemukan.';
            $jenisPesan = 'error';
            break;
    }
}

/*
|--------------------------------------------------------------------------
| PROSES HAPUS BANYAK DATA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenDikirim = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenDikirim) ||
        !hash_equals($csrfToken, $tokenDikirim)
    ) {
        $pesan = 'Permintaan tidak valid. Silakan muat ulang halaman.';
        $jenisPesan = 'error';
    } elseif (($_POST['action'] ?? '') === 'hapus') {
        $daftarId = $_POST['gejala_id'] ?? [];

        if (!is_array($daftarId) || count($daftarId) === 0) {
            $pesan = 'Pilih minimal satu data gejala yang akan dihapus.';
            $jenisPesan = 'error';
        } else {
            // Hanya terima ID berupa bilangan bulat positif.
            $daftarId = array_values(array_unique(array_filter(
                array_map(
                    static function ($id) {
                        if (!is_scalar($id)) {
                            return null;
                        }

                        $id = filter_var($id, FILTER_VALIDATE_INT);

                        return ($id !== false && $id > 0) ? $id : null;
                    },
                    $daftarId
                ),
                static function ($id) {
                    return $id !== null;
                }
            )));

            if (count($daftarId) === 0) {
                $pesan = 'Data yang dipilih tidak valid.';
                $jenisPesan = 'error';
            } else {
                try {
                    /*
                     * Kode ini menggunakan koneksi PDO bernama $pdo,
                     * seperti yang digunakan pada proses pengelolaan data.
                     */
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'DELETE FROM gejala WHERE id = :id'
                    );

                    $jumlahTerhapus = 0;

                    foreach ($daftarId as $id) {
                        $stmt->execute([':id' => $id]);
                        $jumlahTerhapus += $stmt->rowCount();
                    }

                    $pdo->commit();

                    if ($jumlahTerhapus > 0) {
                        $pesan = $jumlahTerhapus .
                            ' data gejala berhasil dihapus.';
                        $jenisPesan = 'success';
                    } else {
                        $pesan = 'Tidak ada data gejala yang berhasil dihapus.';
                        $jenisPesan = 'error';
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    // Data mungkin masih digunakan oleh tabel lain.
                    $pesan = 'Data gejala gagal dihapus. Pastikan data tidak ' .
                        'masih digunakan oleh data kasus atau tabel terkait.';
                    $jenisPesan = 'error';

                    // Catat detail kesalahan di log server.
                    error_log('Gagal menghapus gejala: ' . $e->getMessage());
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA GEJALA
|--------------------------------------------------------------------------
*/

try {
    $stmt = $pdo->query(
        'SELECT id, kode_gejala, nama_gejala, bobot, keterangan
         FROM gejala
         ORDER BY kode_gejala ASC'
    );

    $daftarGejala = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Gagal mengambil data gejala: ' . $e->getMessage());

    $daftarGejala = [];
    $pesan = 'Data gejala gagal dimuat. Periksa koneksi database dan struktur tabel.';
    $jenisPesan = 'error';
}

$totalGejala = count($daftarGejala);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Data Gejala | SI PADI BREBES</title>

    <style>
        :root {
            --hijau-tua: #176b3a;
            --hijau: #198754;
            --hijau-muda: #31945a;
            --merah: #dc3545;
            --abu-abu: #6b7280;
            --garis: #e5e7eb;
            --latar: #f3f7f3;
            --putih: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 28px;
            background: var(--latar);
            color: #1f2937;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }

        .container {
            width: 100%;
            max-width: 1250px;
            margin: 0 auto;
        }

        /* =====================================
            BANNER SI PADI BREBES
        ===================================== */

        .welcome-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 30px;
            color: #ffffff;
            border-radius: 16px;
            background: linear-gradient(
                115deg,
                #176b3a,
                #31945a
            );
            overflow: hidden;
            margin-bottom: 22px;
            box-shadow: 0 5px 18px rgba(23, 107, 58, 0.12);
        }

        .welcome-banner h1 {
            margin: 0 0 10px;
            font-size: 30px;
            color: #ffffff;
            line-height: 1.35;
        }

        .welcome-banner p {
            max-width: 580px;
            margin: 0;
            color: #e2f3e6;
            font-size: 13px;
            line-height: 1.7;
        }

        .sub-brand {
            margin-bottom: 6px;
            color: #c8dfcf;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .welcome-art {
            flex-shrink: 0;
            font-size: 65px;
            opacity: 0.9;
            padding: 0 10px;
        }

        /* =====================================
            PANEL TABEL
        ===================================== */

        .panel {
            width: 100%;
            padding: 24px;
            background: var(--putih);
            border: 1px solid #edf0ed;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .panel-title {
            margin: 0;
            color: #1f2937;
            font-size: 19px;
            font-weight: 700;
        }

        .total-badge {
            display: inline-block;
            padding: 7px 12px;
            background: #e8f5ec;
            color: #176b3a;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        /* =====================================
            NOTIFIKASI
        ===================================== */

        .alert {
            margin-bottom: 20px;
            padding: 13px 16px;
            border-radius: 8px;
            line-height: 1.5;
        }

        .alert-success {
            background: #e8f7ed;
            color: #176b3a;
            border: 1px solid #c8ead3;
        }

        .alert-error {
            background: #fff0f0;
            color: #b42332;
            border: 1px solid #f5c2c7;
        }

        /* =====================================
            TABEL GEJALA
        ===================================== */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--garis);
            border-radius: 9px;
        }

        .table-gejala {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
        }

        .table-gejala thead {
            background: #176b3a;
            color: #ffffff;
        }

        .table-gejala th {
            padding: 14px 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .table-gejala td {
            padding: 14px 15px;
            border-bottom: 1px solid #edf0ed;
            vertical-align: middle;
            line-height: 1.6;
        }

        .table-gejala tbody tr:last-child td {
            border-bottom: none;
        }

        .table-gejala tbody tr:hover {
            background: #f8fbf8;
        }

        .table-gejala th:first-child,
        .table-gejala td:first-child {
            text-align: center;
            width: 55px;
        }

        .table-gejala th:nth-child(2),
        .table-gejala td:nth-child(2) {
            width: 115px;
        }

        .table-gejala th:nth-child(4),
        .table-gejala td:nth-child(4) {
            width: 90px;
            text-align: center;
        }

        .kode-gejala {
            display: inline-block;
            padding: 5px 9px;
            color: #176b3a;
            background: #e8f5ec;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
        }

        .bobot-badge {
            display: inline-block;
            min-width: 30px;
            padding: 5px 9px;
            background: #f3f4f6;
            color: #374151;
            border-radius: 6px;
            font-weight: 700;
            text-align: center;
        }

        /* Tombol Edit Baru dengan Ikon Pensil Sesuai Referensi */
        .btn-edit {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 36px;
            height: 36px;
            color: #176b3a;
            background: #e8f5ec;
            border: 1px solid #cde8d5;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-edit:hover {
            background: #d5efdc;
        }

        .empty-data {
            padding: 35px 15px !important;
            color: #6b7280;
            text-align: center !important;
        }

        /* =====================================
            CHECKBOX PILIH DATA
        ===================================== */

        .kolom-pilih {
            display: none;
        }

        #panelGejala.mode-hapus .kolom-pilih {
            display: table-cell;
        }

        .checkbox-gejala,
        #pilihSemua {
            width: 16px;
            height: 16px;
            accent-color: #198754;
            cursor: pointer;
            vertical-align: middle;
        }

        /* =====================================
            TOMBOL BAGIAN BAWAH
        ===================================== */

        .bottom-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--garis);
        }

        .bottom-left,
        .bottom-right {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 40px;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
        }

        .btn-kembali {
            color: #374151;
            background: #ffffff;
            border: 1px solid #d1d5db;
        }

        .btn-kembali:hover {
            background: #f3f4f6;
        }

        .btn-tambah {
            color: #ffffff;
            background: #198754;
            border: 1px solid #198754;
        }

        .btn-tambah:hover {
            background: #146c43;
            border-color: #146c43;
        }

        .btn-hapus-mode,
        .btn-konfirmasi {
            color: #ffffff;
            background: #dc3545;
            border: 1px solid #dc3545;
        }

        .btn-hapus-mode:hover,
        .btn-konfirmasi:hover {
            background: #bb2d3b;
            border-color: #bb2d3b;
        }

        .btn-batal {
            color: #374151;
            background: #ffffff;
            border: 1px solid #d1d5db;
        }

        .btn-batal:hover {
            background: #f3f4f6;
        }

        [hidden] {
            display: none !important;
        }

        /* =====================================
            RESPONSIVE
        ===================================== */

        @media (max-width: 650px) {
            body {
                padding: 15px;
            }

            .welcome-banner {
                padding: 23px 20px;
            }

            .welcome-banner h1 {
                font-size: 24px;
            }

            .welcome-art {
                font-size: 43px;
                padding: 0;
            }

            .panel {
                padding: 16px;
            }

            .bottom-actions {
                align-items: stretch;
            }

            .bottom-left,
            .bottom-right {
                width: 100%;
            }

            .bottom-left .btn,
            .bottom-right .btn {
                flex: 1;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <!-- =====================================
         BANNER SI PADI BREBES
    ====================================== -->

    <div class="welcome-banner">
        <div>
            <div class="sub-brand">SI PADI BREBES</div>

            <h1>Data Gejala</h1>
        </div>

        <div class="welcome-art" aria-hidden="true">🌾</div>
    </div>

    <!-- =====================================
         PANEL DATA GEJALA
    ====================================== -->

    <div class="panel" id="panelGejala">

        <div class="panel-header">
            <h2 class="panel-title">Daftar Gejala</h2>

            <span class="total-badge">
                <?= $totalGejala; ?> Data Gejala
            </span>
        </div>

        <!-- NOTIFIKASI -->
        <?php if ($pesan !== ''): ?>
            <div
                class="alert <?= $jenisPesan === 'success'
                    ? 'alert-success'
                    : 'alert-error'; ?>"
                role="alert"
            >
                <?= h($pesan); ?>
            </div>
        <?php endif; ?>

        <!--
            Form mencakup tabel dan tombol konfirmasi.
            Jangan menambahkan form lain di dalam form ini.
        -->
        <form
            method="POST"
            action="gejala.php"
            id="formHapusGejala"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($csrfToken); ?>"
            >

            <div class="table-wrapper">
                <table class="table-gejala">

                    <thead>
                        <tr>
                            <th class="kolom-pilih">
                                <input
                                    type="checkbox"
                                    id="pilihSemua"
                                    aria-label="Pilih semua gejala"
                                    title="Pilih semua gejala"
                                >
                            </th>

                            <th>Kode</th>
                            <th>Nama Gejala</th>
                            <th>Bobot</th>
                            <th>Penyakit</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (count($daftarGejala) > 0): ?>

                        <?php foreach ($daftarGejala as $gejala): ?>
                            <tr>
                                <td class="kolom-pilih">
                                    <input
                                        type="checkbox"
                                        class="checkbox-gejala"
                                        name="gejala_id[]"
                                        value="<?= (int) $gejala['id']; ?>"
                                        aria-label="Pilih gejala <?= h($gejala['kode_gejala']); ?>"
                                    >
                                </td>

                                <td>
                                    <span class="kode-gejala">
                                        <?= h($gejala['kode_gejala']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?= h($gejala['nama_gejala']); ?>
                                </td>

                                <td>
                                    <span class="bobot-badge">
                                        <?= (int) $gejala['bobot']; ?>
                                    </span>
                                </td>

                                <td>
                                    <?php
                                    $keterangan = trim(
                                        (string) ($gejala['keterangan'] ?? '')
                                    );

                                    echo $keterangan !== ''
                                        ? nl2br(h($keterangan))
                                        : '-';
                                    ?>
                                </td>

                                <td>
                                    <a
                                        href="edit-gejala.php?id=<?= (int) $gejala['id']; ?>"
                                        class="btn-edit"
                                        title="Edit Gejala"
                                    >
                                        <!-- Ikon Pensil Sesuai Referensi -->
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="empty-data">
                                Belum ada data gejala.
                            </td>
                        </tr>

                    <?php endif; ?>
                    </tbody>

                </table>
            </div>

            <!-- =====================================
                 TOMBOL BAGIAN BAWAH
                 KIRI: KEMBALI & HAPUS TERPILIH
                 KANAN: TAMBAH GEJALA
            ====================================== -->

            <div class="bottom-actions">

                <!-- KIRI -->
                <div class="bottom-left">

                    <a
                        href="dashboard.php"
                        class="btn btn-kembali"
                    >
                        Kembali
                    </a>

                    <button
                        type="button"
                        id="btnHapusMode"
                        class="btn btn-hapus-mode"
                    >
                        Hapus Data Terpilih
                    </button>

                    <button
                        type="button"
                        id="btnBatalHapus"
                        class="btn btn-batal"
                        hidden
                    >
                        Batal
                    </button>

                    <button
                        type="submit"
                        name="action"
                        value="hapus"
                        id="btnKonfirmasiHapus"
                        class="btn btn-konfirmasi"
                        hidden
                    >
                        Konfirmasi Hapus
                    </button>

                </div>

                <!-- KANAN -->
                <div class="bottom-right">

                    <a
                        href="tambah-gejala.php"
                        class="btn btn-tambah"
                    >
                        Tambah Gejala
                    </a>

                </div>

            </div>

        </form>

    </div>

</div>

<!-- =====================================
     JAVASCRIPT PILIH DAN HAPUS DATA
====================================== -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('panelGejala');
    const form = document.getElementById('formHapusGejala');

    const btnHapusMode = document.getElementById('btnHapusMode');
    const btnBatalHapus = document.getElementById('btnBatalHapus');
    const btnKonfirmasiHapus = document.getElementById(
        'btnKonfirmasiHapus'
    );

    const pilihSemua = document.getElementById('pilihSemua');

    const checkboxGejala = Array.from(
        document.querySelectorAll('.checkbox-gejala')
    );

    /*
     * Aktifkan mode pemilihan gejala.
     */
    function aktifkanModeHapus() {
        if (checkboxGejala.length === 0) {
            alert('Belum ada data gejala yang dapat dipilih.');
            return;
        }

        panel.classList.add('mode-hapus');

        btnHapusMode.hidden = true;
        btnBatalHapus.hidden = false;
        btnKonfirmasiHapus.hidden = false;

        if (pilihSemua) {
            pilihSemua.checked = false;
        }
    }

    /*
     * Keluar dari mode pemilihan dan bersihkan centang.
     */
    function batalkanModeHapus() {
        panel.classList.remove('mode-hapus');

        checkboxGejala.forEach(function (checkbox) {
            checkbox.checked = false;
        });

        if (pilihSemua) {
            pilihSemua.checked = false;
            pilihSemua.indeterminate = false;
        }

        btnHapusMode.hidden = false;
        btnBatalHapus.hidden = true;
        btnKonfirmasiHapus.hidden = true;
    }

    /*
     * Perbarui checkbox pilih semua.
     */
    function perbaruiPilihSemua() {
        if (!pilihSemua || checkboxGejala.length === 0) {
            return;
        }

        const jumlahTerpilih = checkboxGejala.filter(
            function (checkbox) {
                return checkbox.checked;
            }
        ).length;

        pilihSemua.checked =
            jumlahTerpilih === checkboxGejala.length;

        pilihSemua.indeterminate =
            jumlahTerpilih > 0 &&
            jumlahTerpilih < checkboxGejala.length;
    }

    /*
     * Tombol hapus.
     */
    btnHapusMode.addEventListener('click', function () {
        aktifkanModeHapus();
    });

    /*
     * Tombol batal.
     */
    btnBatalHapus.addEventListener('click', function () {
        batalkanModeHapus();
    });

    /*
     * Pilih atau batalkan semua gejala.
     */
    if (pilihSemua) {
        pilihSemua.addEventListener('change', function () {
            checkboxGejala.forEach(function (checkbox) {
                checkbox.checked = pilihSemua.checked;
            });

            perbaruiPilihSemua();
        });
    }

    /*
     * Perbarui status pilih semua saat checkbox berubah.
     */
    checkboxGejala.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            perbaruiPilihSemua();
        });
    });

    /*
     * Validasi pilihan dan minta konfirmasi sebelum menghapus.
     */
    form.addEventListener('submit', function (event) {
        const tombolSubmit = event.submitter;

        if (
            !tombolSubmit ||
            tombolSubmit.name !== 'action' ||
            tombolSubmit.value !== 'hapus'
        ) {
            return;
        }

        const terpilih = checkboxGejala.filter(
            function (checkbox) {
                return checkbox.checked;
            }
        );

        if (terpilih.length === 0) {
            event.preventDefault();

            alert('Pilih minimal satu data gejala yang akan dihapus.');
            return;
        }

        const konfirmasi = confirm(
            'Apakah Anda yakin ingin menghapus ' +
            terpilih.length +
            ' data gejala terpilih?\n\n' +
            'Data yang sudah dihapus tidak dapat dikembalikan.'
        );

        if (!konfirmasi) {
            event.preventDefault();
        }
    });
});
</script>

</body>
</html>