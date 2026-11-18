<?php
declare(strict_types=1);
define('ROOT',dirname(__DIR__));define('APP',ROOT.'/app');
if(!is_file(ROOT.'/config.php')){header('Location: install.php');exit;}
$config=require ROOT.'/config.php';
require APP.'/Core/helpers.php';
spl_autoload_register(function($c){$p='App\\';if(str_starts_with($c,$p)){ $f=APP.'/'.str_replace('\\','/',substr($c,strlen($p))).'.php';if(is_file($f))require $f;}});
if(session_status()!==PHP_SESSION_ACTIVE){session_name('smm_session');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax']);session_start();}
date_default_timezone_set('Europe/Istanbul');
App\Core\Database::connect($config['db']);
