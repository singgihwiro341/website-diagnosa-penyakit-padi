
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SI PADI BREBES - Sistem Pakar Tanaman Padi</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #263b2b;
            background: #f5f8f3;
        }

        .navbar {
            background: #14532d;
            color: white;
            padding: 18px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .brand {
            font-size: 25px;
            font-weight: bold;
        }

        .brand small {
            display: block;
            margin-top: 5px;
            font-size: 11px;
            font-weight: normal;
            color: #d1fae5;
        }

        .nav-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            font-size: 14px;
            padding: 10px 14px;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
        }

        .nav-link:hover {
            background: #176b3a;
        }

        .hero {
            padding: 75px 7%;
            background: linear-gradient(135deg, #e1f3df, #f5f8f3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
        }

        .hero-content {
            max-width: 620px;
        }

        .tag {
            color: #176b3a;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        h1 {
            margin: 18px 0;
            color: #14532d;
            font-size: clamp(32px, 5vw, 48px);
            line-height: 1.2;
        }

        .description {
            color: #56675a;
            line-height: 1.8;
            font-size: 16px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }

        .button {
            display: inline-block;
            padding: 14px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            text-align: center;
            cursor: pointer;
        }

        .button-primary {
            color: white;
            background: #176b3a;
        }

        .button-primary:hover {
            background: #104f2b;
        }

        .button-secondary {
            color: #176b3a;
            border: 1px solid #176b3a;
            background: white;
        }

        .button-secondary:hover {
            background: #e8f5e9;
        }

        .hero-icon {
            font-size: 130px;
            text-align: center;
            min-width: 200px;
        }

        .services {
            padding: 45px 7%;
        }

        .section-title {
            text-align: center;
            color: #14532d;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .section-description {
            text-align: center;
            color: #667568;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2eae0;
        }

        .card-icon {
            font-size: 32px;
        }

        .card h3 {
            color: #176b3a;
        }

        .card p {
            color: #667568;
            font-size: 14px;
            line-height: 1.7;
        }

        footer {
            padding: 24px 7%;
            background: #14532d;
            color: #e5f3e8;
            text-align: center;
            font-size: 13px;
            line-height: 1.8;
        }

        @media (max-width: 700px) {
            .hero {
                padding: 45px 6%;
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-icon {
                display: none;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .navbar {
                padding: 16px 5%;
            }

            .brand {
                font-size: 17px;
            }

            .nav-link {
                font-size: 12px;
                padding: 9px;
                white-space: nowrap;
            }
        }
    </style>
</head>

<body>

<header class="navbar">
    <div class="brand">
        🌾 SI PADI BREBES
        <small>Sistem Pakar Diagnosis Penyakit Padi</small>
    </div>

    <a class="nav-link" href="/diagnosa-penyakit-padi/login.php">
        Login Admin
    </a>
</header>

<main>
    <section class="hero">
        <div class="hero-content">
            <div class="tag">SOLUSI CERDAS UNTUK PETANI</div>

            <h1>Kenali Penyakit Padi Lebih Awal</h1>

            <p class="description">
                Bantu mengenali penyakit pada tanaman padi berdasarkan
                gejala yang terlihat. Pilih gejala yang dialami tanaman
                untuk mendapatkan hasil diagnosis dari sistem pakar.
            </p>           
<div class="actions">
    <a class="button button-primary"
       href="/diagnosa-penyakit-padi/diagnosis.php">
        Mulai Diagnosis 
    </a>
</div>
        </div>

        <div class="hero-icon" aria-hidden="true">🌾</div>
    </section>

    <section class="services">
        <h2 class="section-title">Layanan Sistem</h2>

        <p class="section-description">
            Informasi dan bantuan awal untuk mengenali penyakit tanaman padi.
        </p>

        <div class="cards">
            <article class="card">
                <div class="card-icon">🔎</div>
                <h3>Diagnosis Penyakit</h3>
                <p>
                    Pilih gejala tanaman padi untuk dicocokkan
                    dengan basis kasus yang tersedia dalam sistem.
                </p>
            </article>

            <article class="card">
                <div class="card-icon">🌱</div>
                <h3>Informasi Penyakit</h3>
                <p>
                    Kenali informasi penyakit dan saran penanganan
                    berdasarkan sumber yang telah diperiksa.
                </p>
            </article>

            <article class="card">
                <div class="card-icon">👨‍🌾</div>
                <h3>Akses Penyuluh</h3>
                <p>
                    Admin dan penyuluh dapat masuk melalui halaman login
                    untuk menggunakan fitur sesuai kewenangannya.
                </p>
            </article>
        </div>
    </section>
</main>

<footer>
    Sistem Pakar Diagnosis Penyakit pada Tanaman Padi<br>
    Studi Kasus: DPKP Kabupaten Brebes
</footer>

</body>
</html>
