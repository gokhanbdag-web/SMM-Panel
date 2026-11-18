<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require dirname(__DIR__).'/app/bootstrap.php';
$db=\App\Core\Database::pdo();
$lock=fopen(sys_get_temp_dir().'/smm-panel-orders.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){exit("worker already running\n");}
function logProvider(PDO $db,?int $provider,?int $order,string $level,string $message):void{$s=$db->prepare('INSERT INTO provider_logs(provider_id,order_id,level,message,created_at) VALUES(?,?,?,?,NOW())');$s->execute([$provider,$order,$level,mb_substr($message,0,1000)]);}
$sent=0;$checked=0;$refunded=0;
$rows=$db->query("SELECT o.*,s.provider_service_id FROM orders o JOIN services s ON s.id=o.service_id WHERE o.status IN ('queued','processing') ORDER BY o.id ASC LIMIT 50")->fetchAll();
foreach($rows as $o){
 try{
  if($o['status']==='queued'){
   if(empty($o['provider_id'])||empty($o['provider_service_id']))continue;
   $q=$db->prepare("SELECT * FROM providers WHERE id=? AND status='active'");$q->execute([$o['provider_id']]);$provider=$q->fetch();if(!$provider)continue;
   $result=(new \App\Core\ProviderClient($provider))->add((string)$o['provider_service_id'],(string)$o['link'],(int)$o['quantity']);
   if(empty($result['order']))throw new RuntimeException('Sağlayıcı yanıtında sipariş numarası yok.');
   $db->prepare("UPDATE orders SET provider_order_id=?,status='processing',updated_at=NOW(),error_message=NULL WHERE id=? AND status='queued'")->execute([(string)$result['order'],$o['id']]);
   logProvider($db,(int)$provider['id'],(int)$o['id'],'info','Sipariş sağlayıcıya iletildi.');$sent++;continue;
  }
  if(empty($o['provider_order_id'])||empty($o['provider_id']))continue;
  $q=$db->prepare("SELECT * FROM providers WHERE id=? AND status='active'");$q->execute([$o['provider_id']]);$provider=$q->fetch();if(!$provider)continue;
  $result=(new \App\Core\ProviderClient($provider))->status((string)$o['provider_order_id']);
  $status=strtolower((string)($result['status']??'processing'));
  $map=['pending'=>'processing','in progress'=>'processing','processing'=>'processing','completed'=>'completed','partial'=>'partial','canceled'=>'canceled','cancelled'=>'canceled','failed'=>'failed'];
  $next=$map[$status]??'processing';
  $db->beginTransaction();
  $q=$db->prepare('SELECT status,charge,user_id,quantity,refunded_at FROM orders WHERE id=? FOR UPDATE');$q->execute([$o['id']]);$fresh=$q->fetch();
  if(!$fresh){$db->rollBack();continue;}
  $remains=isset($result['remains'])?(int)$result['remains']:null;
  $start=isset($result['start_count'])?(string)$result['start_count']:$o['start_count'];
  $db->prepare('UPDATE orders SET status=?,start_count=?,remains=?,last_check_at=NOW(),updated_at=NOW(),error_message=NULL WHERE id=?')->execute([$next,$start,$remains===null?null:(string)$remains,$o['id']]);
  $refund=0.0;
  if(in_array($next,['canceled','failed'],true))$refund=(float)$fresh['charge'];
  elseif($next==='partial'&&$remains!==null&&$remains>0)$refund=round((float)$fresh['charge']*min($remains,(int)$fresh['quantity'])/max(1,(int)$fresh['quantity']),2);
  if($refund>0&&empty($fresh['refunded_at'])){
   $db->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$refund,$fresh['user_id']]);
   $db->prepare("INSERT INTO transactions(user_id,amount,type,description,created_at) VALUES(?,?,'credit',?,NOW())")->execute([$fresh['user_id'],$refund,'Sipariş iadesi #'.$o['id']]);
   $db->prepare('UPDATE orders SET error_message=?,refunded_at=NOW() WHERE id=?')->execute(['Bakiye iadesi: '.number_format($refund,2,'.','').' TL',$o['id']]);
   $refunded++;
  }
  $db->commit();
  logProvider($db,(int)$provider['id'],(int)$o['id'],'info','Sipariş durumu güncellendi: '.$next);$checked++;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$db->prepare('UPDATE orders SET last_check_at=NOW(),updated_at=NOW(),error_message=? WHERE id=?')->execute([mb_substr($e->getMessage(),0,500),$o['id']]);logProvider($db,$o['provider_id']?(int)$o['provider_id']:null,(int)$o['id'],'error',$e->getMessage());}
}
echo "sent=$sent checked=$checked refunded=$refunded\n";
