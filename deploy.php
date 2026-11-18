<?php
declare(strict_types=1);

/*
 * GitHub webhook deploy endpoint.
 * Create deploy-config.php on the HOSTING SERVER only (never commit it).
 */
$configPath = __DIR__ . '/deploy-config.php';
if (!is_file($configPath)) { http_response_code(503); exit('Deployment is not configured.'); }
$cfg = require $configPath;
$secret = (string)($cfg['webhook_secret'] ?? '');
if ($secret === '') { http_response_code(503); exit('Webhook secret is missing.'); }

$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $raw ?: '', $secret);
if (!hash_equals($expected, $signature)) { http_response_code(401); exit('Invalid signature.'); }
if ($event !== 'push') { http_response_code(202); exit('Ignored event.'); }

$payload = json_decode($raw ?: '', true);
if (!is_array($payload) ||
    ($payload['repository']['full_name'] ?? '') !== 'gokhanbdag-web/SMM-Panel' ||
    ($payload['ref'] ?? '') !== 'refs/heads/main') {
    http_response_code(202); exit('Ignored repository or branch.');
}

$token = (string)($cfg['github_token'] ?? '');
$headers = ['User-Agent: SMM-Panel-Webhook-Deployer', 'Accept: application/vnd.github+json'];
if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;

if (!extension_loaded('curl') || !class_exists('ZipArchive')) {
    http_response_code(500); exit('Hosting requires PHP cURL and ZipArchive extensions.');
}
$url = 'https://api.github.com/repos/gokhanbdag-web/SMM-Panel/zipball/main';
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_HTTPHEADER=>$headers, CURLOPT_CONNECTTIMEOUT=>15, CURLOPT_TIMEOUT=>120]);
$archive = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);
if ($archive === false || $status !== 200) { http_response_code(502); exit('GitHub download failed: HTTP '.$status.' '.$curlError); }

$tmp = sys_get_temp_dir() . '/smmpanel-' . bin2hex(random_bytes(8));
if (!mkdir($tmp, 0700, true)) { http_response_code(500); exit('Cannot create temporary directory.'); }
$zipPath = $tmp . '/repo.zip';
if (file_put_contents($zipPath, $archive) === false) { http_response_code(500); exit('Cannot save archive.'); }
$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) { http_response_code(500); exit('Invalid GitHub archive.'); }
$extract = $tmp . '/src';
mkdir($extract, 0700);
if (!$zip->extractTo($extract)) { $zip->close(); http_response_code(500); exit('Cannot extract archive.'); }
$zip->close();

$roots = glob($extract . '/*', GLOB_ONLYDIR);
if (!$roots || count($roots) !== 1) { http_response_code(500); exit('Unexpected archive structure.'); }
$source = $roots[0];
$preserve = ['config.php'=>true, 'deploy-config.php'=>true];
$preserveDirs = ['uploads'=>true, 'storage'=>true];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
$baseLen = strlen($source) + 1;
foreach ($iterator as $item) {
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), $baseLen));
    $parts = explode('/', $relative);
    if (isset($preserve[$relative]) || isset($preserveDirs[$parts[0]]) || $parts[0] === '.git') continue;
    $target = __DIR__ . '/' . $relative;
    if ($item->isDir()) {
        if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) { http_response_code(500); exit('Cannot create directory: '.$relative); }
    } elseif ($item->isFile()) {
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) { http_response_code(500); exit('Cannot create parent directory.'); }
        if (!copy($item->getPathname(), $target)) { http_response_code(500); exit('Cannot deploy file: '.$relative); }
    }
}
$cleanup = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($cleanup as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
@rmdir($tmp);
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'Deployment completed.';
