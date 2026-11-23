<?php
require_once __DIR__ . '/config/auth.php';

wajibRole(['admin', 'penyuluh']);

require_once __DIR__ . '/config/database.php';

/*
|--------------------------------------------------------------------------
| Ringkasan data
|--------------------------------------------------------------------------
*/
$totalPenyakit = (int) $pdo->query(
    "SELECT COUNT(*) FROM penyakit"
)->fetchColumn();

$totalGejala = (int) $pdo->query(
    "SELECT COUNT(*) FROM gejala"
)->fetchColumn();

$totalKasus = (int) $pdo->query(
    "SELECT COUNT(*) FROM kasus"
)->fetchColumn();

$totalDiagnosis = (int) $pdo->query(
    "SELECT COUNT(*) FROM diagnosis"
)->fetchColumn();

/*
|--------------------------------------------------------------------------
| Lima diagnosis terbaru
|--------------------------------------------------------------------------
| LEFT JOIN memungkinkan diagnosis pengunjung tanpa akun ditampilkan.
| Hasil utama diambil dari diagnosis_hasil jika tersedia.
*/
$sqlAktivitas = "
    SELECT
        d.id,
        d.kode_diagnosis,
        d.tanggal_diagnosis,
        d.status_validasi,

        COALESCE(
            NULLIF(u.nama_lengkap, ''),
            'Petani / Pengunjung'
        ) AS nama_lengkap,

        COALESCE(
            ph.nama_penyakit,
            pk.nama_penyakit,
            'Belum tersedia'
        ) AS nama_penyakit,

        dh.nilai_similarity

    FROM diagnosis d

    LEFT JOIN users u
        ON u.id = d.user_id

    LEFT JOIN diagnosis_hasil dh
        ON dh.diagnosis_id = d.id
        AND dh.terpilih = 1

    LEFT JOIN penyakit ph
        ON ph.id = dh.penyakit_id

    LEFT JOIN penyakit pk
        ON pk.id = d.penyakit_terkonfirmasi_id

    ORDER BY d.id DESC
    LIMIT 5
";

$aktivitas = $pdo->query($sqlAktivitas)->fetchAll(PDO::FETCH_ASSOC);

function hDashboard($nilai)
{
    return htmlspecialchars(
        (string) ($nilai ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Sistem Pakar Padi Brebes</title>

    <style>
        * { box-sizing: border-box; }

        :root {
            --green: #176b3a;
            --green-dark: #104d2b;
            --green-light: #e8f5e9;
            --background: #f4f7f3;
            --text: #24352a;
            --muted: #718074;
            --border: #e4eae3;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--background);
            color: var(--text);
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: var(--green-dark);
            color: white;
            padding: 26px 17px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 28px;
            border-bottom: 1px solid #ffffff30;
        }

        .brand-icon {
            display: grid;
            place-items: center;
            width: 43px;
            height: 43px;
            border-radius: 12px;
            background: #ffffff20;
            font-size: 23px;
        }

        .brand h2 {
            margin: 0 0 5px;
            font-size: 16px;
        }

        .brand p {
            margin: 0;
            font-size: 11px;
            color: #c8dfcf;
        }

        .menu-title {
            margin: 28px 10px 10px;
            font-size: 10px;
            letter-spacing: 1.3px;
            color: #b9d5c1;
        }

        .nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 12px;
            margin: 5px 0;
            border-radius: 9px;
            color: #e5f1e8;
            text-decoration: none;
            font-size: 13px;
        }

        .nav a:hover,
        .nav a.active {
            background: #ffffff1c;
            color: white;
        }

        .nav .icon {
            width: 22px;
            text-align: center;
            font-size: 17px;
        }

        .sidebar-note {
            margin-top: 35px;
            padding: 14px;
            background: #ffffff12;
            border-radius: 10px;
            color: #d5e8d9;
            font-size: 12px;
            line-height: 1.6;
        }

        .main {
            min-width: 0;
            flex: 1;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 20px 30px;
            background: white;
            border-bottom: 1px solid var(--border);
        }

        .topbar p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .topbar strong {
            font-size: 14px;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--green-light);
            color: var(--green);
            font-weight: bold;
        }

        .content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .welcome {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 27px;
            color: white;
            border-radius: 16px;
            background: linear-gradient(115deg, #176b3a, #31945a);
            overflow: hidden;
        }

        .welcome h1 {
            margin: 0 0 10px;
            font-size: 25px;
        }

        .welcome p {
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

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin: 28px 0 16px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: 17px;
        }

        .section-heading p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
        }

        .stat {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 20px;
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 11px;
            background: var(--green-light);
            font-size: 21px;
        }

        .stat-label {
            margin-top: 17px;
            color: var(--muted);
            font-size: 12px;
        }

        .stat-number {
            margin-top: 7px;
            font-size: 29px;
            font-weight: bold;
        }

        .stat small {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 11px;
        }

        .panel-full {
            margin-top: 22px;
            padding: 22px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
        }

        .panel-full h3 {
            margin: 0 0 7px;
            font-size: 15px;
        }

        .panel-desc {
            margin: 0 0 20px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        /* Area daftar Diagnosis Terbaru (Ringkas) */
        .aktivitas-list {
            max-height: 320px;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 4px;
            scrollbar-width: thin;
            scrollbar-color: #a7cdb1 transparent;
        }

        .aktivitas-list::-webkit-scrollbar {
            width: 4px;
        }

        .aktivitas-list::-webkit-scrollbar-thumb {
            background: #a7cdb1;
            border-radius: 10px;
        }

        .activity {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }

        .activity:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .activity:first-child {
            padding-top: 0;
        }

        .activity-icon {
            display: grid;
            place-items: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: var(--green-light);
            color: var(--green);
            flex-shrink: 0;
            font-size: 13px;
        }

        .activity-content {
            min-width: 0;
            flex: 1;
            font-size: 12px;
        }

        .activity-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .activity-code {
            font-weight: bold;
            color: var(--muted);
            font-size: 11px;
        }

        .activity-date {
            color: var(--muted);
            font-size: 10px;
        }

        .activity-disease {
            display: block;
            margin: 2px 0;
            color: var(--green);
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .activity-meta {
            color: var(--muted);
            font-size: 11px;
        }

        .similarity {
            display: inline-block;
            padding: 1px 5px;
            background: var(--green-light);
            color: var(--green);
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }

        .empty-state {
            padding: 15px 0;
            text-align: center;
            color: var(--muted);
            font-size: 12px;
        }

        footer {
            padding: 22px 30px;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
        }

        @media (max-width: 1050px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                padding: 16px;
            }

            .nav {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 3px;
            }

            .sidebar-note {
                display: none;
            }

            .topbar, .content {
                padding: 16px;
            }
        }
    </style>
</head>

<body>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">🌾</div>
            <div>
                <h2>Padi Brebes</h2>
                <p>Sistem Pakar CBR</p>
            </div>
        </div>

        <p class="menu-title">MENU UTAMA</p>

        <nav class="nav">
            <a class="active" href="dashboard.php">
                <span class="icon">▦</span> Dashboard
            </a>

            <a href="logout.php">
                <span class="icon">↪</span> Logout
            </a>
        </nav>

        <p class="menu-title">DATA PENGETAHUAN</p>

        <nav class="nav">
            <a href="penyakit.php">
                <span class="icon">✚</span> Data Penyakit
            </a>

            <a href="gejala.php">
                <span class="icon">☷</span> Data Gejala
            </a>

            <a href="kasus.php">
                <span class="icon">▤</span> Data Basis Kasus 
            </a>
        </nav>

        <div class="sidebar-note">
            <strong>Informasi</strong><br>
            Basis pengetahuan perlu divalidasi oleh pakar sebelum digunakan untuk diagnosis.
        </div>
    </aside>

    <div class="main">

        <header class="topbar">
            <div>
                <strong>Dashboard</strong>
                <p>Sistem Pakar Diagnosis Penyakit Tanaman Padi</p>
            </div>

            <div class="profile">
                <div class="avatar">
                    <?= hDashboard(strtoupper(substr($_SESSION['nama_lengkap'] ?? 'U', 0, 1))) ?>
                </div>
                <div>
                    <strong><?= hDashboard($_SESSION['nama_lengkap'] ?? 'Pengguna') ?></strong>
                </div>
            </div>
        </header>

        <main class="content">

            <section class="welcome">
                <div>
                    <h1>Selamat Datang</h1>
                    <p>
                        Sistem Pakar Diagnosis Penyakit pada Tanaman Padi
                        menggunakan metode Case Based Reasoning (CBR),
                        studi kasus DPKP Kabupaten Brebes.
                    </p>
                </div>
                <div class="welcome-art">🌾</div>
            </section>

            <div class="section-heading">
                <div>
                    <h2>Ringkasan Data</h2>
                    <p>Jumlah data yang saat ini tersimpan di database.</p>
                </div>
            </div>

            <section class="stats">
                <div class="stat">
                    <div class="stat-top">
                        <span class="stat-icon">🌱</span>
                    </div>
                    <div class="stat-label">Data Penyakit</div>
                    <div class="stat-number"><?= $totalPenyakit ?></div>
                    <small>Jenis penyakit terdaftar</small>
                </div>

                <div class="stat">
                    <div class="stat-top">
                        <span class="stat-icon">🔎</span>
                    </div>
                    <div class="stat-label">Data Gejala</div>
                    <div class="stat-number"><?= $totalGejala ?></div>
                    <small>Gejala terdaftar</small>
                </div>

                <div class="stat">
                    <div class="stat-top">
                        <span class="stat-icon">📚</span>
                    </div>
                    <div class="stat-label">Basis Kasus</div>
                    <div class="stat-number"><?= $totalKasus ?></div>
                    <small>Kasus tersimpan</small>
                </div>

                <div class="stat">
                    <div class="stat-top">
                        <span class="stat-icon">📋</span>
                    </div>
                    <div class="stat-label">Riwayat Diagnosis</div>
                    <div class="stat-number"><?= $totalDiagnosis ?></div>
                    <small>Diagnosis tercatat</small>
                </div>
            </section>

            <div class="panel-full">
                <div class="panel-header">
                    <h3>Diagnosis Terbaru</h3>
                    <p class="panel-desc" style="margin-bottom: 12px;">5 catatan diagnosis terakhir.</p>
                </div>

                <div class="aktivitas-list">
                    <?php if (!empty($aktivitas)): ?>
                        <?php foreach ($aktivitas as $item): ?>
                            <div class="activity">
                                <div class="activity-icon">🌾</div>

                                <div class="activity-content">
                                    <div class="activity-top">
                                        <span class="activity-code"><?= hDashboard($item['kode_diagnosis'] ?? '-') ?></span>
                                        <?php if (!empty($item['tanggal_diagnosis'])): ?>
                                            <span class="activity-date"><?= date('d/m/y H:i', strtotime($item['tanggal_diagnosis'])) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <span class="activity-disease" title="<?= hDashboard($item['nama_penyakit']) ?>">
                                        <?= hDashboard($item['nama_penyakit']) ?>
                                    </span>

                                    <div class="activity-meta">
                                        <?= hDashboard($item['nama_lengkap']) ?> 
                                        <?php if ($item['nilai_similarity'] !== null): ?>
                                            • <span class="similarity"><?= number_format((float) $item['nilai_similarity'], 1, ',', '.') ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <p style="margin: 0;">Belum ada riwayat diagnosis.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <footer>
                Sistem Pakar Diagnosis Penyakit Tanaman Padi · CBR · DPKP Kabupaten Brebes
            </footer>

        </main>
    </div>
</div>
</body>
</html>