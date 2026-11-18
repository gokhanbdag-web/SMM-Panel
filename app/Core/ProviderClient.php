<?php
declare(strict_types=1);
namespace App\Core;
final class ProviderClient
{
 private string $url; private string $key;
 public function __construct(array $provider){$this->url=rtrim((string)$provider['api_url'],'/');$this->key=Secret::decrypt((string)$provider['api_key']);}
 public function request(array $payload):array {
  if(!function_exists('curl_init')) throw new \RuntimeException('cURL PHP eklentisi etkin değil.');
  $ch=curl_init($this->url);
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload+['key'=>$this->key]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
  $body=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
  if($body===false) throw new \RuntimeException('Sağlayıcı bağlantı hatası: '.$err);
  if($code<200||$code>=300) throw new \RuntimeException('Sağlayıcı HTTP yanıtı: '.$code);
  $data=json_decode((string)$body,true);
  if(!is_array($data)) throw new \RuntimeException('Sağlayıcı geçersiz JSON döndürdü.');
  if(isset($data['error']) && $data['error']!=='') throw new \RuntimeException((string)$data['error']);
  return $data;
 }
 public function add(string $service,string $link,int $quantity):array{return $this->request(['action'=>'add','service'=>$service,'link'=>$link,'quantity'=>$quantity]);}
 public function status(string $id):array{return $this->request(['action'=>'status','order'=>$id]);}
 public function balance():array{return $this->request(['action'=>'balance']);}
}
