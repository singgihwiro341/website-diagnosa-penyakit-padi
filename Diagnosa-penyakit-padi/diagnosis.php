
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$linkKembali = !empty($_SESSION['user_id'])
    ? 'dashboard.php'
    : 'index.php';

if (!function_exists('h')) {
    function h($nilai)
    {
        return htmlspecialchars(
            (string) ($nilai ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('pathGambar')) {
    function pathGambar($folder, $namaFile)
    {
        $namaFile = basename(trim((string) ($namaFile ?? '')));

        return $namaFile !== ''
            ? $folder . rawurlencode($namaFile)
            : '';
    }
}

/*
|--------------------------------------------------------------------------
| Mengambil data gejala
|--------------------------------------------------------------------------
*/
$stmt = $pdo->query(
    "SELECT id, kode_gejala, nama_gejala, bobot, gambar
     FROM gejala
     ORDER BY kode_gejala ASC"
);

$gejala = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasil = [];
$pesan = '';
$error = '';

$pilihanTersimpan = $_POST['gejala'] ?? [];

if (!is_array($pilihanTersimpan)) {
    $pilihanTersimpan = [];
}

$pilihanTersimpan = array_values(array_unique(array_filter(
    array_map('intval', $pilihanTersimpan),
    fn($id) => $id > 0
)));

/*
|--------------------------------------------------------------------------
| Proses diagnosis
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pilihan = $pilihanTersimpan;

    if (empty($pilihan)) {

        $error = 'Pilih minimal satu gejala terlebih dahulu.';

    } else {

        $placeholders = implode(
            ',',
            array_fill(0, count($pilihan), '?')
        );

        /*
        |--------------------------------------------------------------------------
        | Validasi ID gejala
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare(
            "SELECT id
             FROM gejala
             WHERE id IN ($placeholders)"
        );

        $stmt->execute($pilihan);

        $idValid = array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );

        sort($idValid);

        $pilihanValid = $pilihan;
        sort($pilihanValid);

        if ($idValid !== $pilihanValid) {

            $error = 'Ada gejala yang tidak valid. Silakan pilih ulang.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Mengambil seluruh kasus yang sudah terverifikasi
            |--------------------------------------------------------------------------
            | Data gejala dan bobot diambil untuk menghitung similarity.
            | LEFT JOIN digunakan agar kasus tanpa gejala tetap dapat diperiksa.
            |--------------------------------------------------------------------------
            */

            $sql = "
                SELECT
                    k.id AS kasus_id,
                    k.kode_kasus,

                    p.id AS penyakit_id,
                    p.kode_penyakit,
                    p.nama_penyakit,
                    p.gambar,
                    p.deskripsi,
                    p.solusi,

                    g.id AS gejala_id,
                    g.bobot

                FROM kasus k

                JOIN penyakit p
                    ON p.id = k.penyakit_id

                LEFT JOIN kasus_gejala kg
                    ON kg.kasus_id = k.id

                LEFT JOIN gejala g
                    ON g.id = kg.gejala_id

                WHERE k.status_validasi = 'terverifikasi'

                ORDER BY k.id ASC, g.id ASC
            ";

            $stmt = $pdo->query($sql);

            $dataKasus = $stmt->fetchAll(PDO::FETCH_ASSOC);

            /*
            |--------------------------------------------------------------------------
            | Membuat daftar gejala pilihan pengguna
            |--------------------------------------------------------------------------
            */

            $gejalaDipilih = array_fill_keys($pilihan, true);

            $daftarKasus = [];

            /*
            |--------------------------------------------------------------------------
            | Mengelompokkan gejala berdasarkan kasus
            |--------------------------------------------------------------------------
            */

            foreach ($dataKasus as $row) {

                $kasusId = (int) $row['kasus_id'];

                if (!isset($daftarKasus[$kasusId])) {

                    $daftarKasus[$kasusId] = [

                        'kasus_id' => $kasusId,
                        'kode_kasus' => $row['kode_kasus'],

                        'penyakit_id' => (int) $row['penyakit_id'],
                        'kode_penyakit' => $row['kode_penyakit'],
                        'nama_penyakit' => $row['nama_penyakit'],

                        'gambar' => $row['gambar'],
                        'deskripsi' => $row['deskripsi'],
                        'solusi' => $row['solusi'],

                        'jumlah_cocok' => 0,
                        'total_gejala' => 0,

                        'bobot_cocok' => 0,
                        'total_bobot' => 0,

                        'gejala_diproses' => []
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Menghitung bobot setiap gejala kasus lama
                |--------------------------------------------------------------------------
                */

                if ($row['gejala_id'] !== null) {

                    $gejalaId = (int) $row['gejala_id'];

                    // Mencegah gejala yang sama dihitung lebih dari sekali
                    if (!isset(
                        $daftarKasus[$kasusId]['gejala_diproses'][$gejalaId]
                    )) {

                        $daftarKasus[$kasusId]['gejala_diproses'][$gejalaId] = true;

                        $bobot = (float) $row['bobot'];

                        // Menghitung jumlah seluruh gejala pada kasus lama
                        $daftarKasus[$kasusId]['total_gejala']++;

                        // Menghitung total bobot seluruh gejala kasus lama
                        $daftarKasus[$kasusId]['total_bobot'] += $bobot;

                        /*
                         * Si = 1 jika gejala cocok
                         * Si = 0 jika gejala tidak cocok
                         */

                        $nilaiCocok = isset($gejalaDipilih[$gejalaId])
                            ? 1
                            : 0;

                        if ($nilaiCocok === 1) {

                            // Menambah jumlah gejala yang cocok
                            $daftarKasus[$kasusId]['jumlah_cocok']++;

                            // Menghitung bobot gejala yang cocok
                            $daftarKasus[$kasusId]['bobot_cocok'] +=
                                $nilaiCocok * $bobot;
                        }
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Menghitung nilai similarity berbobot
            |--------------------------------------------------------------------------
            |
            | Similarity = (Jumlah bobot gejala cocok
            |               / Total bobot seluruh gejala kasus lama)
            |               x 100%
            |--------------------------------------------------------------------------
            */

            foreach ($daftarKasus as $item) {

                $totalBobot = (float) $item['total_bobot'];

                $bobotCocok = (float) $item['bobot_cocok'];

                // Memastikan pembagi tidak bernilai nol
                if ($totalBobot <= 0) {
                    continue;
                }

                $item['similarity'] =
                    ($bobotCocok / $totalBobot) * 100;

                /*
                 * Hanya kasus dengan minimal satu gejala cocok
                 * yang dimasukkan ke hasil diagnosis.
                 */

                if ($item['jumlah_cocok'] > 0) {

                    unset($item['gejala_diproses']);

                    $hasil[] = $item;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Mengurutkan hasil dari similarity tertinggi
            |--------------------------------------------------------------------------
            */

            usort(
                $hasil,
                fn($a, $b) =>
                    $b['similarity'] <=> $a['similarity']
            );

            /*
            |--------------------------------------------------------------------------
            | Memeriksa hasil diagnosis
            |--------------------------------------------------------------------------
            */

            if (empty($hasil)) {

                $pesan = 'Belum ditemukan kasus terverifikasi yang cocok dengan gejala pilihan. Sistem memerlukan basis kasus yang sudah diperiksa oleh pakar.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Simpan riwayat diagnosis otomatis
    |--------------------------------------------------------------------------
    */

    if ($error === '' && !empty($hasil)) {

        try {

            $terbaik = $hasil[0];

            $userId = !empty($_SESSION['user_id'])
                ? (int) $_SESSION['user_id']
                : null;

            // Membuat kode diagnosis unik
            $kodeDiagnosis = 'DGN'
                . date('YmdHis')
                . strtoupper(bin2hex(random_bytes(3)));

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | 1. Menyimpan data utama diagnosis
            |--------------------------------------------------------------------------
            */

            $stmtDiagnosis = $pdo->prepare(
                "INSERT INTO diagnosis (
                    kode_diagnosis,
                    user_id,
                    tanggal_diagnosis,
                    catatan_pengguna,
                    penyakit_terkonfirmasi_id
                ) VALUES (
                    :kode,
                    :user_id,
                    NOW(),
                    NULL,
                    :penyakit_id
                )"
            );

            $stmtDiagnosis->execute([

                ':kode' => $kodeDiagnosis,

                ':user_id' => $userId,

                ':penyakit_id' => (int) $terbaik['penyakit_id']

            ]);

            $diagnosisId = (int) $pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | 2. Menyimpan gejala pilihan pengguna
            |--------------------------------------------------------------------------
            */

            $stmtGejala = $pdo->prepare(
                "INSERT INTO diagnosis_gejala (
                    diagnosis_id,
                    gejala_id
                ) VALUES (?, ?)"
            );

            foreach ($pilihan as $idGejala) {

                $stmtGejala->execute([

                    $diagnosisId,

                    (int) $idGejala

                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Menyimpan seluruh hasil pencocokan kasus
            |--------------------------------------------------------------------------
            */

            $stmtHasil = $pdo->prepare(
                "INSERT INTO diagnosis_hasil (
                    diagnosis_id,
                    penyakit_id,
                    kasus_id,
                    nilai_similarity,
                    peringkat,
                    terpilih
                ) VALUES (?, ?, ?, ?, ?, ?)"
            );

            foreach ($hasil as $index => $item) {

                $peringkat = $index + 1;

                $stmtHasil->execute([

                    $diagnosisId,

                    (int) $item['penyakit_id'],

                    (int) $item['kasus_id'],

                    round((float) $item['similarity'], 4),

                    $peringkat,

                    $peringkat === 1 ? 1 : 0

                ]);
            }

            // Menyelesaikan transaksi penyimpanan
            $pdo->commit();

            /*
            |--------------------------------------------------------------------------
            | 4. Menyimpan hasil ke session untuk halaman hasil
            |--------------------------------------------------------------------------
            */

            $gejalaDipilih = array_values(array_filter(

                $gejala,

                fn($item) => in_array(
                    (int) $item['id'],
                    $pilihan,
                    true
                )

            ));

            $_SESSION['hasil_diagnosis'] = $hasil;

            $_SESSION['gejala_diagnosis'] = $gejalaDipilih;

            $_SESSION['kode_diagnosis_terakhir'] = $kodeDiagnosis;

            $_SESSION['id_diagnosis_terakhir'] = $diagnosisId;

            header('Location: hasil-diagnosis.php');

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                '[SI PADI BREBES] Gagal menyimpan diagnosis: '
                . $e->getMessage()
            );

            $error = 'Riwayat diagnosis gagal disimpan. Periksa log error PHP untuk mengetahui penyebabnya.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Diagnosis Penyakit - SI PADI BREBES</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 28px;
            background: #f3f7f3;
            color: #263524;
            font-family: Arial, Helvetica, sans-serif;
        }

        .container {
            max-width: 1250px;
            margin: 0 auto;
        }

        .hero-diagnosis {
            background: linear-gradient(
                115deg,
                #14532d 0%,
                #176534 55%,
                #27834b 100%
            );
            color: #fff;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 22px;
            box-shadow: 0 8px 24px rgba(20, 83, 45, .12);
        }

        .hero-brand {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .7px;
            margin-bottom: 17px;
        }

        .hero-brand-icon {
            font-size: 19px;
        }

        .hero-diagnosis h1 {
            color: #fff;
            font-size: 36px;
            line-height: 1.25;
            margin: 0 0 10px;
        }

        .hero-diagnosis p {
            color: #edf8ef;
            font-size: 16px;
            line-height: 1.7;
            margin: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .btn-primary {
            background: #24783b;
            color: #fff;
        }

        .btn-primary:hover {
            background: #1b602e;
        }

        .panel {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 5px 22px rgba(25, 70, 35, .06);
            overflow: hidden;
            margin-bottom: 22px;
        }

        .panel-top {
            padding: 20px 22px;
            border-bottom: 1px solid #edf1ed;
        }

        .panel-top h2 {
            margin: 0 0 8px;
            color: #176534;
            font-size: 20px;
        }

        .panel-top p {
            margin: 0;
            color: #718075;
            line-height: 1.6;
        }

        .panel-body {
            padding: 22px;
        }

        .info {
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 18px;
            line-height: 1.6;
        }

        .info-error {
            background: #fff0f0;
            color: #a12525;
            border: 1px solid #f2c7c7;
        }

        .info-warning {
            background: #fff8e7;
            color: #815b08;
            border: 1px solid #f1dfad;
        }

        .gejala-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .gejala-card {
            position: relative;
            border: 1px solid #dfe8df;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
        }

        .gejala-card:has(input:checked) {
            border-color: #24783b;
            box-shadow: 0 0 0 2px rgba(36, 120, 59, .12);
            background: #f7fcf7;
        }

        .gejala-card label {
            display: block;
            height: 100%;
            cursor: pointer;
        }

        .gambar-gejala {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
            background: #edf3ed;
            cursor: zoom-in;
            transition: transform .2s ease;
        }

        .gambar-gejala:hover {
            transform: scale(1.02);
        }

        .gambar-kosong {
            height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #edf3ed;
            color: #718075;
            font-size: 13px;
        }

        .gejala-info {
            padding: 14px;
        }

        .gejala-kode {
            display: inline-block;
            font-size: 19px;
            color: #24783b;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .gejala-nama {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.7;
            font-size: 17px;
            font-weight: 500;
            color: #24352a;
        }

        .gejala-nama input {
            margin-top: 5px;
            width: 20px;
            height: 20px;
            accent-color: #24783b;
            flex-shrink: 0;
            cursor: pointer;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .form-actions-right {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .catatan {
            color: #718075;
            font-size: 13px;
            line-height: 1.6;
            margin-top: 18px;
        }

        .modal-gambar {
            display: none;
            position: fixed;
            z-index: 9999;
            inset: 0;
            padding: 25px;
            background: rgba(0, 0, 0, .85);
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .modal-gambar.aktif {
            display: flex;
        }

        .modal-gambar img {
            max-width: 90%;
            max-height: 78vh;
            object-fit: contain;
            border-radius: 12px;
            background: #fff;
        }

        .modal-gambar p {
            color: #fff;
            font-size: 17px;
            line-height: 1.6;
            text-align: center;
            max-width: 700px;
        }

        .tutup-modal {
            position: absolute;
            top: 15px;
            right: 25px;
            border: 0;
            background: transparent;
            color: #fff;
            font-size: 42px;
            cursor: pointer;
        }

        @media (max-width: 900px) {

            .gejala-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 600px) {

            body {
                padding: 16px;
            }

            .hero-diagnosis {
                padding: 23px 20px;
                border-radius: 15px;
            }

            .hero-diagnosis h1 {
                font-size: 27px;
            }

            .hero-diagnosis p {
                font-size: 14px;
            }

            .gejala-grid {
                grid-template-columns: 1fr;
            }

            .panel-body {
                padding: 15px;
            }

            .gambar-gejala,
            .gambar-kosong {
                height: 210px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <header class="hero-diagnosis">

        <div class="hero-brand">
            <span class="hero-brand-icon">🌾</span>
            <span>SI PADI BREBES</span>
        </div>

        <h1>Diagnosis Penyakit Tanaman Padi</h1>

        <p>
            Hasil pencocokan gejala menggunakan metode
            Case Based Reasoning (CBR).
        </p>

    </header>

    <section class="panel">

        <div class="panel-top">

            <h2>Pilih Gejala Tanaman</h2>

            <p>
                Centang satu atau beberapa gejala sesuai kondisi
                tanaman padi yang sedang diperiksa.
            </p>

        </div>

        <div class="panel-body">

            <?php if ($error !== ''): ?>

                <div class="info info-error">
                    <?= h($error) ?>
                </div>

            <?php endif; ?>

            <?php if ($pesan !== ''): ?>

                <div class="info info-warning">
                    <?= h($pesan) ?>
                </div>

            <?php endif; ?>

            <?php if (empty($gejala)): ?>

                <div class="info info-warning">
                    Data gejala belum tersedia. Silakan tambahkan
                    data gejala terlebih dahulu.
                </div>

            <?php else: ?>

                <form method="POST" action="diagnosis.php">

                    <div class="gejala-grid">

                        <?php foreach ($gejala as $item): ?>

                            <?php

                            $idGejala = (int) $item['id'];

                            $gambar = pathGambar(
                                'assets/images/gejala/',
                                $item['gambar'] ?? ''
                            );

                            $terpilih = in_array(
                                $idGejala,
                                $pilihanTersimpan,
                                true
                            );

                            ?>

                            <div class="gejala-card">

                                <label>

                                    <?php if ($gambar !== ''): ?>

                                        <img
                                            class="gambar-gejala"
                                            src="<?= h($gambar) ?>"
                                            alt="<?= h($item['nama_gejala']) ?>"
                                            loading="lazy"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                        >

                                        <div
                                            class="gambar-kosong"
                                            style="display:none;"
                                        >
                                            Gambar belum tersedia
                                        </div>

                                    <?php else: ?>

                                        <div class="gambar-kosong">
                                            Gambar belum tersedia
                                        </div>

                                    <?php endif; ?>

                                    <div class="gejala-info">

                                        <span class="gejala-kode">
                                            <?= h($item['kode_gejala']) ?>
                                        </span>

                                        <div class="gejala-nama">

                                            <input
                                                type="checkbox"
                                                name="gejala[]"
                                                value="<?= $idGejala ?>"
                                                <?= $terpilih ? 'checked' : '' ?>
                                            >

                                            <span>
                                                <?= h($item['nama_gejala']) ?>
                                            </span>

                                        </div>

                                    </div>

                                </label>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <div class="form-actions">

                        <a
                            href="<?= h($linkKembali) ?>"
                            class="btn btn-kembali"
                        >
                            Kembali
                        </a>

                        <div class="form-actions-right">

                            <a
                                href="diagnosis.php"
                                class="btn btn-kembali"
                            >
                                Reset Pilihan
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Diagnosa Sekarang
                            </button>

                        </div>

                    </div>

                    <p class="catatan">
                        Hasil diagnosis bergantung pada kelengkapan
                        dan validasi basis kasus. Konsultasikan kembali
                        dengan penyuluh atau pakar apabila diperlukan.
                    </p>

                </form>

            <?php endif; ?>

        </div>

    </section>

</div>

<!-- Modal untuk memperbesar gambar gejala -->

<div id="modalGambar" class="modal-gambar">

    <button
        type="button"
        class="tutup-modal"
        id="tutupModal"
        aria-label="Tutup gambar"
    >&times;</button>

    <img
        id="gambarBesar"
        src=""
        alt="Gambar gejala diperbesar"
    >

    <p id="judulGambar"></p>

</div>

<script>

    const modalGambar = document.getElementById('modalGambar');

    const gambarBesar = document.getElementById('gambarBesar');

    const judulGambar = document.getElementById('judulGambar');

    const tutupModal = document.getElementById('tutupModal');

    document.querySelectorAll('.gambar-gejala').forEach(function(gambar) {

        gambar.addEventListener('click', function(event) {

            event.preventDefault();
            event.stopPropagation();

            gambarBesar.src = gambar.src;

            judulGambar.textContent = gambar.alt;

            modalGambar.classList.add('aktif');

        });

    });

    function tutupGambar() {

        modalGambar.classList.remove('aktif');

        gambarBesar.src = '';

    }

    tutupModal.addEventListener('click', tutupGambar);

    modalGambar.addEventListener('click', function(event) {

        if (event.target === modalGambar) {

            tutupGambar();

        }

    });

    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {

            tutupGambar();

        }

    });

</script>

</body>
</html>
