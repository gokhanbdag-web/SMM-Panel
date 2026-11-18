<?php
namespace App\Core;
use App\Controllers\PanelController;
final class Application{
 function run():void{
  if($_SERVER['REQUEST_METHOD']==='POST'){$t=$_POST['_csrf']??'';if(!hash_equals($_SESSION['_csrf']??'',(string)$t)){http_response_code(419);exit('CSRF doğrulaması başarısız');}}
  $r=$_GET['r']??(Auth::user()?'dashboard':'login');(new PanelController())->dispatch((string)$r);
 }
}
