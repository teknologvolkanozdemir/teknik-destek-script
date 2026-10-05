<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

$services = $pdo->query('SELECT id, name FROM services WHERE active = 1 ORDER BY name')->fetchAll();
$errors = [];
$v = ['name' => '', 'service_id' => '', 'subject' => '', 'message' => '', 'bot_token' => '', 'chat_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $k => $_) {
        $v[$k] = trim((string)($_POST[$k] ?? ''));
    }
    if ($v['name'] === '' || mb_strlen($v['name']) > 100) {
        $errors[] = 'Ad soyad zorunludur (en fazla 100 karakter).';
    }
    $sid = null;
    if ($services) {
        $ids = array_column($services, 'id');
        if (!in_array($v['service_id'], array_map('strval', $ids), true)) {
            $errors[] = 'Lütfen bir hizmet seçin.';
        } else {
            $sid = (int)$v['service_id'];
        }
    }
    if ($v['subject'] === '' || mb_strlen($v['subject']) > 200) {
        $errors[] = 'Konu başlığı zorunludur (en fazla 200 karakter).';
    }
    if ($v['message'] === '' || mb_strlen($v['message']) > 5000) {
        $errors[] = 'Mesaj zorunludur (en fazla 5000 karakter).';
    }
    if ($v['bot_token'] === '') {
        $errors[] = 'Telegram bot tokeni zorunludur.';
    } elseif (!valid_token($v['bot_token'])) {
        $errors[] = 'Telegram bot tokeni geçersiz biçimde.';
    }
    if ($v['chat_id'] === '') {
        $errors[] = 'Telegram chat ID zorunludur.';
    } elseif (!valid_chat_id($v['chat_id'])) {
        $errors[] = 'Telegram chat ID geçersiz biçimde.';
    }

    if (!$errors) {
        $no = new_ticket_no();
        $link = base_url() . '/track.php?no=' . $no;
        $sent = telegram_send($v['bot_token'], $v['chat_id'],
            "Destek talebiniz oluşturuldu.\nTalep no: $no\nKonu: {$v['subject']}\nTakip: $link");
        if (!$sent) {
            $errors[] = 'Telegram bot tokeni veya chat ID doğrulanamadı; bota mesaj gönderilemedi. Botunuza önce /start yazdığınızdan emin olun. Talep oluşturulmadı.';
        } else {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO tickets (ticket_no, service_id, name, subject, message, bot_token, chat_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$no, $sid, $v['name'], $v['subject'], $v['message'], $v['bot_token'], $v['chat_id'], $now, $now]);
            notify_admin("Yeni destek talebi\nNo: $no\nKonu: {$v['subject']}\nGönderen: {$v['name']}");
            header('Location: track.php?no=' . urlencode($no) . '&created=1');
            exit;
        }
    }
}

page_header('Destek Talebi Oluştur');
show_errors($errors);
?>
<p>Üyelik gerekmez. Bildirimler e-posta ile değil, Telegram botunuz üzerinden gönderilir. Bot tokeni ve chat ID girmeniz zorunludur.</p>
<form method="post" novalidate>
<?= csrf_field() ?>
<div class="field"><label for="name">Ad soyad</label>
<input id="name" name="name" required maxlength="100" autocomplete="name" value="<?= e($v['name']) ?>"></div>
<?php if ($services): ?>
<div class="field"><label for="service_id">Hizmet</label>
<select id="service_id" name="service_id" required>
<option value="">Seçiniz</option>
<?php foreach ($services as $s): ?>
<option value="<?= (int)$s['id'] ?>"<?= $v['service_id'] === (string)$s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
<?php endforeach; ?>
</select></div>
<?php endif; ?>
<div class="field"><label for="subject">Konu başlığı</label>
<input id="subject" name="subject" required maxlength="200" value="<?= e($v['subject']) ?>"></div>
<div class="field"><label for="message">Mesajınız</label>
<textarea id="message" name="message" rows="7" required maxlength="5000"><?= e($v['message']) ?></textarea></div>
<div class="field"><label for="bot_token">Telegram bot tokeni (zorunlu)</label>
<input id="bot_token" name="bot_token" required autocomplete="off" aria-describedby="h1" value="<?= e($v['bot_token']) ?>">
<p class="hint" id="h1">@BotFather ile oluşturduğunuz botun tokeni. Örnek: 123456789:AA...</p></div>
<div class="field"><label for="chat_id">Telegram chat ID (zorunlu)</label>
<input id="chat_id" name="chat_id" required autocomplete="off" aria-describedby="h2" value="<?= e($v['chat_id']) ?>">
<p class="hint" id="h2">Kendi chat ID'niz. Botunuza önce /start yazmalısınız.</p></div>
<button type="submit">Talebi Gönder</button>
</form>
<?php page_footer();
