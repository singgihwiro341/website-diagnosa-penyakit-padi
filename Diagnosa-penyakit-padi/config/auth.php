
<?php
if (session_status() === PHP_SESSION_NONE) {
    // Cookie session hanya dikirim melalui HTTP lokal
    // pada konfigurasi pengembangan ini.
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function wajibLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function wajibRole(array $roles): void
{
    wajibLogin();

    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('Akses ditolak. Kamu tidak memiliki izin membuka halaman ini.');
    }
}
