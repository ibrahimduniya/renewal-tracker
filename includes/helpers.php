<?php
function e($x): string { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function num($n): string { return (string)$n; }
function fmt_date(string $d): string { return date('d-m-Y', strtotime($d)); }
// Date typed in the form (dd-mm-yyyy / dd/mm/yyyy / yyyy-mm-dd) -> storage format (Y-m-d)
function parse_date_input(string $s): ?string {
    $s = trim($s);
    if ($s === '') return null;
    foreach (['d-m-Y', 'd/m/Y', 'd.m.Y', 'Y-m-d'] as $f) {
        $dt = DateTime::createFromFormat($f, $s);
        if ($dt && $dt->format($f) === $s) return $dt->format('Y-m-d');
    }
    return null;
}
// Stored date shown back in form fields (dd-mm-yyyy)
function date_for_input(?string $stored): string {
    if (!$stored) return '';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $stored, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return $stored; // If it is raw text the user typed, show it as-is
}
function days_left(string $expiry): int {
    $today = new DateTime('today');
    $exp = new DateTime($expiry);
    return (int)$today->diff($exp)->format('%R%a');
}
function status_of(int $days): array {
    if ($days < 0)   return ['expired', 'Expired', '#b91c1c'];
    if ($days <= 7)  return ['danger',  $days == 0 ? 'Expires today' : 'Only ' . $days . ' days left', '#dc2626'];
    if ($days <= 30) return ['warning', $days . ' days left', '#d97706'];
    return ['ok', $days . ' days left', '#16a34a'];
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('Invalid request (CSRF).');
    }
}
function flash(string $msg): void { $_SESSION['flash'] = $msg; }
function take_flash(): ?string { $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m; }

// Fetch a domain's expiry date via RDAP (free, no key needed)
function rdap_expiry(string $domain): ?string {
    $domain = strtolower(trim($domain));
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = strtok($domain, '/');
    if (!preg_match('/^[a-z0-9\-\.]+\.[a-z]{2,}$/', $domain)) return null;
    $url = 'https://rdap.org/domain/' . urlencode($domain);
    $json = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'RenewalTracker/1.0',
        ]);
        $res = curl_exec($ch);
        if ($res !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400) $json = $res;
        curl_close($ch);
    }
    if ($json === null) {
        $ctx = stream_context_create(['http' => [
            'timeout' => 12, 'follow_location' => 1, 'max_redirects' => 5,
            'header' => "User-Agent: RenewalTracker/1.0\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
        ]]);
        $json = @file_get_contents($url, false, $ctx);
    }
    if ($json === false || $json === null) return null;
    $data = json_decode($json, true);
    if (!is_array($data)) return null;
    foreach (($data['events'] ?? []) as $ev) {
        if (($ev['eventAction'] ?? '') === 'expiration' && !empty($ev['eventDate'])) {
            return date('Y-m-d', strtotime($ev['eventDate']));
        }
    }
    return null;
}

function send_mail(string $to, string $subject, string $body): bool {
    $from = setting('from_email');
    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
    if ($from !== '') $headers .= 'From: ' . $from . "\r\n";
    if (defined('RT_MAIL_DRY_RUN') && RT_MAIL_DRY_RUN) {
        error_log("[MAIL DRY RUN] To: $to | Subject: $subject\n$body");
        return true;
    }
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

// Whichever of a record's two dates expires first: [kind, date, days]
function next_expiry(array $r): ?array {
    $best = null;
    foreach (['domain' => $r['domain_expiry'] ?? null, 'hosting' => $r['hosting_expiry'] ?? null] as $kind => $date) {
        if (!$date) continue;
        $d = days_left($date);
        if ($best === null || $d < $best[2]) $best = [$kind, $date, $d];
    }
    return $best;
}
