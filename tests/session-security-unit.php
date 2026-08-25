<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/bootstrap.php';
function assertSessionSecurity(bool $condition,string $name):void{if(!$condition)throw new RuntimeException($name);echo "PASS $name\n";}
putenv('APP_ENV=production');$_ENV['APP_ENV']='production';
$params=cookieParams();assertSessionSecurity($params['secure']===true&&$params['httponly']===true&&$params['samesite']==='Lax'&&$params['path']==='/'&&$params['domain']==='','Produktions-Session-Cookie sicher und callback-kompatibel');
$_SERVER['HTTPS']='on';unset($_SERVER['HTTP_X_FORWARDED_PROTO']);assertSessionSecurity(requestScheme()==='https','direktes HTTPS erkannt');
$_SERVER['HTTPS']='off';$_SERVER['HTTP_X_FORWARDED_PROTO']='https';assertSessionSecurity(requestScheme()==='https','Reverse-Proxy-HTTPS erkannt');
$_SERVER['HTTP_X_FORWARDED_PROTO']='http';assertSessionSecurity(requestScheme()==='http','HTTP-Schema erkannt');
