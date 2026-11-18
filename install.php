<?php
declare(strict_types=1);
session_start();
if (file_exists(__DIR__ . '/config.php')) {
    http_response_code(403);
    exit('Kurulum zaten yapılmış. Güvenlik için install.php dosyasını hosting üzerinden silin.');
}
if (empty($_SESSION['install_csrf'])) $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['install_csrf'], $_POST['csrf'] ?? '')) {
            throw new RuntimeException('Güvenlik doğrulaması başarısız. Sayfayı yenileyin.');
        }
        $host = trim($_POST['db_host'] ?? 'localhost');
        $name = trim($_POST['db_name'] ?? '');
        $dbuser = trim($_POST['db_user'] ?? '');
        $dbpass = $_POST['db_pass'] ?? '';
        $admin = trim($_POST['admin_name'] ?? '');
        $email = filter_var($_POST['admin_email'] ?? '', FILTER_VALIDATE_EMAIL);
        $pass = $_POST['admin_pass'] ?? '';
        if (!$name || !$dbuser || !$admin || !$email || strlen($pass) < 10) {
            throw new RuntimeException('Tüm alanları doldurun. Yönetici şifresi en az 10 karakter olmalı.');
        }
        $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $dbuser, $dbpass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        if ($sql === false) throw new RuntimeException('schema.sql dosyası okunamadı.');
        $pdo->exec($sql);
        $st = $pdo->prepare("INSERT INTO users(name,email,password_hash,role,balance,created_at) VALUES(?,?,?,'admin',0,NOW())");
        $st->execute([$admin, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        $cfg = "<?php\nreturn " . var_export([
            'db_host'=>$host, 'db_name'=>$name, 'db_user'=>$dbuser, 'db_pass'=>$dbpass,
            'bank_name'=>'', 'iban'=>'', 'payment_provider'=>'',
            'payment_merchant_id'=>'', 'payment_merchant_key'=>'', 'payment_merchant_salt'=>''
        ], true) . ";\n";
        if (file_put_contents(__DIR__ . '/config.php', $cfg, LOCK_EX) === false) {
            throw new RuntimeException('config.php yazılamadı. Hosting dosya izinlerini kontrol edin.');
        }
        @chmod(__DIR__ . '/config.php', 0600);
        session_regenerate_id(true);
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SMM Panel Kurulumu</title><link rel="stylesheet" href="style.css"></head>
<body><main><section class="auth card"><h1>SMM Panel kurulumu</h1><p>Veritabanı ve yönetici hesabını tanımlayın.</p>
<?php if ($error) echo '<div class="notice">'.htmlspecialchars($error, ENT_QUOTES, 'UTF-8').'</div>'; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['install_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<h3>Veritabanı</h3><label>Sunucu</label><input name="db_host" value="localhost" required>
<label>Veritabanı adı</label><input name="db_name" required>
<label>Veritabanı kullanıcı adı</label><input name="db_user" required>
<label>Veritabanı şifresi</label><input name="db_pass" type="password">
<h3>Yönetici hesabı</h3><label>Ad soyad</label><input name="admin_name" required>
<label>E-posta</label><input name="admin_email" type="email" required>
<label>Şifre (min. 10 karakter)</label><input name="admin_pass" type="password" minlength="10" required>
<button>Kurulumu tamamla</button></form></section></main></body></html>
