<?php
$id = (int)($_GET['id'] ?? 0);
$svc = null;
if ($id) {
    $s = db()->prepare("SELECT * FROM services WHERE id = ?");
    $s->execute([$id]);
    $svc = $s->fetch();
    if (!$svc) { flash('Record not found.'); redirect('index.php?page=services'); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $client  = trim($_POST['client_name'] ?? '');
    $phone   = trim($_POST['client_phone'] ?? '');
    $name    = trim($_POST['name'] ?? '');
    $dexpRaw = trim($_POST['domain_expiry'] ?? '');
    $hexpRaw = trim($_POST['hosting_expiry'] ?? '');
    $dexp    = parse_date_input($dexpRaw) ?? '';
    $hosting = trim($_POST['hosting'] ?? '');
    $hexp    = parse_date_input($hexpRaw) ?? '';
    $dateErr = ($dexpRaw !== '' && $dexp === '') || ($hexpRaw !== '' && $hexp === '');
    $amount  = (float)($_POST['amount'] ?? 0);
    $notes   = trim($_POST['notes'] ?? '');
    // If the domain date is empty, try to fetch it automatically via RDAP
    if ($dexp === '' && $name !== '') {
        $auto = rdap_expiry($name);
        if ($auto) $dexp = $auto;
    }
    if ($client === '' || $name === '' || ($dexp === '' && $hexp === '')) {
        $error = $dateErr
            ? 'Enter dates in the correct format: dd-mm-yyyy (e.g. 02-11-2026).'
            : 'Client name and domain are required, and at least one expiry date is needed.' . ($dexp === '' && $name !== '' ? ' (The domain date could not be fetched via RDAP either — type it manually.)' : '');
        $svc = ['client_name' => $client, 'client_phone' => $phone, 'name' => $name,
                'domain_expiry' => $dexpRaw, 'hosting' => $hosting, 'hosting_expiry' => $hexpRaw,
                'amount' => $amount, 'notes' => $notes, 'id' => $id];
    } else {
        $vals = [$client, $phone, $name, $dexp ?: null, $hosting, $hexp ?: null, $amount, $notes];
        if ($id) {
            $u = db()->prepare("UPDATE services SET client_name=?, client_phone=?, name=?, domain_expiry=?, hosting=?, hosting_expiry=?, amount=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $u->execute([...$vals, $id]);
            flash('Updated.');
        } else {
            // Legacy columns (type, expiry_date) — filled because v1 tables may have them as NOT NULL
            $legacyExp = $dexp ?: ($hexp ?: date('Y-m-d'));
            $u = db()->prepare("INSERT INTO services (client_name, client_phone, name, domain_expiry, hosting, hosting_expiry, amount, notes, type, expiry_date) VALUES (?,?,?,?,?,?,?,?, '', ?)");
            $u->execute([...$vals, $legacyExp]);
            flash('New website added.');
        }
        redirect('index.php?page=services');
    }
}
$v = fn($k, $d = '') => e($svc[$k] ?? $d);
?>
<div class="card">
  <h3><?= $id ? 'Edit Website' : 'Add a New Website' ?></h3>
  <?php if (!empty($error)): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="grid2">
      <div><label>Client Name *</label><input name="client_name" required value="<?= $v('client_name') ?>"></div>
      <div><label>Client Phone</label><input name="client_phone" value="<?= $v('client_phone') ?>"></div>
    </div>
    <div class="grid2">
      <div><label>Domain</label><input name="name" id="nameFld" value="<?= $v('name') ?>" placeholder="example.com"></div>
      <div><label>Expiry Date (Domain)</label><input type="text" name="domain_expiry" id="expFld" placeholder="dd-mm-yyyy" value="<?= e(date_for_input($svc['domain_expiry'] ?? null) ?: ($svc['domain_expiry'] ?? '')) ?>">
        <small class="hint">Leave empty to fetch automatically online (RDAP). <a href="#" id="rdapBtn">Fetch now</a> <span id="rdapMsg"></span></small></div>
    </div>
    <div class="grid2">
      <div><label>Hosting</label><input name="hosting" value="<?= $v('hosting') ?>" placeholder="Hosting account / provider"></div>
      <div><label>Expiry Date (Hosting)</label><input type="text" name="hosting_expiry" placeholder="dd-mm-yyyy" value="<?= e(date_for_input($svc['hosting_expiry'] ?? null) ?: ($svc['hosting_expiry'] ?? '')) ?>"></div>
    </div>
    <div class="grid2">
      <div><label>Renewal Fee (Tk)</label><input type="number" step="0.01" name="amount" value="<?= $v('amount') ?>"></div>
      <div><label>Note</label><input name="notes" value="<?= $v('notes') ?>"></div>
    </div>
    <button class="btn green">Save</button>
    <a class="btn grey" href="index.php?page=services">Cancel</a>
  </form>
</div>
<script>
document.getElementById('rdapBtn').addEventListener('click', async (ev) => {
  ev.preventDefault();
  const name = document.getElementById('nameFld').value.trim();
  const msg = document.getElementById('rdapMsg');
  if (!name) { msg.textContent = 'Type the domain name first.'; return; }
  msg.textContent = 'Looking up…';
  try {
    const r = await fetch('index.php?page=rdap&domain=' + encodeURIComponent(name));
    const j = await r.json();
    if (j.expiry) { const p = j.expiry.split('-'); document.getElementById('expFld').value = p[2] + '-' + p[1] + '-' + p[0]; msg.textContent = 'Date found ✓'; }
    else msg.textContent = 'Not found — type it manually.';
  } catch (e) { msg.textContent = 'Error — type it manually.'; }
});
</script>
