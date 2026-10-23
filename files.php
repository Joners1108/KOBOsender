<?php
declare(strict_types=1);

const EXPIRE_SECONDS = 3600;
const DATA_FILE = __DIR__ . '/current.json';
const UPLOAD_DIR = __DIR__ . '/uploads';

function notFound(): never
{
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Sem ficheiro</title><p>Sem ficheiro disponivel.</p><p><a href="/">Voltar</a></p>';
    exit;
}

function readList(): array
{
    if (!is_file(DATA_FILE)) return [];
    $h = @fopen(DATA_FILE, 'c+');
    if (!$h) return [];
    flock($h, LOCK_SH);
    $raw = stream_get_contents($h);
    flock($h, LOCK_UN);
    fclose($h);
    $list = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    return is_array($list) ? $list : [];
}

function saveList(array $list): void
{
    $h = @fopen(DATA_FILE, 'c+');
    if (!$h) return;
    flock($h, LOCK_EX);
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($list, JSON_UNESCAPED_SLASHES));
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

$code = strtoupper(trim((string)($_GET['c'] ?? '')));
if (preg_match('/^[A-Z0-9]{4}$/', $code) !== 1) notFound();

$data = readList();
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

if (!isset($sessions[$code]) || !is_array($sessions[$code])) {
    $sessions[$code] = ['created' => time(), 'last' => time(), 'files' => []];
}
$files = isset($sessions[$code]['files']) && is_array($sessions[$code]['files']) ? $sessions[$code]['files'] : [];
$valid = [];
for ($i = 0, $n = count($files); $i < $n; $i++) {
    $path = isset($files[$i]['path']) ? basename((string)$files[$i]['path']) : '';
    $name = isset($files[$i]['name']) ? (string)$files[$i]['name'] : 'book';
    $time = isset($files[$i]['time']) ? (int)$files[$i]['time'] : 0;
    $full = UPLOAD_DIR . '/' . $path;
    if ($path === '' || !is_file($full)) continue;
    if ($time <= 0 || (time() - $time) > EXPIRE_SECONDS) continue;
    $valid[] = ['path' => $path, 'name' => $name, 'time' => $time];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['i'])) {
    $idx = (int)$_POST['i'];
    if (!isset($valid[$idx])) notFound();
    $file = $valid[$idx];
    $full = UPLOAD_DIR . '/' . $file['path'];
    if (!is_file($full)) notFound();
    $safeName = str_replace(["\r", "\n"], '', (string)$file['name']);
    $size = filesize($full);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($safeName) . '"');
    header('Content-Length: ' . $size);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, max-age=0');
    readfile($full);
    @unlink($full);
    $updated = [];
    for ($i = 0, $n = count($files); $i < $n; $i++) {
        $p = isset($files[$i]['path']) ? basename((string)$files[$i]['path']) : '';
        if ($p === $file['path']) continue;
        $updated[] = $files[$i];
    }
    $sessions[$code]['files'] = $updated;
    $sessions[$code]['last'] = time();
    $data['sessions'] = $sessions;
    saveList($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del'])) {
    $idx = (int)$_POST['del'];
    if (!isset($valid[$idx])) notFound();
    $file = $valid[$idx];
    $full = UPLOAD_DIR . '/' . $file['path'];
    if (is_file($full)) @unlink($full);
    $updated = [];
    for ($i = 0, $n = count($files); $i < $n; $i++) {
        $p = isset($files[$i]['path']) ? basename((string)$files[$i]['path']) : '';
        if ($p === $file['path']) continue;
        $updated[] = $files[$i];
    }
    $sessions[$code]['files'] = $updated;
    $sessions[$code]['last'] = time();
    $data['sessions'] = $sessions;
    saveList($data);
    header('Location: /files?c=' . rawurlencode($code));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear'])) {
    for ($i = 0, $n = count($files); $i < $n; $i++) {
        $p = isset($files[$i]['path']) ? basename((string)$files[$i]['path']) : '';
        if ($p !== '' && is_file(UPLOAD_DIR . '/' . $p)) @unlink(UPLOAD_DIR . '/' . $p);
    }
    $sessions[$code]['files'] = [];
    $sessions[$code]['last'] = time();
    $data['sessions'] = $sessions;
    saveList($data);
    header('Location: /files?c=' . rawurlencode($code));
    exit;
}

$sessions[$code]['files'] = $valid;
$sessions[$code]['last'] = time();
$data['sessions'] = $sessions;
saveList($data);
?>
<!doctype html><html lang="pt"><meta charset="utf-8"><meta http-equiv="refresh" content="10"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ficheiros</title><link rel="stylesheet" href="/style.css"><body><main class="w"><h1><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></h1>
<?php for ($i = 0, $n = count($valid); $i < $n; $i++): ?>
<p><?php echo htmlspecialchars((string)$valid[$i]['name'], ENT_QUOTES, 'UTF-8'); ?></p>
<form method="post" action="/files?c=<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><button type="submit" name="i" value="<?php echo $i; ?>">Download</button></form>
<form method="post" action="/files?c=<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><button type="submit" name="del" value="<?php echo $i; ?>">Eliminar</button></form>
<?php endfor; ?>
<?php if (!$valid): ?><p>A aguardar envio...</p><?php endif; ?>
<form method="post" action="/files?c=<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><button type="submit" name="clear" value="1">Eliminar tudo</button></form>
<p>Licenciado por João Pereira</p>
<p>Com amor para Luana.</p>
</main></body></html>
