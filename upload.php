<?php
declare(strict_types=1);

const MAX_FILE_SIZE = 30 * 1024 * 1024;
const EXPIRE_SECONDS = 3600;
const DATA_FILE = __DIR__ . '/current.json';
const UPLOAD_DIR = __DIR__ . '/uploads';

$allowedExtensions = ['epub'];

function fail(string $message): never
{
    http_response_code(400);
    echo '<!doctype html><meta charset="utf-8"><title>Erro</title><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p><a href="/">Voltar</a></p>';
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
    if (!$h) fail('Erro ao guardar lista.');
    flock($h, LOCK_EX);
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($list, JSON_UNESCAPED_SLASHES));
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Pedido invalido.');
$code = strtoupper(trim((string)($_POST['code'] ?? '')));
if (preg_match('/^[A-Z0-9]{4}$/', $code) !== 1) fail('Codigo invalido.');

if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) fail('Erro na pasta uploads');

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

if (!isset($_FILES['file']) || !is_array($_FILES['file']) || !isset($_FILES['file']['name']) || !is_array($_FILES['file']['name'])) {
    fail('Escolhe ficheiros.');
}
$added = 0;
for ($i = 0, $n = count($_FILES['file']['name']); $i < $n; $i++) {
    if ((int)($_FILES['file']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
    $name = (string)($_FILES['file']['name'][$i] ?? 'file.epub');
    $tmp = (string)($_FILES['file']['tmp_name'][$i] ?? '');
    $size = (int)($_FILES['file']['size'][$i] ?? 0);
    $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
    if (!($size > 0 && $size <= MAX_FILE_SIZE && in_array($ext, $allowedExtensions, true) && is_uploaded_file($tmp))) continue;
    $stored = 'book_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $stored;
    if (!move_uploaded_file($tmp, $dest)) continue;
    $files[] = ['path' => $stored, 'name' => $name, 'time' => time()];
    $added++;
}
if ($added === 0) fail('Nenhum EPUB valido.');
if (!$files) fail('Nenhum ficheiro valido.');
$sessions[$code]['files'] = $files;
$sessions[$code]['last'] = time();
$data['sessions'] = $sessions;
saveList($data);
header('Location: /send?c=' . rawurlencode($code) . '&ok=1');
exit;
