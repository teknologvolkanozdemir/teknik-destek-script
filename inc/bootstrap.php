<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
if (!is_file(ROOT . '/config.php')) {
    header('Location: ' . (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'admin' ? '../' : '') . 'install.php');
    exit;
}
require ROOT . '/config.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'");

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Geçersiz istek (CSRF).');
    }
}

function setting(string $key, string $default = ''): string
{
    global $pdo;
    $st = $pdo->prepare('SELECT v FROM settings WHERE k = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string)$v;
}

function set_setting(string $key, string $value): void
{
    global $pdo;
    $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')->execute([$key, $value]);
}

function valid_token(string $t): bool
{
    return (bool)preg_match('/^\d{5,15}:[A-Za-z0-9_-]{30,50}$/', $t);
}

function valid_chat_id(string $c): bool
{
    return (bool)preg_match('/^(-?\d{1,20}|@[A-Za-z][A-Za-z0-9_]{3,31})$/', $c);
}

/** Telegram'a mesaj gönderir; başarılıysa true döner. */
function telegram_send(string $token, string $chatId, string $text): bool
{
    if (!valid_token($token) || !valid_chat_id($chatId)) {
        return false;
    }
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['chat_id' => $chatId, 'text' => mb_substr($text, 0, 4000)],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = is_string($res) ? json_decode($res, true) : null;
    return is_array($data) && !empty($data['ok']);
}

function notify_admin(string $text): void
{
    $t = setting('admin_bot_token');
    $c = setting('admin_chat_id');
    if ($t !== '' && $c !== '') {
        telegram_send($t, $c, $text);
    }
}

function base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if (substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}

function status_label(string $s): string
{
    return ['open' => 'Açık', 'answered' => 'Yanıtlandı', 'closed' => 'Kapalı'][$s] ?? $s;
}

function new_ticket_no(): string
{
    return 'TD-' . strtoupper(bin2hex(random_bytes(4)));
}

function page_header(string $title, string $assets = '', bool $admin = false): void
{
    $site = setting('site_title', 'Teknik Destek');
    $nav = $admin
        ? '<a href="index.php">Talepler</a><a href="settings.php">Ayarlar</a><form method="post" action="logout.php" class="inline">' . csrf_field() . '<button type="submit" class="link">Çıkış</button></form>'
        : '<a href="' . $assets . 'index.php">Destek Talebi Aç</a><a href="' . $assets . 'track.php">Talep Takibi</a>';
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex">'
        . '<title>' . e($title) . ' - ' . e($site) . '</title>'
        . '<link rel="stylesheet" href="' . $assets . 'assets/style.css"></head><body>'
        . '<a class="skip" href="#main">İçeriğe geç</a>'
        . '<header><p class="brand">' . e($site) . ($admin ? ' – Yönetim' : '') . '</p>'
        . '<nav aria-label="Ana menü">' . $nav . '</nav></header>'
        . '<main id="main" tabindex="-1"><h1>' . e($title) . '</h1>';
}

function page_footer(string $assets = ''): void
{
    echo '</main><script src="' . $assets . 'assets/app.js" defer></script></body></html>';
}

function show_errors(array $errors): void
{
    if ($errors) {
        echo '<div class="alert error" role="alert" id="errors" tabindex="-1"><p>Lütfen aşağıdaki hataları düzeltin:</p><ul>';
        foreach ($errors as $er) {
            echo '<li>' . e($er) . '</li>';
        }
        echo '</ul></div>';
    }
}
