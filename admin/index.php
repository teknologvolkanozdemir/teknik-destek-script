<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin.php';

$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$where = [];
$args = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(t.ticket_no LIKE ? OR t.subject LIKE ?)';
    array_push($args, $like, $like);
}
if (in_array($status, ['open', 'answered', 'closed'], true)) {
    $where[] = 't.status = ?';
    $args[] = $status;
}
$sql = 'SELECT t.id, t.ticket_no, t.subject, t.status, t.updated_at, s.name AS service FROM tickets t LEFT JOIN services s ON s.id = t.service_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY t.updated_at DESC LIMIT 200';
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

page_header('Destek Talepleri', '../', true);
?>
<form method="get" role="search" aria-label="Talep ara" class="searchbar">
<div class="field"><label for="q">Talep numarası veya konu başlığı</label>
<input id="q" name="q" type="search" value="<?= e($q) ?>"></div>
<div class="field"><label for="status">Durum</label>
<select id="status" name="status"><option value="">Tümü</option>
<?php foreach (['open', 'answered', 'closed'] as $s): ?>
<option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= e(status_label($s)) ?></option>
<?php endforeach; ?></select></div>
<button type="submit">Ara</button>
</form>
<p role="status"><?= count($rows) ?> talep listeleniyor.</p>
<table>
<caption class="sr-only">Destek talepleri listesi</caption>
<thead><tr><th scope="col">Talep no</th><th scope="col">Konu</th><th scope="col">Hizmet</th><th scope="col">Durum</th><th scope="col">Son işlem</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr><th scope="row"><a href="ticket.php?id=<?= (int)$r['id'] ?>"><?= e($r['ticket_no']) ?></a></th>
<td><?= e($r['subject']) ?></td><td><?= e($r['service'] ?? '-') ?></td>
<td><?= e(status_label($r['status'])) ?></td><td><?= e($r['updated_at']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php page_footer('../');
