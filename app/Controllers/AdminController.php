<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Database,ProviderClient,Secret};
final class AdminController
{
 public function dispatch(string $method):void {
  $db=Database::pdo();$message='';
  if($method==='POST'){
   $action=(string)($_POST['action']??'');
   try{
    if($action==='topup'){
     $id=(int)($_POST['id']??0);$decision=(string)($_POST['decision']??'');
     if($id<1||!in_array($decision,['approve','reject'],true))throw new \RuntimeException('Geçersiz bakiye işlemi.');
     $db->beginTransaction();$q=$db->prepare("SELECT * FROM topups WHERE id=? AND status='pending' FOR UPDATE");$q->execute([$id]);$t=$q->fetch();
     if($t){$status=$decision==='approve'?'approved':'rejected';$db->prepare('UPDATE topups SET status=? WHERE id=?')->execute([$status,$id]);if($decision==='approve'){$db->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$t['amount'],$t['user_id']]);$db->prepare("INSERT INTO transactions(user_id,amount,type,description,created_at) VALUES(?,?,'credit','Banka yüklemesi',NOW())")->execute([$t['user_id'],$t['amount']]);}}
     $db->commit();$message=$t?'Bakiye bildirimi işlendi.':'Bildirim zaten işlenmiş veya bulunamadı.';
    }elseif($action==='provider_save'){
     $id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$url=trim((string)($_POST['api_url']??''));$key=(string)($_POST['api_key']??'');
     if($name===''||mb_strlen($name)>120||!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https')throw new \RuntimeException('Sağlayıcı adı ve HTTPS API adresi geçerli olmalıdır.');
     if($id){if($key!==''){$db->prepare('UPDATE providers SET name=?,api_url=?,api_key=?,updated_at=NOW() WHERE id=?')->execute([$name,$url,Secret::encrypt($key),$id]);}else{$db->prepare('UPDATE providers SET name=?,api_url=?,updated_at=NOW() WHERE id=?')->execute([$name,$url,$id]);}}
     else{if($key==='')throw new \RuntimeException('Yeni sağlayıcı için API anahtarı zorunludur.');$db->prepare("INSERT INTO providers(name,api_url,api_key,status,created_at) VALUES(?,?,?,'active',NOW())")->execute([$name,$url,Secret::encrypt($key)]);}
     $message='Sağlayıcı kaydedildi.';
    }elseif($action==='provider_toggle'){
     $db->prepare("UPDATE providers SET status=IF(status='active','inactive','active'),updated_at=NOW() WHERE id=?")->execute([(int)($_POST['id']??0)]);$message='Sağlayıcı durumu güncellendi.';
    }elseif($action==='provider_test'){
     $q=$db->prepare('SELECT * FROM providers WHERE id=?');$q->execute([(int)($_POST['id']??0)]);$provider=$q->fetch();if(!$provider)throw new \RuntimeException('Sağlayıcı bulunamadı.');
     $result=(new ProviderClient($provider))->balance();$message='API bağlantısı başarılı. Yanıt: '.json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
     $db->prepare("INSERT INTO provider_logs(provider_id,level,message,created_at) VALUES(?,'info',?,NOW())")->execute([$provider['id'],'API bağlantı testi başarılı.']);
    }elseif($action==='service_save'){
     $id=(int)($_POST['id']??0);$category=trim((string)($_POST['category']??''));$name=trim((string)($_POST['name']??''));$description=trim((string)($_POST['description']??''));$price=(float)($_POST['price_per_1000']??0);$min=(int)($_POST['min_qty']??0);$max=(int)($_POST['max_qty']??0);$provider=(int)($_POST['provider_id']??0);$providerService=trim((string)($_POST['provider_service_id']??''));
     if($category===''||$name===''||$price<=0||$min<1||$max<$min||$max>100000000)throw new \RuntimeException('Hizmet alanlarını kontrol edin.');
     if($provider){$q=$db->prepare("SELECT COUNT(*) FROM providers WHERE id=? AND status='active'");$q->execute([$provider]);if(!(int)$q->fetchColumn()||$providerService==='')throw new \RuntimeException('Sağlayıcı seçildiğinde sağlayıcı servis ID zorunludur.');}
     $args=[$category,$name,$description,$price,$min,$max,$provider?:null,$providerService?:null];
     if($id){$db->prepare('UPDATE services SET category=?,name=?,description=?,price_per_1000=?,min_qty=?,max_qty=?,provider_id=?,provider_service_id=? WHERE id=?')->execute([...$args,$id]);}
     else{$db->prepare('INSERT INTO services(category,name,description,price_per_1000,min_qty,max_qty,provider_id,provider_service_id,active) VALUES(?,?,?,?,?,?,?,?,1)')->execute($args);}
     $message='Hizmet kaydedildi.';
    }elseif($action==='service_toggle'){
     $db->prepare('UPDATE services SET active=IF(active=1,0,1) WHERE id=?')->execute([(int)($_POST['id']??0)]);$message='Hizmet durumu güncellendi.';
    }elseif($action==='user_toggle'){
     $db->prepare("UPDATE users SET status=IF(status='active','suspended','active') WHERE id=? AND role='user'")->execute([(int)($_POST['id']??0)]);$message='Kullanıcı durumu güncellendi.';
    }elseif($action==='order_status'){
     $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');$allowed=['manual_review','queued','processing','completed','partial','canceled','failed'];
     if($id<1||!in_array($status,$allowed,true))throw new \RuntimeException('Geçersiz sipariş durumu.');
     $db->prepare('UPDATE orders SET status=?,updated_at=NOW() WHERE id=?')->execute([$status,$id]);$message='Sipariş durumu güncellendi.';
    }elseif($action==='settings'){
     foreach(['site_name','bank_name','bank_iban','bank_account_name'] as $key){$value=trim((string)($_POST[$key]??''));$db->prepare('INSERT INTO settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()')->execute([$key,$value]);}$message='Genel ve banka ayarları kaydedildi.';
    }else{throw new \RuntimeException('Bilinmeyen yönetim işlemi.');}
   }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();error_log((string)$e);$message=$e instanceof \RuntimeException?$e->getMessage():'İşlem sırasında hata oluştu. Ayrıntı sunucu günlüğüne yazıldı.';}
   $_SESSION['flash']=$message;header('Location: index.php?r=admin');exit;
  }
  $topups=$db->query("SELECT t.*,u.email FROM topups t JOIN users u ON u.id=t.user_id WHERE t.status='pending' ORDER BY t.id")->fetchAll();
  $orders=$db->query('SELECT o.*,u.email,s.name service FROM orders o JOIN users u ON u.id=o.user_id JOIN services s ON s.id=o.service_id ORDER BY o.id DESC LIMIT 100')->fetchAll();
  $services=$db->query('SELECT s.*,p.name provider_name FROM services s LEFT JOIN providers p ON p.id=s.provider_id ORDER BY s.category,s.name')->fetchAll();
  $providers=$db->query('SELECT id,name,api_url,status,created_at FROM providers ORDER BY id DESC')->fetchAll();
  $users=$db->query("SELECT id,name,email,role,status,balance,created_at FROM users ORDER BY id DESC LIMIT 100")->fetchAll();
  $logs=$db->query('SELECT l.*,p.name provider_name FROM provider_logs l LEFT JOIN providers p ON p.id=l.provider_id ORDER BY l.id DESC LIMIT 30')->fetchAll();
  $settings=[];foreach($db->query('SELECT setting_key,setting_value FROM settings')->fetchAll() as $row)$settings[$row['setting_key']]=$row['setting_value'];
  $content=\App\Core\View::render('admin',['topups'=>$topups,'orders'=>$orders,'services'=>$services,'providers'=>$providers,'users'=>$users,'logs'=>$logs,'settings'=>$settings]);
  $title='Yönetim paneli';$user=Auth::user();$flash=$_SESSION['flash']??'';unset($_SESSION['flash']);$token=$_SESSION['_csrf']??='';require APP.'/Views/layout.php';
 }
}
