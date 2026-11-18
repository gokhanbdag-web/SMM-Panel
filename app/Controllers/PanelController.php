<?php
namespace App\Controllers;
use App\Core\{Auth,Database};
final class PanelController{
 private function view(string $title,string $content):void{$user=Auth::user();$flash=$_SESSION['flash']??'';unset($_SESSION['flash']);$token=$_SESSION['_csrf']??=bin2hex(random_bytes(32));require APP.'/Views/layout.php';}
 private function page(string $name,array $data=[]):string{extract($data);ob_start();require APP.'/Views/'.$name.'.php';return ob_get_clean();}
 function dispatch(string $r):void{
  $p=Database::pdo();
  if($r==='logout'){$_SESSION=[];session_destroy();go('login');}
  if($r==='login'||$r==='register'){
   $error='';
   if($_SERVER['REQUEST_METHOD']==='POST'){
    if($r==='login'){if(Auth::login(trim($_POST['email']??''),(string)($_POST['password']??'')))go('dashboard');$error='E-posta veya şifre hatalı.';}
    else{$n=trim($_POST['name']??'');$em=filter_var($_POST['email']??'',FILTER_VALIDATE_EMAIL);$pw=(string)($_POST['password']??'');if(!$n||!$em||strlen($pw)<10)$error='Bilgileri kontrol edin; şifre en az 10 karakter olmalı.';else try{$p->prepare("INSERT INTO users(name,email,password_hash,role,status,balance,created_at) VALUES(?,?,?,'user','active',0,NOW())")->execute([$n,$em,password_hash($pw,PASSWORD_DEFAULT)]);$_SESSION['flash']='Hesabınız oluşturuldu.';go('login');}catch(\Throwable){$error='E-posta kullanımda.';}}
   }
   $this->view($r==='login'?'Giriş':'Kayıt',$this->page('auth',['mode'=>$r,'error'=>$error]));return;
  }
  Auth::requireLogin();$uid=(int)Auth::user()['id'];
  if($r==='dashboard'){$s=$p->prepare('SELECT balance FROM users WHERE id=?');$s->execute([$uid]);$bal=$s->fetchColumn();$s=$p->prepare('SELECT o.*,s.name service FROM orders o JOIN services s ON s.id=o.service_id WHERE o.user_id=? ORDER BY o.id DESC LIMIT 10');$s->execute([$uid]);$body=$this->page('dashboard',['balance'=>$bal,'orders'=>$s->fetchAll()]);$this->view('Kontrol paneli',$body);return;}
  if($r==='services'){
   $error='';if($_SERVER['REQUEST_METHOD']==='POST'){$p->beginTransaction();try{$s=$p->prepare('SELECT * FROM services WHERE id=? AND active=1 FOR UPDATE');$s->execute([(int)$_POST['service_id']]);$v=$s->fetch();$q=(int)$_POST['quantity'];$link=trim($_POST['link']??'');if(!$v||$q<$v['min_qty']||$q>$v['max_qty']||!filter_var($link,FILTER_VALIDATE_URL))throw new \RuntimeException('Sipariş bilgileri geçersiz.');$charge=round($q*$v['price_per_1000']/1000,2);$s=$p->prepare('SELECT balance FROM users WHERE id=? FOR UPDATE');$s->execute([$uid]);if((float)$s->fetchColumn()<$charge)throw new \RuntimeException('Yetersiz bakiye.');$p->prepare('UPDATE users SET balance=balance-? WHERE id=?')->execute([$charge,$uid]);$p->prepare("INSERT INTO orders(user_id,service_id,link,quantity,charge,status,created_at) VALUES(?,?,?,?,?,'pending',NOW())")->execute([$uid,$v['id'],$link,$q,$charge]);$p->prepare("INSERT INTO transactions(user_id,amount,type,description,created_at) VALUES(?,?,'debit','Sipariş oluşturuldu',NOW())")->execute([$uid,-$charge]);$p->commit();$_SESSION['flash']='Sipariş oluşturuldu.';go('orders');}catch(\Throwable $e){if($p->inTransaction())$p->rollBack();$error=$e->getMessage();}}
   $rows=$p->query('SELECT * FROM services WHERE active=1 ORDER BY category,name')->fetchAll();$this->view('Hizmetler',$this->page('services',['services'=>$rows,'error'=>$error]));return;
  }
  if($r==='orders'){$s=$p->prepare('SELECT o.*,s.name service FROM orders o JOIN services s ON s.id=o.service_id WHERE o.user_id=? ORDER BY o.id DESC');$s->execute([$uid]);$this->view('Siparişlerim',$this->page('orders',['orders'=>$s->fetchAll()]));return;}
  if($r==='topup'){if($_SERVER['REQUEST_METHOD']==='POST'){$a=(float)($_POST['amount']??0);if($a>=10&&$a<=100000){$p->prepare("INSERT INTO topups(user_id,amount,method,status,note,created_at) VALUES(?,?,'bank','pending',?,NOW())")->execute([$uid,$a,trim($_POST['note']??'')]);$_SESSION['flash']='Banka transfer bildiriminiz kaydedildi.';go('topup');}}$s=$p->prepare('SELECT * FROM topups WHERE user_id=? ORDER BY id DESC');$s->execute([$uid]);$this->view('Bakiye yükle',$this->page('topup',['rows'=>$s->fetchAll()]));return;}
  if($r==='admin'){Auth::admin();if($_SERVER['REQUEST_METHOD']==='POST'){$id=(int)$_POST['id'];$p->beginTransaction();$s=$p->prepare("SELECT * FROM topups WHERE id=? AND status='pending' FOR UPDATE");$s->execute([$id]);$t=$s->fetch();if($t){$ok=($_POST['action']??'')==='approve';$p->prepare('UPDATE topups SET status=? WHERE id=?')->execute([$ok?'approved':'rejected',$id]);if($ok){$p->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$t['amount'],$t['user_id']]);$p->prepare("INSERT INTO transactions(user_id,amount,type,description,created_at) VALUES(?,?,'credit','Banka yüklemesi',NOW())")->execute([$t['user_id'],$t['amount']]);}}$p->commit();go('admin');}$top=$p->query("SELECT t.*,u.email FROM topups t JOIN users u ON u.id=t.user_id WHERE t.status='pending'")->fetchAll();$orders=$p->query("SELECT o.*,u.email,s.name service FROM orders o JOIN users u ON u.id=o.user_id JOIN services s ON s.id=o.service_id ORDER BY o.id DESC LIMIT 40")->fetchAll();$this->view('Yönetim',$this->page('admin',['topups'=>$top,'orders'=>$orders]));return;}
  http_response_code(404);$this->view('Bulunamadı','<section class="card">Sayfa bulunamadı.</section>');
 }
}
