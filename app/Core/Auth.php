<?php
namespace App\Core;
final class Auth{
 static function user():?array{return $_SESSION['user']??null;}
 static function require():void{if(!self::user())go('login');}
 static function admin():void{self::require();if((self::user()['role']??'')!=='admin'){http_response_code(403);exit('Yetkisiz erişim');}}
 static function login(string $email,string $pass):bool{$s=Database::pdo()->prepare("SELECT id,name,email,password_hash,role,status FROM users WHERE email=?");$s->execute([$email]);$u=$s->fetch();if(!$u||$u['status']!=='active'||!password_verify($pass,$u['password_hash']))return false;session_regenerate_id(true);$_SESSION['user']=['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role']];return true;}
}
