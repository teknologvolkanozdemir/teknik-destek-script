<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin.php';

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT t.*, s.name AS service FROM tickets t LEFT JOIN services s ON s.id = t.service_id WHERE t.id = ?');
$st->execute([$id]);
$t = $st->fetch();
if (!$t) {
    http_response_code(404);
    exit('Talep bulunamadı.');
}
$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $msg = trim((string)($_POST['message'] ?? ''));
    $new = (string)($_POST['status'] ?? 'answered');
    if (!in_array($new, ['open', 'answered', 'closed'], true)) {
        $new = 'answered';
    }
    if ($msg === '' && $new === $t['status']) {
        $errors[] = 'Yanıt yazın veya durumu değiştirin.';
    } elseif (mb_strlen($msg) > 5000) {
        $errors[] = 'Yanıt en fazla 5000 karakter olabilir.';
    } else {
        $now = date('Y-m-d H:i:s');
        if ($msg !== '') {
            $pdo->prepare('INSERT INTO replies (ticket_id, is_admin, message, created_at) VALUES (?,1,?,?)')->execute([$id, $msg, $now]);
        }
        $pdo->prepare('UPDATE tickets SET status=?, updated_at=? WHERE id=?')->execute([$new, $now, $id]);
        $text = "Talebiniz güncellendi.\nTalep no: {$t['ticket_no']}\nKonu: {$t['subject']}\nDurum: " . status_label($new)
            . ($msg !== '' ? "\n\nYanıt:\n$msg" : '') . "\n\nTakip: " . base_url() . '/track.php?no=' . $t['ticket_no'];
        $ok = telegram_send($t['bot_token'], $t['chat_id'], $text);
        $notice = 'Kaydedildi.' . ($ok ? ' Kullanıcıya Telegram ile bildirildi.' : ' Ancak Telegram bildirimi gönderilemedi.');
        $st->execute([$id]);
        $t = $st->fetch();
    }
}
$r = $pdo->prepare('SELECT * FROM replies WHERE ticket_id = ? ORDER BY id');
$r->execute([$id]);

page_header('Talep ' . $t['ticket_no'], '../', true);
show_errors($errors);
if ($notice) {
    echo '<div class="alert ok" role="status"><p>' . e($notice) . '</p></div>';
}
?>
<p><a href="index.php">&larr; Taleplere dön</a></p>
<h2><?= e($t['subject']) ?></h2>
<dl><dt>Durum</dt><dd><?= e(status_label($t['status'])) ?></dd>
<dt>Gönderen</dt><dd><?= e($t['name']) ?></dd>
<dt>Hizmet</dt><dd><?= e($t['service'] ?? '-') ?></dd>
<dt>Tarih</dt><dd><?= e($t['created_at']) ?></dd></dl>
<h3>Yazışma</h3>
<ol class="thread">
<li><strong><?= e($t['name']) ?></strong> <time><?= e($t['created_at']) ?></time><p><?= nl2br(e($t['message'])) ?></p></li>
<?php foreach ($r as $row): ?>
<li class="<?= $row['is_admin'] ? 'staff' : '' ?>"><strong><?= $row['is_admin'] ? 'Destek ekibi' : e($t['name']) ?></strong> <time><?= e($row['created_at']) ?></time><p><?= nl2br(e($row['message'])) ?></p></li>
<?php endforeach; ?>
</ol>
<form method="post">
<?= csrf_field() ?>
<div class="field"><label for="message">Yanıt</label>
<textarea id="message" name="message" rows="6" maxlength="5000"></textarea></div>
<div class="field"><label for="status">Durum</label>
<select id="status" name="status">
<?php foreach (['answered', 'open', 'closed'] as $s): ?>
<option value="<?= $s ?>"<?= $s === 'answered' ? ' selected' : '' ?>><?= e(status_label($s)) ?></option>
<?php endforeach; ?></select></div>
<button type="submit">Yanıtla ve bildir</button>
</form>
<?php page_footer('../');
