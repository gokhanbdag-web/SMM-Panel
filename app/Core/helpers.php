<?php
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function money($v):string{return number_format((float)$v,2,',','.').' TL';}
function go(string $r='dashboard'):never{header('Location: index.php?r='.rawurlencode($r));exit;}
