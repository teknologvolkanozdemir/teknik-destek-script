<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin.php';

$errors = [];
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'site') {
        $title = trim((string)($_POST['site_title'] ?? ''));
        $tok = trim((string)($_POST['admin_bot_token'] ?? ''));
        $chat = trim((string)($_POST['admin_chat_id'] ?? ''));
        if ($title === '' || mb_strlen($title) > 100) {
            $errors[] = 'Site başlığı zorunludur (en fazla 100 karakter).';
        }
        if ($tok !== '' && !valid_token($tok)) {
            $errors[] = 'Yönetici bot tokeni geçersiz biçimde.';
        }
        if ($chat !== '' && !valid_chat_id($chat)) {
            $errors[] = 'Yönetici chat ID geçersiz biçimde.';
        }
        if (($tok === '') !== ($chat === '')) {
            $errors[] = 'Bot tokeni ve chat ID birlikte girilmelidir.';
        }
        if (!$errors) {
            set_setting('site_title', $title);
            set_setting('admin_bot_token', $tok);
            set_setting('admin_chat_id', $chat);
            $notice = 'Ayarlar kaydedildi.';
        }
    } elseif ($act === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Hizmet adı zorunludur (en fazla 100 karakter).';
        } else {
            $pdo->prepare('INSERT INTO services (name) VALUES (?)')->execute([$name]);
            $notice = 'Hizmet eklendi.';
        }
    } elseif ($act === 'toggle') {
        $pdo->prepare('UPDATE services SET active = 1 - active WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        $notice = 'Hizmet durumu değiştirildi.';
    } elseif ($act === 'delete') {
        $sid = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE tickets SET service_id = NULL WHERE service_id = ?')->execute([$sid]);
        $pdo->prepare('DELETE FROM services WHERE id = ?')->execute([$sid]);
        $notice = 'Hizmet silindi.';
    } elseif ($act === 'test') {
        $ok = telegram_send(setting('admin_bot_token'), setting('admin_chat_id'), 'Test bildirimi: ' . setting('site_title'));
        if ($ok) {
            $notice = 'Test mesajı gönderildi.';
        } else {
            $errors[] = 'Test mesajı gönderilemedi.';
        }
    }
}
$services = $pdo->query('SELECT * FROM services ORDER BY name')->fetchAll();

page_header('Site Ayarları', '../', true);
show_errors($errors);
if ($notice) {
    echo '<div class="alert ok" role="status"><p>' . e($notice) . '</p></div>';
}
?>
<section aria-labelledby="s1"><h2 id="s1">Genel ayarlar</h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="site">
<div class="field"><label for="site_title">Site başlığı</label>
<input id="site_title" name="site_title" required maxlength="100" value="<?= e(setting('site_title', 'Teknik Destek')) ?>"></div>
<div class="field"><label for="admin_bot_token">Yönetici bildirim bot tokeni</label>
<input id="admin_bot_token" name="admin_bot_token" autocomplete="off" value="<?= e(setting('admin_bot_token')) ?>"></div>
<div class="field"><label for="admin_chat_id">Yönetici chat ID</label>
<input id="admin_chat_id" name="admin_chat_id" autocomplete="off" value="<?= e(setting('admin_chat_id')) ?>"></div>
<button type="submit">Kaydet</button></form>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test"><button type="submit">Test bildirimi gönder</button></form>
</section>
<section aria-labelledby="s2"><h2 id="s2">Destek ayarları: hizmetler</h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
<div class="field"><label for="name">Yeni hizmet adı</label>
<input id="name" name="name" required maxlength="100"></div>
<button type="submit">Hizmet ekle</button></form>
<table><caption class="sr-only">Destek verilen hizmetler</caption>
<thead><tr><th scope="col">Hizmet</th><th scope="col">Durum</th><th scope="col">İşlemler</th></tr></thead><tbody>
<?php foreach ($services as $s): ?>
<tr><th scope="row"><?= e($s['name']) ?></th><td><?= $s['active'] ? 'Aktif' : 'Pasif' ?></td><td>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="toggle">
<button type="submit"><?= $s['active'] ? 'Pasifleştir' : 'Aktifleştir' ?><span class="sr-only">: <?= e($s['name']) ?></span></button></form>
<form method="post" class="inline" data-confirm="Bu hizmet silinsin mi?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="delete">
<button type="submit">Sil<span class="sr-only">: <?= e($s['name']) ?></span></button></form></td></tr>
<?php endforeach; ?>
</tbody></table></section>
<?php page_footer('../');
