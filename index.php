<?php
declare(strict_types=1);
const EXPIRE_SECONDS = 3600;
const DATA_FILE = __DIR__ . '/current.json';
const UPLOAD_DIR = __DIR__ . '/uploads';

function readData(): array {
    if (!is_file(DATA_FILE)) return [];
    $h = @fopen(DATA_FILE, 'c+');
    if (!$h) return [];
    flock($h, LOCK_SH);
    $raw = stream_get_contents($h);
    flock($h, LOCK_UN);
    fclose($h);
    $d = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    return is_array($d) ? $d : [];
}
function saveData(array $d): void {
    $h = @fopen(DATA_FILE, 'c+');
    if (!$h) return;
    flock($h, LOCK_EX);
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($d, JSON_UNESCAPED_SLASHES));
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
}
function code4(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $o = '';
    for ($i = 0; $i < 4; $i++) $o .= $chars[random_int(0, strlen($chars) - 1)];
    return $o;
}

$data = readData();
if (!isset($data['sessions']) || !is_array($data['sessions'])) $data['sessions'] = [];
$sessions = $data['sessions'];

foreach ($sessions as $k => $s) {
    $created = isset($s['created']) ? (int)$s['created'] : 0;
    if ($created <= 0 || (time() - $created) > EXPIRE_SECONDS) {
        $old = isset($s['files']) && is_array($s['files']) ? $s['files'] : [];
        for ($j = 0, $m = count($old); $j < $m; $j++) {
            $p = isset($old[$j]['path']) ? basename((string)$old[$j]['path']) : '';
            if ($p !== '' && is_file(UPLOAD_DIR . '/' . $p)) @unlink(UPLOAD_DIR . '/' . $p);
        }
        unset($sessions[$k]);
    }
}

$code = '';
$code = strtoupper(trim((string)($_COOKIE['kobo_code'] ?? '')));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_key'])) {
    $code = '';
}

if (preg_match('/^[A-Z0-9]{4}$/', $code) !== 1 || !isset($sessions[$code])) {
    do { $code = code4(); } while (isset($sessions[$code]));
    $sessions[$code] = ['created' => time(), 'last' => time(), 'files' => []];
}

$sessions[$code]['last'] = time();
$data['sessions'] = $sessions;
saveData($data);
setcookie('kobo_code', $code, time() + EXPIRE_SECONDS, '/');

$files = isset($sessions[$code]['files']) && is_array($sessions[$code]['files']) ? $sessions[$code]['files'] : [];
$hasFiles = false;
for ($i = 0, $n = count($files); $i < $n; $i++) {
    $p = isset($files[$i]['path']) ? basename((string)$files[$i]['path']) : '';
    $t = isset($files[$i]['time']) ? (int)$files[$i]['time'] : 0;
    if ($p !== '' && $t > 0 && (time() - $t) <= EXPIRE_SECONDS && is_file(UPLOAD_DIR . '/' . $p)) {
        $hasFiles = true;
        break;
    }
}
if ($hasFiles) {
    header('Location: /files?c=' . rawurlencode($code));
    exit;
}
?>
<!doctype html><html lang="pt"><meta charset="utf-8"><meta http-equiv="refresh" content="10"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enviar</title><link rel="stylesheet" href="/style.css"><body><main class="w"><h1>Enviar</h1><p>Chave</p><p><b style="font-size:56px;letter-spacing:6px"><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></b></p><form method="post" action="/"><p><button type="submit" name="new_key" value="1">Nova chave</button></p></form><p>No iPhone abre <b>/send</b>.</p><p>Licenciado por João Pereira</p><p>Com amor para Luana.</p></main></body></html>
