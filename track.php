<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

$no = strtoupper(trim((string)($_GET['no'] ?? $_POST['no'] ?? '')));
$errors = [];
$ticket = null;
if ($no !== '') {
    $st = $pdo->prepare('SELECT t.*, s.name AS service FROM tickets t LEFT JOIN services s ON s.id = t.service_id WHERE t.ticket_no = ?');
    $st->execute([$no]);
    $ticket = $st->fetch() ?: null;
    if (!$ticket) {
        $errors[] = 'Bu numarayla bir talep bulunamadı.';
    }
}

if ($ticket && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $msg = trim((string)($_POST['message'] ?? ''));
    if ($ticket['status'] === 'closed') {
        $errors[] = 'Kapatılmış talebe yanıt verilemez.';
    } elseif ($msg === '' || mb_strlen($msg) > 5000) {
        $errors[] = 'Mesaj zorunludur (en fazla 5000 karakter).';
    } else {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO replies (ticket_id, is_admin, message, created_at) VALUES (?,0,?,?)')->execute([$ticket['id'], $msg, $now]);
        $pdo->prepare("UPDATE tickets SET status='open', updated_at=? WHERE id=?")->execute([$now, $ticket['id']]);
        notify_admin("Talebe müşteri yanıtı\nNo: {$ticket['ticket_no']}\nKonu: {$ticket['subject']}");
        header('Location: track.php?no=' . urlencode($no) . '&sent=1');
        exit;
    }
}

page_header('Talep Takibi');
show_errors($errors);
if (isset($_GET['created'])) {
    echo '<div class="alert ok" role="status"><p>Talebiniz oluşturuldu. Talep numaranız: <strong>' . e($no) . '</strong>. Bilgi Telegram botunuza gönderildi.</p></div>';
}
if (isset($_GET['sent'])) {
    echo '<div class="alert ok" role="status"><p>Mesajınız gönderildi.</p></div>';
}
?>
<form method="get" role="search" aria-label="Talep sorgula">
<div class="field"><label for="no">Talep numarası</label>
<input id="no" name="no" required placeholder="TD-XXXXXXXX" value="<?= e($no) ?>"></div>
<button type="submit">Sorgula</button>
</form>
<?php if ($ticket): ?>
<section aria-labelledby="t">
<h2 id="t"><?= e($ticket['subject']) ?></h2>
<dl>
<dt>Talep no</dt><dd><?= e($ticket['ticket_no']) ?></dd>
<dt>Durum</dt><dd><?= e(status_label($ticket['status'])) ?></dd>
<?php if ($ticket['service']): ?><dt>Hizmet</dt><dd><?= e($ticket['service']) ?></dd><?php endif; ?>
<dt>Tarih</dt><dd><?= e($ticket['created_at']) ?></dd>
</dl>
<h3>Yazışma</h3>
<ol class="thread">
<li><strong>Siz</strong> <time><?= e($ticket['created_at']) ?></time><p><?= nl2br(e($ticket['message'])) ?></p></li>
<?php
$r = $pdo->prepare('SELECT * FROM replies WHERE ticket_id = ? ORDER BY id');
$r->execute([$ticket['id']]);
foreach ($r as $row): ?>
<li class="<?= $row['is_admin'] ? 'staff' : '' ?>"><strong><?= $row['is_admin'] ? 'Destek ekibi' : 'Siz' ?></strong> <time><?= e($row['created_at']) ?></time><p><?= nl2br(e($row['message'])) ?></p></li>
<?php endforeach; ?>
</ol>
<?php if ($ticket['status'] !== 'closed'): ?>
<form method="post">
<?= csrf_field() ?><input type="hidden" name="no" value="<?= e($ticket['ticket_no']) ?>">
<div class="field"><label for="message">Yeni mesaj</label>
<textarea id="message" name="message" rows="5" required maxlength="5000"></textarea></div>
<button type="submit">Gönder</button>
</form>
<?php endif; ?>
</section>
<?php endif; page_footer();
