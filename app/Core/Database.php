<?php
namespace App\Core;
use PDO;
final class Database{
 private static ?PDO $db=null;
 public static function connect(array $c):void{self::$db=new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
 public static function pdo():PDO{return self::$db??throw new \RuntimeException('Database not connected');}
}
