<?php
declare(strict_types=1);
$cfgFile=__DIR__.'/deploy-config.php';
if(!is_file($cfgFile)){http_response_code(503);exit('Webhook yapılandırılmamış.');}
$cfg=require $cfgFile;$secret=(string)($cfg['webhook_secret']??'');
$raw=file_get_contents('php://input')?:'';$sig=$_SERVER['HTTP_X_HUB_SIGNATURE_256']??'';
if($secret===''||!hash_equals('sha256='.hash_hmac('sha256',$raw,$secret),$sig)){http_response_code(401);exit('Geçersiz imza.');}
if(($_SERVER['HTTP_X_GITHUB_EVENT']??'')!=='push'){http_response_code(202);exit('Olay yok sayıldı.');}
$p=json_decode($raw,true);
if(!is_array($p)||($p['repository']['full_name']??'')!=='gokhanbdag-web/SMM-Panel'||($p['ref']??'')!=='refs/heads/main'){http_response_code(202);exit('Depo veya dal eşleşmedi.');}
if(!extension_loaded('curl')||!class_exists('ZipArchive')){http_response_code(500);exit('PHP cURL ve ZipArchive gerekli.');}
$headers=['User-Agent: SMM-Panel-Deployer','Accept: application/vnd.github+json'];$token=(string)($cfg['github_token']??'');if($token!=='')$headers[]='Authorization: Bearer '.$token;
$ch=curl_init('https://api.github.com/repos/gokhanbdag-web/SMM-Panel/zipball/main');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>120]);$zipData=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
if($zipData===false||$status!==200){http_response_code(502);exit('GitHub indirme hatası: '.$status.' '.$err);}
$tmp=sys_get_temp_dir().'/smm-'.bin2hex(random_bytes(8));if(!mkdir($tmp,0700,true)){http_response_code(500);exit('Geçici dizin oluşturulamadı.');}
$zipPath=$tmp.'/repo.zip';file_put_contents($zipPath,$zipData);$zip=new ZipArchive();if($zip->open($zipPath)!==true){http_response_code(500);exit('Arşiv açılamadı.');}
$srcDir=$tmp.'/src';mkdir($srcDir,0700);$rootPrefix=$zip->getNameIndex(0);$rootPrefix=explode('/',$rootPrefix)[0].'/';
for($i=0;$i<$zip->numFiles;$i++){ $name=$zip->getNameIndex($i);if($name===false||!str_starts_with($name,$rootPrefix))continue;$rel=substr($name,strlen($rootPrefix));if($rel===''||str_contains($rel,'../')||str_starts_with($rel,'/'))continue;$target=$srcDir.'/'.$rel;if(str_ends_with($name,'/')){if(!is_dir($target))mkdir($target,0755,true);continue;}if(!is_dir(dirname($target)))mkdir(dirname($target),0755,true);$in=$zip->getStream($name);if(!$in)continue;$out=fopen($target,'wb');stream_copy_to_stream($in,$out);fclose($in);fclose($out);}
$zip->close();
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
foreach($it as $f){$rel=str_replace(DIRECTORY_SEPARATOR,'/',substr($f->getPathname(),strlen($srcDir)+1));$parts=explode('/',$rel);if(in_array($rel,['config.php','deploy-config.php','install.lock'],true)||in_array($parts[0],['storage','uploads'],true))continue;$dst=__DIR__.'/'.$rel;if($f->isDir()){if(!is_dir($dst))mkdir($dst,0755,true);}else{if(!is_dir(dirname($dst)))mkdir(dirname($dst),0755,true);if(!copy($f->getPathname(),$dst)){http_response_code(500);exit('Dosya dağıtılamadı: '.$rel);}}}
$clean=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($clean as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($tmp);
header('Content-Type: text/plain; charset=utf-8');echo 'Deployment completed.';
