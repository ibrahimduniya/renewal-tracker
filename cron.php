<?php
// Run once a day: curl "https://your-site/cron.php?key=XXXX"
// Or from cPanel Cron Jobs: php /home/USER/public_html/PATH/cron.php KEY
if (!file_exists(__DIR__ . '/config.local.php')) exit("Finish installing first: open install.php in a browser.\n");
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';

$key = $_GET['key'] ?? ($argv[1] ?? '');
if (!hash_equals(setting('cron_key'), (string)$key)) {
    http_response_code(403);
    exit('Wrong key');
}

$to = setting('admin_email');
$thresholds = array_map('intval', array_filter(explode(',', setting('remind_days', '7,3,1,0')), fn($x) => $x !== ''));
$rows = db()->query("SELECT * FROM services")->fetchAll();
$sent = 0;

// For every record, check both the domain date and the hosting date separately
$items = []; // each: [row, kind, date]
foreach ($rows as $r) {
    if (!empty($r['domain_expiry']))  $items[] = [$r, 'domain',  $r['domain_expiry']];
    if (!empty($r['hosting_expiry'])) $items[] = [$r, 'hosting', $r['hosting_expiry']];
}

foreach ($thresholds as $t) {
    $due = [];
    foreach ($items as [$r, $kind, $date]) {
        if (days_left($date) === $t) {
            $s = db()->prepare("SELECT COUNT(*) c FROM reminder_log WHERE service_id = ? AND kind = ? AND expiry_date = ? AND days_before = ?");
            $s->execute([$r['id'], $kind, $date, $t]);
            if ((int)$s->fetch()['c'] === 0) $due[] = [$r, $kind, $date];
        }
    }
    if (!$due) continue;
    if ($to === '') { echo "Warning: no admin_email in Settings — mail not sent.\n"; continue; }

    $when = $t === 0 ? 'today' : ('in ' . $t . ' day' . ($t === 1 ? '' : 's'));
    $subject = 'Renewal Reminder: ' . count($due) . ' item(s) expire ' . $when;
    $lines = ["The following items expire $when:\n"];
    foreach ($due as [$r, $kind, $date]) {
        $lines[] = '• ' . ($kind === 'hosting' ? 'Hosting' : 'Domain') . ': ' . $r['name'] . ($kind === 'hosting' && $r['hosting'] ? ' (Hosting: ' . $r['hosting'] . ')' : '');
        $lines[] = '  Client: ' . $r['client_name'] . ($r['client_phone'] ? ' (' . $r['client_phone'] . ')' : '');
        $lines[] = '  Expires: ' . fmt_date($date) . ($r['amount'] > 0 ? ' · Renewal fee: Tk ' . number_format((float)$r['amount'], 0) : '');
        $lines[] = '';
    }
    $lines[] = 'After renewing, press the matching "Renew ✓" button in the system to extend the date by 1 year.';
    if (send_mail($to, $subject, implode("\n", $lines))) {
        $ins = db()->prepare("INSERT INTO reminder_log (service_id, kind, expiry_date, days_before) VALUES (?, ?, ?, ?)");
        foreach ($due as [$r, $kind, $date]) { $ins->execute([$r['id'], $kind, $date, $t]); $sent++; }
        echo "Sent ({$t} days before): " . count($due) . " item(s)\n";
    } else {
        echo "Failed to send mail ({$t} days before) — mail() is not working.\n";
    }
}
echo "Done. Reminders sent: $sent.\n";
