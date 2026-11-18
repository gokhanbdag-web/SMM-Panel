<?php
declare(strict_types=1);
session_start();
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) { header('Location: install.php'); exit; }
$config = require $configFile;
$pdo = new PDO(
    "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'], $config['db_pass'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Güvenlik doğrulaması başarısız.'); } }
function user(): ?array { return $_SESSION['user'] ?? null; }
function go(string $to='index.php'): never { header('Location: '.$to); exit; }
function flash(string $m): void { $_SESSION['flash']=$m; }
function layout(string $title, string $body): void {
    $u=user(); $msg=$_SESSION['flash']??''; unset($_SESSION['flash']);
    echo '<!doctype html><html lang="tr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).'</title><link rel="stylesheet" href="style.css"><body><header><a class="brand" href="index.php">SMM<span>Panel</span></a><nav>';
    if($u){ echo '<a href="index.php">Panel</a><a href="?page=services">Hizmetler</a><a href="?page=orders">Siparişler</a><a href="?page=topup">Bakiye yükle</a>'; if($u['role']==='admin') echo '<a href="?page=admin">Yönetim</a>'; echo '<a href="?action=logout">Çıkış</a>'; }
    echo '</nav></header><main>'.($msg?'<div class="notice">'.e($msg).'</div>':'').$body.'</main><footer>SMM-Panel · Sosyal medya hizmet paneli</footer></body></html>';
}
if (($_GET['action']??'')==='logout') { session_destroy(); go(); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf(); $action=$_POST['action']??'';
    if($action==='login'){
        $st=$pdo->prepare('SELECT * FROM users WHERE email=?'); $st->execute([trim($_POST['email']??'')]); $u=$st->fetch();
        if($u && password_verify($_POST['password']??'', $u['password_hash'])) { session_regenerate_id(true); $_SESSION['user']=['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']]; go(); }
        flash('E-posta veya şifre hatalı.'); go('?page=login');
    }
    if($action==='register'){
        $name=trim($_POST['name']??''); $email=filter_var($_POST['email']??'',FILTER_VALIDATE_EMAIL); $pass=$_POST['password']??'';
        if(!$name||!$email||strlen($pass)<8){flash('Bilgileri kontrol edin. Şifre en az 8 karakter olmalı.');go('?page=register');}
        try{$st=$pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');$st->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);flash('Hesabınız oluşturuldu. Giriş yapabilirsiniz.');}catch(Throwable $e){flash('Bu e-posta adresi zaten kayıtlı olabilir.');} go('?page=login');
    }
    if(!user()){go('?page=login');}
    if($action==='manual_topup'){
        $amount=(float)($_POST['amount']??0); $note=trim($_POST['note']??'');
        if($amount<10||$amount>100000){flash('Yükleme tutarı 10–100.000 TL arasında olmalı.');go('?page=topup');}
        $st=$pdo->prepare("INSERT INTO topups(user_id,amount,method,status,note,created_at) VALUES(?,?,'bank','pending',?,NOW())");$st->execute([user()['id'],$amount,$note]);flash('Havale/EFT bildiriminiz alındı. Yönetici onayından sonra bakiyenize eklenecek.');go('?page=topup');
    }
    if($action==='online_start'){
        $amount=(float)($_POST['amount']??0);
        if($amount<10||$amount>100000){flash('Geçersiz tutar.');go('?page=topup');}
        $st=$pdo->prepare("INSERT INTO topups(user_id,amount,method,status,note,created_at) VALUES(?,?,'online','pending','Online ödeme başlatma kaydı',NOW())")->execute([user()['id'],$amount]);
        flash('Online ödeme altyapısı henüz etkinleştirilmedi. Bu işlem karttan tahsilat yapmaz.');go('?page=topup');
    }
    if($action==='order'){
        $sid=(int)($_POST['service_id']??0);$link=trim($_POST['link']??'');$qty=(int)($_POST['quantity']??0);
        $pdo->beginTransaction();
        try{
            $st=$pdo->prepare('SELECT * FROM services WHERE id=? AND active=1 FOR UPDATE');$st->execute([$sid]);$s=$st->fetch();
            if(!$s||$qty<(int)$s['min_qty']||$qty>(int)$s['max_qty']||!filter_var($link,FILTER_VALIDATE_URL))throw new RuntimeException('Sipariş bilgileri geçersiz.');
            $cost=round($qty*(float)$s['price_per_1000']/1000,2);
            $st=$pdo->prepare('SELECT balance FROM users WHERE id=? FOR UPDATE');$st->execute([user()['id']]);$bal=(float)$st->fetchColumn();
            if($bal<$cost)throw new RuntimeException('Yetersiz bakiye. Önce kredi yükleyin.');
            $pdo->prepare('UPDATE users SET balance=balance-? WHERE id=?')->execute([$cost,user()['id']]);
            $pdo->prepare("INSERT INTO orders(user_id,service_id,link,quantity,charge,status,created_at) VALUES(?,?,?,?,?,'pending',NOW())")->execute([user()['id'],$sid,$link,$qty,$cost]);
            $pdo->commit();flash('Siparişiniz alındı.');
        }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();flash($ex->getMessage());}
        go('?page=services');
    }
    if($action==='approve_topup' && user()['role']==='admin'){
        $id=(int)$_POST['id'];$pdo->beginTransaction();
        $st=$pdo->prepare("SELECT * FROM topups WHERE id=? AND status='pending' AND method='bank' FOR UPDATE");$st->execute([$id]);$t=$st->fetch();
        if($t){$pdo->prepare("UPDATE topups SET status='approved' WHERE id=?")->execute([$id]);$pdo->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$t['amount'],$t['user_id']]);}
        $pdo->commit();flash($t?'Bakiye onaylandı.':'Talep bulunamadı veya daha önce işlendi.');go('?page=admin');
    }
    if($action==='reject_topup' && user()['role']==='admin'){
        $pdo->prepare("UPDATE topups SET status='rejected' WHERE id=? AND status='pending' AND method='bank'")->execute([(int)$_POST['id']]);flash('Talep reddedildi.');go('?page=admin');
    }
}
$page=$_GET['page']??'home';
if(!user() && !in_array($page,['login','register'],true)) $page='login';
if($page==='login'){layout('Giriş', '<section class="auth card"><h1>Hesabına giriş yap</h1><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="login"><label>E-posta</label><input name="email" type="email" required><label>Şifre</label><input name="password" type="password" required><button>Giriş yap</button></form><p>Hesabın yok mu? <a href="?page=register">Kayıt ol</a></p></section>');exit;}
if($page==='register'){layout('Kayıt', '<section class="auth card"><h1>Ücretsiz hesap oluştur</h1><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="register"><label>Ad soyad</label><input name="name" required><label>E-posta</label><input name="email" type="email" required><label>Şifre (min. 8 karakter)</label><input name="password" type="password" minlength="8" required><button>Kayıt ol</button></form><p><a href="?page=login">Girişe dön</a></p></section>');exit;}
if($page==='topup'){
$st=$pdo->prepare('SELECT * FROM topups WHERE user_id=? ORDER BY id DESC LIMIT 15');$st->execute([user()['id']]);$rows=$st->fetchAll();
$body='<h1>Bakiye yükle</h1><div class="grid"><section class="card"><h2>Havale / EFT</h2><p>Transfer açıklamasına kullanıcı e-posta adresinizi yazın.</p><p><b>'.e($config['bank_name']??'Banka bilgisi yönetimden girilecek').'</b><br>'.e($config['iban']??'IBAN henüz tanımlanmadı').'</p><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="manual_topup"><label>Tutar (TL)</label><input name="amount" type="number" min="10" max="100000" step=".01" required><label>İşlem notu / gönderen adı</label><input name="note"><button>Transfer bildirimi gönder</button></form></section><section class="card"><h2>Online ödeme</h2><p>Kartla güvenli ödeme. Sağlayıcı bağlantısı etkinleştirildiğinde kullanılabilir.</p><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="online_start"><label>Tutar (TL)</label><input name="amount" type="number" min="10" max="100000" step=".01" required><button class="secondary">Online ödeme</button></form><small>Online ödeme henüz aktif değildir; bu form gerçek tahsilat yapmaz.</small></section></div><section class="card"><h2>Yükleme geçmişi</h2><table><tr><th>Tarih</th><th>Tutar</th><th>Yöntem</th><th>Durum</th></tr>';
foreach($rows as $r)$body.='<tr><td>'.e($r['created_at']).'</td><td>'.number_format((float)$r['amount'],2,',','.').' TL</td><td>'.e($r['method']).'</td><td>'.e($r['status']).'</td></tr>';
$body.='</table></section>';layout('Bakiye yükle',$body);exit;
}
if($page==='services'){
$services=$pdo->query('SELECT * FROM services WHERE active=1 ORDER BY category,name')->fetchAll();$body='<h1>Hizmetler</h1><div class="grid">';
foreach($services as $s)$body.='<section class="card"><span class="tag">'.e($s['category']).'</span><h2>'.e($s['name']).'</h2><p>'.e($s['description']).'</p><p><b>'.number_format((float)$s['price_per_1000'],2,',','.').' TL / 1.000</b></p><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="action" value="order"><input type="hidden" name="service_id" value="'.$s['id'].'"><label>Bağlantı</label><input name="link" type="url" placeholder="https://" required><label>Miktar ('.$s['min_qty'].'-'.$s['max_qty'].')</label><input name="quantity" type="number" min="'.$s['min_qty'].'" max="'.$s['max_qty'].'" required><button>Sipariş ver</button></form></section>';
$body.='</div>';layout('Hizmetler',$body);exit;
}
if($page==='orders'){
$st=$pdo->prepare('SELECT o.*,s.name service FROM orders o JOIN services s ON s.id=o.service_id WHERE o.user_id=? ORDER BY o.id DESC');$st->execute([user()['id']]);$rows=$st->fetchAll();$body='<h1>Siparişlerim</h1><section class="card"><table><tr><th>#</th><th>Hizmet</th><th>Miktar</th><th>Tutar</th><th>Durum</th></tr>';
foreach($rows as $r)$body.='<tr><td>'.$r['id'].'</td><td>'.e($r['service']).'</td><td>'.$r['quantity'].'</td><td>'.number_format((float)$r['charge'],2,',','.').' TL</td><td>'.e($r['status']).'</td></tr>';$body.='</table></section>';layout('Siparişler',$body);exit;
}
if($page==='admin' && user()['role']==='admin'){
$rows=$pdo->query("SELECT t.*,u.email FROM topups t JOIN users u ON u.id=t.user_id WHERE t.status='pending' AND t.method='bank' ORDER BY t.id")->fetchAll();$body='<h1>Yönetim paneli</h1><section class="card"><h2>Bekleyen bakiye talepleri</h2><table><tr><th>Kullanıcı</th><th>Tutar</th><th>Yöntem</th><th>Not</th><th>İşlem</th></tr>';
foreach($rows as $r)$body.='<tr><td>'.e($r['email']).'</td><td>'.number_format((float)$r['amount'],2,',','.').' TL</td><td>'.e($r['method']).'</td><td>'.e($r['note']).'</td><td><form class="inline" method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="id" value="'.$r['id'].'"><button name="action" value="approve_topup">Onayla</button><button class="danger" name="action" value="reject_topup">Reddet</button></form></td></tr>';$body.='</table></section>';layout('Yönetim',$body);exit;
}
$st=$pdo->prepare('SELECT balance FROM users WHERE id=?');$st->execute([user()['id']]);$balance=(float)$st->fetchColumn();
$body='<h1>Merhaba, '.e(user()['name']).'</h1><div class="stats"><section class="card"><small>Mevcut bakiye</small><strong>'.number_format($balance,2,',','.').' TL</strong><a href="?page=topup">Bakiye yükle →</a></section><section class="card"><small>Hızlı işlem</small><strong>Sipariş oluştur</strong><a href="?page=services">Hizmetleri görüntüle →</a></section></div><section class="card"><h2>Nasıl çalışır?</h2><p>Hizmet seçin, bağlantınızı ve miktarı girin. Sipariş tutarı bakiyenizden düşülür; sipariş yönetici tarafından işleme alınır.</p></section>';
layout('Kontrol paneli',$body);
