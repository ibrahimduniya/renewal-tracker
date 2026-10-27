<?php
$id = (int)($_GET['id'] ?? 0);
$s = db()->prepare("SELECT * FROM services WHERE id = ?");
$s->execute([$id]);
$r = $s->fetch();
if (!$r) { flash('Record not found.'); redirect('index.php?page=services'); }
function view_date($date) {
    if (!$date) return '—';
    $d = days_left($date);
    [$cls, $label, $color] = status_of($d);
    return fmt_date($date) . ' &nbsp;<span class="badge" style="background:' . $color . '">' . e($label) . '</span>';
}
?>
<div class="card">
  <h3><?= e($r['name']) ?></h3>
  <table class="stack">
    <tbody>
      <tr><td><b>Client Name</b></td><td><?= e($r['client_name']) ?></td></tr>
      <tr><td><b>Client Phone</b></td><td><?= e($r['client_phone'] ?: '—') ?></td></tr>
      <tr><td><b>Domain</b></td><td><?= e($r['name']) ?></td></tr>
      <tr><td><b>Domain Expiry Date</b></td><td><?= view_date($r['domain_expiry']) ?></td></tr>
      <tr><td><b>Hosting</b></td><td><?= e($r['hosting'] ?: '—') ?></td></tr>
      <tr><td><b>Hosting Expiry Date</b></td><td><?= view_date($r['hosting_expiry']) ?></td></tr>
      <tr><td><b>Renewal Fee</b></td><td><?= $r['amount'] > 0 ? 'Tk ' . num(number_format((float)$r['amount'], 0)) : '—' ?></td></tr>
      <tr><td><b>Note</b></td><td><?= e($r['notes'] ?: '—') ?></td></tr>
    </tbody>
  </table>
  <div class="actions" style="margin-top:14px">
    <?php if ($r['domain_expiry']): ?>
    <form method="post" action="index.php?page=renew" onsubmit="return confirm('Mark the domain as renewed and extend its date by 1 year?')">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="kind" value="domain">
      <button class="btn green">Domain Renew ✓</button>
    </form>
    <?php endif; ?>
    <?php if ($r['hosting_expiry']): ?>
    <form method="post" action="index.php?page=renew" onsubmit="return confirm('Mark the hosting as renewed and extend its date by 1 year?')">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="kind" value="hosting">
      <button class="btn green">Hosting Renew ✓</button>
    </form>
    <?php endif; ?>
    <a class="btn grey" href="index.php?page=service_form&id=<?= (int)$r['id'] ?>">Edit</a>
    <form method="post" action="index.php?page=delete" onsubmit="return confirm('Really delete this?')">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn red">Delete</button>
    </form>
    <a class="btn" href="index.php?page=services">← Back to List</a>
  </div>
</div>
