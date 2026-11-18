<?php
declare(strict_types=1);
namespace App\Core;
final class Secret
{
 private static function key():string{$c=require ROOT.'/config.php';$d=$c['db'];return hash('sha256',(string)$d['host'].'|'.(string)$d['name'].'|'.(string)$d['user'].'|'.(string)$d['pass'],true);}
 public static function encrypt(string $plain):string{$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new \RuntimeException('API anahtarı şifrelenemedi.');return base64_encode($iv.$tag.$cipher);}
 public static function decrypt(string $encoded):string{$raw=base64_decode($encoded,true);if($raw===false||strlen($raw)<29)return ''; $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));if($plain===false)throw new \RuntimeException('Sağlayıcı anahtarı çözülemedi; veritabanı bağlantı ayarları değişmiş olabilir.');return $plain;}
}
