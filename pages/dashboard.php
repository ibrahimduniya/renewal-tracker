<?php
$rows = db()->query("SELECT * FROM services")->fetchAll();
$total = count($rows); $in7 = 0; $in30 = 0; $expired = 0;
foreach ($rows as $r) {
    $n = next_expiry($r);
    if ($n === null) continue;
    if ($n[2] < 0) $expired++; elseif ($n[2] <= 7) $in7++; elseif ($n[2] <= 30) $in30++;
}
usort($rows, function ($a, $b) {
    $na = next_expiry($a); $nb = next_expiry($b);
    if ($na === null) return 1; if ($nb === null) return -1;
    return $na[2] <=> $nb[2];
});
?>
<div class="stats">
  <div class="stat"><div class="n"><?= num($total) ?></div><div class="l">Total Websites</div></div>
  <div class="stat"><div class="n" style="color:#dc2626"><?= num($in7) ?></div><div class="l">Expiring within 7 days (domain/hosting)</div></div>
  <div class="stat"><div class="n" style="color:#d97706"><?= num($in30) ?></div><div class="l">Expiring within 30 days</div></div>
  <div class="stat"><div class="n" style="color:#b91c1c"><?= num($expired) ?></div><div class="l">Already expired</div></div>
</div>
<div class="card">
  <h3>Expiring soonest first</h3>
  <?php if (!$rows): ?>
    <p>No websites added yet. <a href="index.php?page=service_form">Add the first one</a>.</p>
  <?php else: ?>
  <table class="stack">
    <thead><tr><th>Client</th><th>Domain</th><th>Domain Expiry</th><th>Hosting Expiry</th><th>Most Urgent</th><th></th></tr></thead>
    <tbody>
    <?php foreach (array_slice($rows, 0, 10) as $r): $n = next_expiry($r); ?>
      <tr>
        <td><b><?= e($r['client_name']) ?></b><?php if ($r['client_phone']): ?><br><small class="hint"><?= e($r['client_phone']) ?></small><?php endif; ?></td>
        <td><?= e($r['name']) ?></td>
        <td><?= $r['domain_expiry'] ? fmt_date($r['domain_expiry']) : '—' ?></td>
        <td><?= $r['hosting_expiry'] ? fmt_date($r['hosting_expiry']) : '—' ?></td>
        <td><?php if ($n): [$c, $l, $col] = status_of($n[2]); ?><span class="badge" style="background:<?= $col ?>"><?= $n[0] === 'domain' ? 'Domain' : 'Hosting' ?>: <?= e($l) ?></span><?php endif; ?></td>
        <td><a class="btn small grey" href="index.php?page=service_form&id=<?= (int)$r['id'] ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
