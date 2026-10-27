<?php
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $s = db()->prepare("SELECT * FROM services WHERE client_name LIKE ? OR name LIKE ? OR hosting LIKE ?");
    $like = '%' . $q . '%';
    $s->execute([$like, $like, $like]);
    $rows = $s->fetchAll();
} else {
    $rows = db()->query("SELECT * FROM services")->fetchAll();
}
usort($rows, function ($a, $b) {
    $na = next_expiry($a); $nb = next_expiry($b);
    if ($na === null) return 1; if ($nb === null) return -1;
    return $na[2] <=> $nb[2];
});
function date_cell($date, $kindLabel) {
    if (!$date) return '<small class="hint">—</small>';
    $d = days_left($date);
    [$cls, $label, $color] = status_of($d);
    return fmt_date($date) . '<br><span class="badge" style="background:' . $color . '">' . e($label) . '</span>';
}
?>
<div class="card">
  <h3>All Websites (<?= num(count($rows)) ?>)</h3>
  <form class="search" method="get" style="margin-bottom:12px">
    <input type="hidden" name="page" value="services">
    <input name="q" value="<?= e($q) ?>" placeholder="Search by client name or domain…">
    <button class="btn">Search</button>
  </form>
  <div class="table-wrap">
  <table class="keep-table">
    <thead><tr><th>Domain Name</th><th>Domain Expiry Date</th><th>Hosting Expiry Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b><?= e($r['name']) ?></b><?php if ($r['client_name']): ?><br><small class="hint"><?= e($r['client_name']) ?></small><?php endif; ?></td>
        <td class="date"><?= date_cell($r['domain_expiry'], 'Domain') ?></td>
        <td class="date"><?= date_cell($r['hosting_expiry'], 'Hosting') ?></td>
        <td class="act">
          <div class="actions">
            <a class="btn small" href="index.php?page=view&id=<?= (int)$r['id'] ?>">View</a>
            <a class="btn small grey" href="index.php?page=service_form&id=<?= (int)$r['id'] ?>">Edit</a>
            <form method="post" action="index.php?page=delete" onsubmit="return confirm('Really delete this?')">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn small red">Delete</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4">Nothing found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
