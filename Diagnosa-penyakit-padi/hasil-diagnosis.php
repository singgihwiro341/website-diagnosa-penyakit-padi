
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($nilai)
{
    return htmlspecialchars(
        (string) ($nilai ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function pathGambar($folder, $namaFile)
{
    $namaFile = basename(trim((string) ($namaFile ?? '')));

    return $namaFile !== ''
        ? $folder . rawurlencode($namaFile)
        : '';
}

$hasil = $_SESSION['hasil_diagnosis'] ?? [];
$gejalaDipilih = $_SESSION['gejala_diagnosis'] ?? [];

$linkKembali = !empty($_SESSION['user_id'])
    ? 'dashboard.php'
    : 'index.php';

if (!is_array($hasil)) {
    $hasil = [];
}

if (!is_array($gejalaDipilih)) {
    $gejalaDipilih = [];
}

// Jika halaman dibuka langsung tanpa melakukan diagnosis
if (empty($hasil)) {
    header('Location: diagnosis.php');
    exit;
}

$terbaik = $hasil[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hasil Diagnosis - SI PADI BREBES</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 28px 16px;
            background: #f2f7f2;
            color: #24352a;
            font-family: Arial, Helvetica, sans-serif;
        }

        .container {
            max-width: 1050px;
            margin: auto;
        }

        .hero {
            position: relative;
            overflow: hidden;
            padding: 30px;
            border-radius: 20px;
            color: white;
            background: linear-gradient(135deg, #124d2d, #278047);
            margin-bottom: 24px;
        }

        .brand {
            font-size: 13px;
            letter-spacing: 1.2px;
            font-weight: bold;
            color: #d5f2db;
        }

        .hero h1 {
            font-size: clamp(26px, 4vw, 36px);
            margin: 18px 0 10px;
        }

        .hero p {
            margin: 0;
            line-height: 1.7;
            color: #e2f1e5;
        }

        .panel {
            background: white;
            border: 1px solid #e3ece4;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 22px;
            box-shadow: 0 7px 25px rgba(27, 73, 39, .06);
        }

        .section-title {
            color: #176534;
            margin: 0 0 18px;
            font-size: 21px;
        }

        .winner {
            border: 2px solid #b8dfc1;
            background: linear-gradient(145deg, #ffffff, #f4fbf5);
        }

        .label {
            display: inline-block;
            background: #e0f3e4;
            color: #176534;
            border-radius: 30px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .disease-title {
            font-size: clamp(23px, 4vw, 30px);
            color: #176534;
            margin: 0 0 10px;
            line-height: 1.35;
        }

        .muted {
            color: #718075;
            font-size: 14px;
            line-height: 1.7;
        }

        .disease-image {
            display: block;
            width: 100%;
            max-width: 480px;
            max-height: 310px;
            object-fit: cover;
            border-radius: 12px;
            margin: 20px 0;
            background: #edf4ee;
        }

        .score-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 20px;
        }

        .score {
            font-size: 31px;
            font-weight: bold;
            color: #237a3b;
            white-space: nowrap;
        }

        .progress {
            height: 11px;
            border-radius: 20px;
            background: #e3eee5;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #2d8a49, #75bd6d);
            border-radius: 20px;
        }

        .symptom-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 12px;
        }

        .symptom {
            padding: 14px;
            border: 1px solid #e2ece3;
            border-radius: 11px;
            background: #fbfdfb;
            line-height: 1.6;
        }

        .symptom-code {
            display: inline-block;
            color: #237a3b;
            font-weight: bold;
            margin-right: 5px;
        }

        .solution {
            border-left: 4px solid #3b9653;
            border-radius: 8px;
            background: #f0f8f1;
            padding: 18px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .rank-card {
            border: 1px solid #e4ece5;
            border-radius: 12px;
            padding: 17px;
            margin-top: 12px;
        }

        .rank-card h3 {
            margin: 0 0 8px;
            color: #176534;
        }

        .rank-score {
            font-size: 19px;
            color: #237a3b;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 22px;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 12px 18px;
            border-radius: 9px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            border: 0;
            cursor: pointer;
        }

        .btn-primary {
            background: #24783b;
            color: white;
        }

        .btn-primary:hover {
            background: #195e2d;
        }

        .btn-secondary {
            color: #176534;
            background: #e4f2e6;
            border: 1px solid #c5e1cb;
        }

        .note {
            padding: 15px;
            border-radius: 10px;
            background: #fff8e8;
            border: 1px solid #f0dfb5;
            color: #72591b;
            line-height: 1.7;
            font-size: 13px;
        }

        .footer {
            text-align: center;
            padding: 18px;
            color: #718075;
            font-size: 12px;
            line-height: 1.7;
        }

        @media (max-width: 600px) {
            body {
                padding: 16px 11px;
            }

            .hero, .panel {
                padding: 20px;
            }

            .score {
                font-size: 26px;
            }

            .actions .btn {
                width: 100%;
            }
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .hero {
                background: #eaf5eb !important;
                color: #174d32 !important;
                print-color-adjust: exact;
            }

            .hero p, .brand {
                color: #174d32 !important;
            }

            .actions {
                display: none;
            }

            .panel {
                box-shadow: none;
                break-inside: avoid;
            }

            .container {
                max-width: none;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <header class="hero">
        <div class="brand">🌾 SI PADI BREBES</div>
        <h1>Hasil Diagnosis Tanaman Padi</h1>
        <p>
            Hasil pencocokan gejala menggunakan metode
            Case Based Reasoning (CBR).
        </p>
    </header>

    <section class="panel winner">
        <span class="label">HASIL DENGAN KEMIRIPAN TERTINGGI</span>

        <h2 class="disease-title">
            <?= h($terbaik['nama_penyakit'] ?? 'Penyakit tidak diketahui') ?>
        </h2>

        <p class="muted">
            Kode penyakit: <?= h($terbaik['kode_penyakit'] ?? '-') ?>
            · Kode kasus: <?= h($terbaik['kode_kasus'] ?? '-') ?>
        </p>

        <?php
        $gambar = pathGambar(
            'assets/images/penyakit/',
            $terbaik['gambar'] ?? ''
        );
        ?>

        <?php if ($gambar !== ''): ?>
            <img
                class="disease-image"
                src="<?= h($gambar) ?>"
                alt="<?= h($terbaik['nama_penyakit'] ?? 'Gambar penyakit') ?>"
                onerror="this.style.display='none'"
            >
        <?php endif; ?>

        <div class="score-row">
            <div>
                <strong>Nilai kemiripan CBR</strong>
                <div class="muted">Berdasarkan kasus yang dibandingkan</div>
            </div>
            <div class="score">
                <?= number_format((float) ($terbaik['similarity'] ?? 0), 2, ',', '.') ?>%
            </div>
        </div>

        <div class="progress">
            <div
                class="progress-bar"
                style="width: <?= min(100, max(0, (float) ($terbaik['similarity'] ?? 0))) ?>%"
            ></div>
        </div>

        <?php if (!empty($terbaik['deskripsi'])): ?>
            <h3>Informasi Penyakit</h3>
            <p style="line-height:1.8">
                <?= nl2br(h($terbaik['deskripsi'])) ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2 class="section-title">Gejala yang Dipilih</h2>

        <?php if (!empty($gejalaDipilih)): ?>
            <div class="symptom-list">
                <?php foreach ($gejalaDipilih as $g): ?>
                    <div class="symptom">
                        <span class="symptom-code">
                            <?= h($g['kode_gejala'] ?? '') ?>
                        </span>
                        <?= h($g['nama_gejala'] ?? '') ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="muted">Data gejala pilihan tidak tersedia.</p>
        <?php endif; ?>
    </section>

    <?php if (!empty($terbaik['solusi'])): ?>
        <section class="panel">
            <h2 class="section-title">Saran Penanganan</h2>
            <div class="solution">
                <?= nl2br(h($terbaik['solusi'])) ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (count($hasil) > 1): ?>
        <section class="panel">
            <h2 class="section-title">Perbandingan Kasus Lain</h2>
            <p class="muted">
                Daftar berikut diurutkan dari nilai kemiripan tertinggi.
            </p>

            <?php foreach (array_slice($hasil, 1) as $i => $item): ?>
                <div class="rank-card">
                    <h3>
                        Peringkat <?= $i + 2 ?> —
                        <?= h($item['nama_penyakit'] ?? '-') ?>
                    </h3>
                    <p class="muted">
                        Kode kasus: <?= h($item['kode_kasus'] ?? '-') ?>
                    </p>
                    <div class="rank-score">
                        <?= number_format((float) ($item['similarity'] ?? 0), 2, ',', '.') ?>%
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="note">
        <strong>Catatan:</strong> Hasil ini merupakan bantuan awal
        berdasarkan kemiripan dengan basis kasus yang tersedia, bukan
        kepastian diagnosis. Konfirmasikan hasil dan penanganan kepada
        penyuluh atau pakar tanaman padi.
    </div>

    <div class="actions">
        <a href="diagnosis.php" class="btn btn-primary">
            ↻ Diagnosis Ulang
        </a>

        <a href="<?= h($linkKembali) ?>" class="btn btn-secondary">
            Kembali
        </a>

        <button type="button" onclick="window.print()" class="btn btn-secondary">
            🖨 Cetak Hasil
        </button>
    </div>

    <footer class="footer">
        SI PADI BREBES · Sistem Pakar Diagnosis Penyakit Tanaman Padi
    </footer>
</div>
</body>
</html>
