<?php
declare(strict_types=1);
const DATA_FILE = __DIR__ . '/current.json';
$code = strtoupper(trim((string)($_COOKIE['phone_code'] ?? '')));
if (isset($_GET['c'])) $code = strtoupper(trim((string)$_GET['c']));
$ok = isset($_GET['ok']);
$setMessage = '';

function readData(): array {
    if (!is_file(DATA_FILE)) return [];
    $raw = file_get_contents(DATA_FILE);
    $d = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    return is_array($d) ? $d : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_code'])) {
    $in = strtoupper(trim((string)($_POST['code'] ?? '')));
    if (preg_match('/^[A-Z0-9]{4}$/', $in) === 1) {
        $data = readData();
        $sessions = (isset($data['sessions']) && is_array($data['sessions'])) ? $data['sessions'] : [];
        if (isset($sessions[$in]) && is_array($sessions[$in])) {
            $code = $in;
            setcookie('phone_code', $code, time() + 3600, '/');
            $setMessage = 'Ligado ao Kobo: ' . $code;
        } else {
            $setMessage = 'Chave nao encontrada no Kobo.';
        }
    } else {
        $setMessage = 'Formato de chave invalido.';
    }
}
?>
<!doctype html><html lang="pt"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enviar</title><link rel="stylesheet" href="/style.css"><body><main class="w"><h1>Enviar</h1><form method="post" action="/send"><p><input name="code" maxlength="4" pattern="[A-Za-z0-9]{4}" style="text-transform:uppercase" placeholder="chave" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" required> <button type="submit" name="set_code" value="1">Set</button></p></form><?php if ($setMessage !== ''): ?><p><?php echo htmlspecialchars($setMessage, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?><form id="uploadForm" action="/upload.php" method="post" enctype="multipart/form-data"><input type="hidden" name="code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><p><input name="file[]" type="file" multiple accept=".epub" required></p><p>Só ficheiros EPUB (.epub)</p><p><button id="uploadBtn" type="submit">Enviar</button></p></form><p id="uploadStatus" style="display:none">A enviar... <span id="uploadPct">0%</span></p><progress id="uploadProgress" max="100" value="0" style="display:none;width:100%"></progress><?php if ($ok): ?><p>Enviado.</p><?php endif; ?><p>Licenciado por João Pereira</p><p>Com amor para Luana.</p></main><script>(function(){var f=document.getElementById('uploadForm');var p=document.getElementById('uploadProgress');var s=document.getElementById('uploadStatus');var t=document.getElementById('uploadPct');var b=document.getElementById('uploadBtn');if(!f||!window.XMLHttpRequest||!window.FormData)return;f.addEventListener('submit',function(e){e.preventDefault();var x=new XMLHttpRequest();var d=new FormData(f);p.style.display='block';s.style.display='block';b.disabled=true;x.upload.addEventListener('progress',function(ev){if(ev.lengthComputable){var v=Math.round((ev.loaded/ev.total)*100);p.value=v;t.textContent=v+'%';}});x.onreadystatechange=function(){if(x.readyState===4){b.disabled=false;p.value=100;t.textContent='100%';if(x.status>=200&&x.status<400){window.location=x.responseURL||('/send?c=<?php echo rawurlencode($code); ?>&ok=1');}else{s.textContent='Falha no envio.';}}};x.open('POST',f.action,true);x.send(d);});})();</script></body></html>
