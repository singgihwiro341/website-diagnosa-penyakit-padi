<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

wajibRole(['admin']);

function h($nilai)
{
    return htmlspecialchars((string) ($nilai ?? ''), ENT_QUOTES, 'UTF-8');
}

$linkKembali = 'dashboard.php';
$pesan = '';
$jenisPesan = 'info';

if (empty($_SESSION['csrf_kasus'])) {
    $_SESSION['csrf_kasus'] = bin2hex(random_bytes(32));
}

if (!empty($_SESSION['flash_kasus'])) {
    $pesan = $_SESSION['flash_kasus']['pesan'];
    $jenisPesan = $_SESSION['flash_kasus']['jenis'];
    unset($_SESSION['flash_kasus']);
}

/*
|--------------------------------------------------------------------------
| PROSES FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $aksi = $_POST['aksi'] ?? '';

    try {
        if (
            !is_string($token) ||
            !hash_equals($_SESSION['csrf_kasus'], $token)
        ) {
            throw new RuntimeException(
                'Permintaan tidak valid. Silakan muat ulang halaman.'
            );
        }

        if (!$id || $id < 1) {
            throw new RuntimeException('ID kasus tidak valid.');
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFIKASI
        |--------------------------------------------------------------------------
        */

        if ($aksi === 'verifikasi') {
            $stmt = $pdo->prepare(
                "UPDATE kasus
                 SET status_validasi = 'terverifikasi'
                 WHERE id = :id
                   AND status_validasi = 'draft'"
            );

            $stmt->execute(['id' => $id]);

            $_SESSION['flash_kasus'] = [
                'pesan' => $stmt->rowCount() > 0
                    ? 'Kasus berhasil diverifikasi dan dapat digunakan dalam diagnosis.'
                    : 'Kasus tidak ditemukan atau statusnya sudah berubah.',
                'jenis' => $stmt->rowCount() > 0 ? 'success' : 'error'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | BATALKAN VERIFIKASI
        |--------------------------------------------------------------------------
        */

        elseif ($aksi === 'batalkan') {
            $stmt = $pdo->prepare(
                "UPDATE kasus
                 SET status_validasi = 'draft'
                 WHERE id = :id
                   AND status_validasi = 'terverifikasi'"
            );

            $stmt->execute(['id' => $id]);

            $_SESSION['flash_kasus'] = [
                'pesan' => $stmt->rowCount() > 0
                    ? 'Verifikasi berhasil dibatalkan.'
                    : 'Kasus tidak ditemukan atau statusnya sudah berubah.',
                'jenis' => $stmt->rowCount() > 0 ? 'success' : 'error'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | EDIT KASUS
        |--------------------------------------------------------------------------
        */

        elseif ($aksi === 'edit') {
            $penyakitId = filter_var(
                $_POST['penyakit_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $sumberData = trim($_POST['sumber_data'] ?? '');
            $catatan = trim($_POST['catatan'] ?? '');

            $gejalaDipilih = $_POST['gejala'] ?? [];

            if (!$penyakitId || $penyakitId < 1) {
                throw new RuntimeException('Silakan pilih penyakit.');
            }

            if (!is_array($gejalaDipilih) || empty($gejalaDipilih)) {
                throw new RuntimeException(
                    'Pilih minimal satu gejala untuk kasus ini.'
                );
            }

            $gejalaDipilih = array_values(array_unique(array_filter(
                array_map('intval', $gejalaDipilih),
                fn($nilai) => $nilai > 0
            )));

            if (empty($gejalaDipilih)) {
                throw new RuntimeException('Pilihan gejala tidak valid.');
            }

            // Pastikan penyakit tersedia
            $cekPenyakit = $pdo->prepare(
                "SELECT id FROM penyakit WHERE id = :id"
            );
            $cekPenyakit->execute(['id' => $penyakitId]);

            if (!$cekPenyakit->fetchColumn()) {
                throw new RuntimeException('Data penyakit tidak ditemukan.');
            }

            // Pastikan semua gejala tersedia
            $placeholder = implode(
                ',',
                array_fill(0, count($gejalaDipilih), '?')
            );

            $cekGejala = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM gejala
                 WHERE id IN ($placeholder)"
            );

            $cekGejala->execute($gejalaDipilih);

            if ((int) $cekGejala->fetchColumn() !== count($gejalaDipilih)) {
                throw new RuntimeException(
                    'Terdapat gejala yang tidak ditemukan di database.'
                );
            }

            $pdo->beginTransaction();

            // Perbarui data utama kasus
            $updateKasus = $pdo->prepare(
                "UPDATE kasus
                 SET penyakit_id = :penyakit_id,
                     sumber_data = :sumber_data,
                     catatan = :catatan
                 WHERE id = :id"
            );

            $updateKasus->execute([
                'penyakit_id' => $penyakitId,
                'sumber_data' => $sumberData,
                'catatan' => $catatan,
                'id' => $id
            ]);

            // Hapus hubungan gejala lama
            $hapusGejala = $pdo->prepare(
                "DELETE FROM kasus_gejala WHERE kasus_id = :id"
            );
            $hapusGejala->execute(['id' => $id]);

            // Masukkan hubungan gejala yang baru
            $tambahGejala = $pdo->prepare(
                "INSERT INTO kasus_gejala (kasus_id, gejala_id)
                 VALUES (:kasus_id, :gejala_id)"
            );

            foreach ($gejalaDipilih as $gejalaId) {
                $tambahGejala->execute([
                    'kasus_id' => $id,
                    'gejala_id' => $gejalaId
                ]);
            }

            $pdo->commit();

            $_SESSION['flash_kasus'] = [
                'pesan' => 'Data kasus berhasil diperbarui.',
                'jenis' => 'success'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | HAPUS KASUS
        |--------------------------------------------------------------------------
        */

        elseif ($aksi === 'hapus') {
            $pdo->beginTransaction();

            // Hapus hubungan gejala terlebih dahulu
            $hapusRelasi = $pdo->prepare(
                "DELETE FROM kasus_gejala WHERE kasus_id = :id"
            );
            $hapusRelasi->execute(['id' => $id]);

            // Hapus data kasus
            $hapusKasus = $pdo->prepare(
                "DELETE FROM kasus WHERE id = :id"
            );
            $hapusKasus->execute(['id' => $id]);

            if ($hapusKasus->rowCount() < 1) {
                throw new RuntimeException('Data kasus tidak ditemukan.');
            }

            $pdo->commit();

            $_SESSION['flash_kasus'] = [
                'pesan' => 'Data kasus berhasil dihapus.',
                'jenis' => 'success'
            ];
        } else {
            throw new RuntimeException('Aksi tidak dikenali.');
        }

    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['flash_kasus'] = [
            'pesan' => $e->getMessage(),
            'jenis' => 'error'
        ];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[SI PADI BREBES] ' . $e->getMessage());

        $_SESSION['flash_kasus'] = [
            'pesan' => 'Terjadi kesalahan database. Periksa kembali data kasus.',
            'jenis' => 'error'
        ];
    }

    header('Location: kasus.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA PENYAKIT DAN GEJALA UNTUK FORM EDIT
|--------------------------------------------------------------------------
*/

try {
    $penyakitList = $pdo->query(
        "SELECT id, kode_penyakit, nama_penyakit
         FROM penyakit
         ORDER BY kode_penyakit ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $gejalaList = $pdo->query(
        "SELECT id, kode_gejala, nama_gejala, bobot
         FROM gejala
         ORDER BY kode_gejala ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | AMBIL DATA KASUS
    |--------------------------------------------------------------------------
    */

    $sql = "SELECT
                k.id,
                k.kode_kasus,
                k.penyakit_id,
                p.nama_penyakit,
                k.sumber_data,
                k.catatan,
                k.status_validasi,
                k.created_at
            FROM kasus k
            JOIN penyakit p ON p.id = k.penyakit_id
            ORDER BY k.id DESC";

    $kasus = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | AMBIL GEJALA SETIAP KASUS
    |--------------------------------------------------------------------------
    */

    $gejalaPerKasus = [];

    if (!empty($kasus)) {
        $sqlGejala = "SELECT
                         kg.kasus_id,
                         g.id AS gejala_id,
                         g.kode_gejala,
                         g.nama_gejala,
                         g.bobot
                      FROM kasus_gejala kg
                      JOIN gejala g ON g.id = kg.gejala_id
                      ORDER BY kg.kasus_id, g.kode_gejala";

        $semuaGejala = $pdo->query($sqlGejala)
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($semuaGejala as $gejala) {
            $idKasus = (int) $gejala['kasus_id'];

            if (!isset($gejalaPerKasus[$idKasus])) {
                $gejalaPerKasus[$idKasus] = [];
            }

            $gejalaPerKasus[$idKasus][] = $gejala;
        }
    }

} catch (PDOException $e) {
    error_log('[SI PADI BREBES] ' . $e->getMessage());

    $kasus = [];
    $gejalaPerKasus = [];
    $penyakitList = [];
    $gejalaList = [];

    $pesan = 'Gagal memuat data kasus. Periksa struktur tabel database.';
    $jenisPesan = 'error';
}

/*
|--------------------------------------------------------------------------
| RINGKASAN
|--------------------------------------------------------------------------
*/

$totalKasus = count($kasus);

$totalDraft = count(array_filter(
    $kasus,
    fn($item) => $item['status_validasi'] === 'draft'
));

$totalTerverifikasi = count(array_filter(
    $kasus,
    fn($item) => $item['status_validasi'] === 'terverifikasi'
));
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Data Kasus CBR - SI PADI BREBES</title>

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
    max-width: 1350px;
    margin: 0 auto;
}

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

.header {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 13px;
    border: 0;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: .2s ease;
    font-family: inherit;
}

.btn-kembali {
    background: #e2f2e5;
    color: #176534;
    border: 1px solid #b9dfc1;
}

.btn-tambah {
    background: #168044;
    color: white;
}

.btn-tambah:hover,
.btn-verifikasi:hover {
    background: #106536;
}

.btn-edit {
    background: #e8f1ff;
    color: #245b87;
    border: 1px solid #cbdcf4;
}

.btn-edit:hover {
    background: #d7e7ff;
}

.btn-hapus {
    background: #fff0ef;
    color: #a12b25;
    border: 1px solid #f2cecb;
}

.btn-hapus:hover {
    background: #ffe0dd;
}

.btn-verifikasi {
    background: #168044;
    color: white;
    padding: 8px 12px;
    font-size: 12px;
}

.btn-batalkan {
    background: #fff1d6;
    color: #875500;
    border: 1px solid #f0d49a;
    padding: 8px 12px;
    font-size: 12px;
}

.btn-batalkan:hover {
    background: #ffe6b0;
}

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

.ringkasan {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.stat {
    background: white;
    border: 1px solid #e7eee7;
    border-radius: 12px;
    padding: 17px;
}

.stat-label {
    color: #718075;
    font-size: 13px;
    margin-bottom: 8px;
}

.stat-number {
    font-size: 25px;
    font-weight: 700;
    color: #176534;
}

.stat-number.orange {
    color: #a16a00;
}

.stat-number.green {
    color: #168044;
}

.alert {
    margin: 0 0 18px;
    padding: 13px 16px;
    border-radius: 9px;
    line-height: 1.5;
}

.alert.error {
    color: #a12b25;
    background: #fff0ef;
}

.alert.success {
    color: #176534;
    background: #e7f7e9;
}

.petunjuk {
    padding: 16px 22px;
    background: #f7fbf7;
    color: #526456;
    font-size: 13px;
    line-height: 1.7;
    border-bottom: 1px solid #edf1ed;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th, td {
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

tbody tr:hover:not(.baris-detail) {
    background: #f7fbf7;
}

.kode {
    color: #24783b;
    font-weight: 700;
    white-space: nowrap;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 7px;
    font-weight: 600;
    font-size: 12px;
    white-space: nowrap;
}

.status.draft {
    background: #fff4d9;
    color: #875500;
}

.status.terverifikasi {
    background: #e1f6e7;
    color: #176534;
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

/* Tombol detail */
.btn-detail {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 8px 10px;
    border: 1px solid #cfe5d2;
    border-radius: 8px;
    background: #f0f8f1;
    color: #176534;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}

.btn-detail:hover {
    background: #dff0e2;
}

.panah-detail {
    display: inline-block;
    font-size: 11px;
    transition: transform .25s ease;
}

.btn-detail[aria-expanded="true"] .panah-detail {
    transform: rotate(180deg);
}

/* Baris detail */
.baris-detail td {
    padding: 0;
    background: #f7fbf7;
}

.detail-gejala {
    padding: 22px;
    border-left: 4px solid #168044;
    animation: turun .25s ease;
}

.detail-gejala h3 {
    margin: 0 0 15px;
    color: #176534;
    font-size: 16px;
}

.detail-info {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}

.info-box {
    background: white;
    border: 1px solid #e1ece2;
    padding: 13px;
    border-radius: 9px;
}

.info-label {
    display: block;
    color: #718075;
    font-size: 12px;
    margin-bottom: 7px;
}

.info-value {
    color: #24352a;
    font-size: 13px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.judul-sub {
    color: #176534;
    font-size: 14px;
    margin: 20px 0 12px;
}

.daftar-gejala {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.item-gejala {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px;
    background: white;
    border: 1px solid #e1ece2;
    border-radius: 9px;
    line-height: 1.6;
}

.kode-gejala {
    flex-shrink: 0;
    padding: 3px 7px;
    background: #e1f6e7;
    color: #176534;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 700;
}

.gejala-kosong {
    padding: 13px;
    color: #875500;
    background: #fff7e5;
    border: 1px solid #f2dfb5;
    border-radius: 8px;
    font-size: 13px;
}

/* Form edit */
.form-edit {
    margin-top: 22px;
    padding: 20px;
    background: white;
    border: 1px solid #dceadd;
    border-radius: 12px;
}

.form-edit h3 {
    margin: 0 0 17px;
    color: #176534;
    font-size: 16px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 700;
    color: #405446;
}

.form-control {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #d7e2d8;
    border-radius: 8px;
    font-family: inherit;
    font-size: 13px;
    color: #24352a;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #168044;
    box-shadow: 0 0 0 3px rgba(22, 128, 68, .10);
}

textarea.form-control {
    resize: vertical;
    min-height: 75px;
}

.pilihan-gejala {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 9px;
    max-height: 350px;
    overflow-y: auto;
    padding: 12px;
    background: #f7fbf7;
    border: 1px solid #e1ece2;
    border-radius: 9px;
}

.checkbox-gejala {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    padding: 10px;
    background: white;
    border: 1px solid #e1ece2;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
    line-height: 1.5;
}

.checkbox-gejala input {
    margin-top: 3px;
    accent-color: #168044;
}

.checkbox-gejala:hover {
    border-color: #168044;
}

.form-actions {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
    margin-top: 17px;
}

.catatan-kecil {
    color: #718075;
    font-size: 12px;
    line-height: 1.6;
    margin-top: 7px;
}

[hidden] {
    display: none !important;
}

/* Tombol di bagian paling bawah */
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
}

@keyframes turun {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 650px) {
    body {
        padding: 17px 12px;
    }

    .welcome-banner h1 {
        font-size: 23px;
    }

    .header-actions {
        width: 100%;
    }

    .header-actions .btn {
        flex: 1;
    }

    .ringkasan {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .panel-top {
        padding: 16px;
    }

    .detail-gejala {
        padding: 15px 12px;
    }

    .daftar-gejala,
    .detail-info,
    .pilihan-gejala {
        grid-template-columns: 1fr;
    }

    .form-edit {
        padding: 14px;
    }

    .bottom-actions {
        padding: 16px;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="welcome-banner">
        <div>
            <div class="sub-brand"> SI PADI BREBES</div>
            <h1>Data Basis Kasus CBR</h1>
        </div>
        <div class="welcome-art">🌾</div>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= h($jenisPesan) ?>">
            <?= h($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="ringkasan">

        <div class="stat">
            <div class="stat-label">Total Kasus</div>
            <div class="stat-number"><?= $totalKasus ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Menunggu Verifikasi</div>
            <div class="stat-number orange"><?= $totalDraft ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Kasus Terverifikasi</div>
            <div class="stat-number green"><?= $totalTerverifikasi ?></div>
        </div>

    </div>

    <div class="panel">

        <div class="panel-top">
            <h2>Daftar Kasus</h2>

            <span class="status terverifikasi">
                <?= $totalTerverifikasi ?> kasus aktif untuk diagnosis
            </span>
        </div>

        <div class="petunjuk">
            <strong>Petunjuk:</strong>
            Klik panah pada kode kasus untuk melihat informasi status, daftar gejala, dan formulir edit. Gunakan tombol hapus apabila data kasus memang ingin dihilangkan selain itu semua jangan di ubah apapun.
        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Kode Kasus</th>
                        <th>Penyakit</th>
                        <th>Sumber Data</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($kasus)): ?>

                    <tr>
                        <td colspan="4" class="empty">
                            Belum ada data kasus. Silakan tambahkan kasus terlebih dahulu.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($kasus as $item): ?>

                        <?php
                        $idKasus = (int) $item['id'];
                        $daftarGejala = $gejalaPerKasus[$idKasus] ?? [];
                        $idDetail = 'detail-kasus-' . $idKasus;

                        $gejalaTerpilih = array_map(
                            'intval',
                            array_column($daftarGejala, 'gejala_id')
                        );
                        ?>

                        <!-- BARIS UTAMA -->
                        <tr>

                            <td class="kode">

                                <button
                                    type="button"
                                    class="btn-detail"
                                    aria-expanded="false"
                                    aria-controls="<?= h($idDetail) ?>"
                                    onclick="toggleDetail(this, '<?= h($idDetail) ?>')"
                                >
                                    <span class="panah-detail">▼</span>
                                    <?= h($item['kode_kasus']) ?>
                                </button>

                            </td>

                            <td>
                                <?= h($item['nama_penyakit']) ?>
                            </td>

                            <td>
                                <?= h($item['sumber_data'] ?: '-') ?>
                            </td>

                            <td>
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <?php if ($item['status_validasi'] === 'draft'): ?>
                                        <form
                                            method="POST"
                                            style="display:inline;"
                                            onsubmit="return confirm('Pastikan data kasus sudah diperiksa dan disetujui berdasarkan data pakar. Verifikasi kasus ini?');"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_kasus']) ?>">
                                            <input type="hidden" name="id" value="<?= $idKasus ?>">
                                            <input type="hidden" name="aksi" value="verifikasi">
                                            <button type="submit" class="btn btn-verifikasi">
                                                ✓ Verifikasi Kasus
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="return confirm('Yakin ingin menghapus kasus <?= h($item['kode_kasus']) ?>? Data gejala yang terhubung dengan kasus ini juga akan dihapus.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= h($_SESSION['csrf_kasus']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= $idKasus ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="aksi"
                                            value="hapus"
                                        >

                                        <button type="submit" class="btn btn-hapus">
                                            Hapus
                                        </button>

                                    </form>
                                </div>
                            </td>

                        </tr>

                        <!-- BARIS DETAIL -->
                        <tr
                            id="<?= h($idDetail) ?>"
                            class="baris-detail"
                            hidden
                        >

                            <td colspan="4">

                                <div class="detail-gejala">

                                    <h3>
                                        Detail Kasus <?= h($item['kode_kasus']) ?>
                                    </h3>

                                    <!-- INFORMASI STATUS DAN CATATAN -->
                                    <div class="detail-info">

                                        <div class="info-box">
                                            <span class="info-label">
                                                Status Validasi
                                            </span>

                                            <div class="info-value">

                                                <?php if ($item['status_validasi'] === 'terverifikasi'): ?>

                                                    <span class="status terverifikasi">
                                                        Terverifikasi
                                                    </span>

                                                <?php else: ?>

                                                    <span class="status draft">
                                                        Draft
                                                    </span>

                                                <?php endif; ?>

                                            </div>
                                        </div>

                                        <div class="info-box">
                                            <span class="info-label">
                                                Tanggal Dibuat
                                            </span>

                                            <div class="info-value">
                                                <?= h($item['created_at'] ?? '-') ?>
                                            </div>
                                        </div>

                                        <div class="info-box">
                                            <span class="info-label">
                                                Catatan Kasus
                                            </span>

                                            <div class="info-value">
                                                <?= nl2br(h($item['catatan'] ?: '-')) ?>
                                            </div>
                                        </div>

                                        <div class="info-box">
                                            <span class="info-label">
                                                Jumlah Gejala
                                            </span>

                                            <div class="info-value">
                                                <?= count($daftarGejala) ?> gejala
                                            </div>
                                        </div>

                                    </div>

                                    <!-- DAFTAR GEJALA -->
                                    <h3 class="judul-sub">
                                        Daftar Gejala Kasus
                                    </h3>

                                    <?php if (empty($daftarGejala)): ?>

                                        <div class="gejala-kosong">
                                            Belum ada gejala yang terhubung dengan kasus ini.
                                        </div>

                                    <?php else: ?>

                                        <div class="daftar-gejala">

                                            <?php foreach ($daftarGejala as $gejala): ?>

                                                <div class="item-gejala">

                                                    <span class="kode-gejala">
                                                        <?= h($gejala['kode_gejala']) ?>
                                                    </span>

                                                    <span>
                                                        <?= h($gejala['nama_gejala']) ?>
                                                    </span>

                                                </div>

                                            <?php endforeach; ?>

                                        </div>

                                    <?php endif; ?>

                                    <!-- AKSI BATALKAN VERIFIKASI (JIKA TERVERIFIKASI) -->
                                    <div class="form-actions">
                                        <?php if ($item['status_validasi'] === 'terverifikasi'): ?>
                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Batalkan verifikasi kasus ini? Kasus akan dikembalikan ke status draft.');"
                                            >
                                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_kasus']) ?>">
                                                <input type="hidden" name="id" value="<?= $idKasus ?>">
                                                <input type="hidden" name="aksi" value="batalkan">
                                                <button type="submit" class="btn btn-batalkan">
                                                    Batalkan Verifikasi
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <!-- FORM EDIT KASUS -->
                                    <div class="form-edit">
                                        <h3>Edit Data Kasus</h3>

                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_kasus']) ?>">
                                            <input type="hidden" name="id" value="<?= $idKasus ?>">
                                            <input type="hidden" name="aksi" value="edit">

                                            <div class="form-group">
                                                <label for="penyakit_id_<?= $idKasus ?>">Penyakit Terdiagnosis</label>
                                                <select id="penyakit_id_<?= $idKasus ?>" name="penyakit_id" class="form-control" required>
                                                    <option value="">-- Pilih Penyakit --</option>
                                                    <?php foreach ($penyakitList as $penyakit): ?>
                                                        <option value="<?= $penyakit['id'] ?>" <?= $penyakit['id'] == $item['penyakit_id'] ? 'selected' : '' ?>>
                                                            [<?= h($penyakit['kode_penyakit']) ?>] <?= h($penyakit['nama_penyakit']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="sumber_data_<?= $idKasus ?>">Sumber Data</label>
                                                <input type="text" id="sumber_data_<?= $idKasus ?>" name="sumber_data" class="form-control" value="<?= h($item['sumber_data']) ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="catatan_<?= $idKasus ?>">Catatan / Keterangan</label>
                                                <textarea id="catatan_<?= $idKasus ?>" name="catatan" class="form-control"><?= h($item['catatan']) ?></textarea>
                                            </div>

                                            <div class="form-group">
                                                <label>Pilih Gejala yang Dialami</label>
                                                <div class="pilihan-gejala">
                                                    <?php foreach ($gejalaList as $g): ?>
                                                        <label class="checkbox-gejala">
                                                            <input type="checkbox" name="gejala[]" value="<?= $g['id'] ?>" <?= in_array((int)$g['id'], $gejalaTerpilih, true) ? 'checked' : '' ?>>
                                                            <span><strong>[<?= h($g['kode_gejala']) ?>]</strong> <?= h($g['nama_gejala']) ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <div class="form-actions">
                                                <button type="submit" class="btn btn-tambah">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="footer">
            Sistem Pakar Diagnosis Penyakit Tanaman Padi · CBR · DPKP Kabupaten Brebes
        </div>

    </div>

    <!-- TOMBOL BAGIAN PALING BAWAH -->
    <div class="bottom-actions">

        <!-- KIRI: KEMBALI -->
        <div class="bottom-left">
            <a href="<?= h($linkKembali) ?>" class="btn btn-kembali">
                Kembali
            </a>
        </div>

        <!-- KANAN: TAMBAH KASUS -->
        <div class="bottom-right">
            <a href="tambah-kasus.php" class="btn btn-tambah">
                Tambah Kasus
            </a>
        </div>

    </div>

</div>

<script>
function toggleDetail(button, targetId) {
    const row = document.getElementById(targetId);
    const expanded = button.getAttribute('aria-expanded') === 'true';

    if (expanded) {
        row.hidden = true;
        button.setAttribute('aria-expanded', 'false');
    } else {
        row.hidden = false;
        button.setAttribute('aria-expanded', 'true');
    }
}
</script>

</body>
</html>