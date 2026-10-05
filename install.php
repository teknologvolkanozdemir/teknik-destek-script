<?php
declare(strict_types=1);
session_start();
if (is_file(__DIR__ . '/config.php')) {
    exit('Kurulum zaten yapılmış. Güvenlik için install.php dosyasını silin.');
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$errors = [];
$v = ['host' => 'localhost', 'db' => '', 'user' => '', 'admin' => 'admin'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        exit('Geçersiz istek.');
    }
    foreach ($v as $k => $_) {
        $v[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $dbpass = (string)($_POST['dbpass'] ?? '');
    $apass = (string)($_POST['apass'] ?? '');
    if ($v['db'] === '' || $v['user'] === '' || $v['admin'] === '') {
        $errors[] = 'Veritabanı adı, kullanıcı ve yönetici kullanıcı adı zorunludur.';
    }
    if (strlen($apass) < 8) {
        $errors[] = 'Yönetici parolası en az 8 karakter olmalıdır.';
    }
    if (!$errors) {
        try {
            $pdo = new PDO("mysql:host={$v['host']};dbname={$v['db']};charset=utf8mb4", $v['user'], $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
            $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')->execute([$v['admin'], password_hash($apass, PASSWORD_DEFAULT)]);
            $pdo->prepare("INSERT INTO settings (k, v) VALUES ('site_title', 'Teknik Destek')")->execute();
            $cfg = "<?php\ndefine('DB_HOST', " . var_export($v['host'], true) . ");\ndefine('DB_NAME', " . var_export($v['db'], true)
                . ");\ndefine('DB_USER', " . var_export($v['user'], true) . ");\ndefine('DB_PASS', " . var_export($dbpass, true) . ");\n";
            if (file_put_contents(__DIR__ . '/config.php', $cfg) === false) {
                $errors[] = 'config.php yazılamadı; dizin yazma izinlerini kontrol edin.';
            } else {
                header('Location: admin/login.php');
                exit;
            }
        } catch (PDOException $ex) {
            $errors[] = 'Veritabanı hatası: ' . $ex->getMessage();
        }
    }
}
?><!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kurulum</title><link rel="stylesheet" href="assets/style.css"></head><body><main id="main"><h1>Kurulum</h1>
<?php if ($errors): ?><div class="alert error" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
<div class="field"><label for="host">MySQL sunucu</label><input id="host" name="host" required value="<?= h($v['host']) ?>"></div>
<div class="field"><label for="db">Veritabanı adı (önceden oluşturulmuş)</label><input id="db" name="db" required value="<?= h($v['db']) ?>"></div>
<div class="field"><label for="user">Veritabanı kullanıcısı</label><input id="user" name="user" required value="<?= h($v['user']) ?>"></div>
<div class="field"><label for="dbpass">Veritabanı parolası</label><input id="dbpass" name="dbpass" type="password" autocomplete="off"></div>
<div class="field"><label for="admin">Yönetici kullanıcı adı</label><input id="admin" name="admin" required value="<?= h($v['admin']) ?>"></div>
<div class="field"><label for="apass">Yönetici parolası (en az 8 karakter)</label><input id="apass" name="apass" type="password" required autocomplete="new-password"></div>
<button type="submit">Kur</button></form></main></body></html>
