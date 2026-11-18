<?php
declare(strict_types=1);
namespace App\Core;
final class View { public static function render(string $name,array $data=[]):string {extract($data,EXTR_SKIP);ob_start();require APP.'/Views/'.$name.'.php';return (string)ob_get_clean();} }
