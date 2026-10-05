<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $_SESSION['fails'] = $_SESSION['fails'] ?? 0;
    if ($_SESSION['fails'] >= 10) {
        $errors[] = 'Çok fazla hatalı deneme. Daha sonra tekrar deneyin.';
    } else {
        $st = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
        $st->execute([trim((string)($_POST['username'] ?? ''))]);
        $a = $st->fetch();
        if ($a && password_verify((string)($_POST['password'] ?? ''), $a['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            $_SESSION['fails'] = 0;
            header('Location: index.php');
            exit;
        }
        $_SESSION['fails']++;
        $errors[] = 'Kullanıcı adı veya parola hatalı.';
    }
}
page_header('Yönetici Girişi', '../', true);
show_errors($errors);
?>
<form method="post">
<?= csrf_field() ?>
<div class="field"><label for="username">Kullanıcı adı</label>
<input id="username" name="username" required autocomplete="username"></div>
<div class="field"><label for="password">Parola</label>
<input id="password" name="password" type="password" required autocomplete="current-password"></div>
<button type="submit">Giriş yap</button>
</form>
<?php page_footer('../');
