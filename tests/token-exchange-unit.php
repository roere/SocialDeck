<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/bootstrap.php';
function tokenResult(int $status,string|false $body,?string $warning=null):array{return['body'=>$body,'headers'=>["HTTP/1.1 $status Test"],'warning'=>$warning,'durationMs'=>7];}
function expectTokenError(string $name,string $expected,array $result):void{$transport=fn()=> $result;try{linkedInTokenExchange(['clientId'=>'unit-client','clientSecret'=>'unit-secret-never-log','redirectUri'=>'https://social.roederstein.de/api/oauth/linkedin/callback'],'unit-code-never-log',$transport);throw new RuntimeException("$name wurde akzeptiert");}catch(OAuthException $error){if($error->errorCode!==$expected)throw new RuntimeException("$name: {$error->errorCode}");echo "PASS $name\n";}}
$log='/tmp/socialdeck-token-exchange-unit.log';@unlink($log);ini_set('log_errors','1');ini_set('error_log',$log);$config=['clientId'=>'unit-client','clientSecret'=>'unit-secret-never-log','redirectUri'=>'https://social.roederstein.de/api/oauth/linkedin/callback'];
$token=linkedInTokenExchange($config,'unit-code-never-log',function(string $endpoint,string $content,int $timeout){parse_str($content,$form);$expected=['grant_type','code','client_id','client_secret','redirect_uri'];if(array_keys($form)!==$expected)throw new RuntimeException('Falsche Token-Parameter');if($form['redirect_uri']!=='https://social.roederstein.de/api/oauth/linkedin/callback')throw new RuntimeException('Redirect URI nicht bytegleich');if($endpoint!==linkedInEndpoint('token')||$timeout!==10)throw new RuntimeException('Token-Transport falsch konfiguriert');return tokenResult(200,'{"access_token":"unit-access-never-log","expires_in":3600}');});if(($token['expires_in']??null)!==3600)throw new RuntimeException('Gültiges Token nicht übernommen');echo "PASS Token HTTP 200, Requestformat und Redirect URI\n";
expectTokenError('Token invalid_grant','LINKEDIN_TOKEN_CODE',tokenResult(400,'{"error":"invalid_grant","error_description":"code expired"}'));
expectTokenError('Token invalid_client','LINKEDIN_TOKEN_CLIENT',tokenResult(400,'{"error":"invalid_client","error_description":"client rejected"}'));
expectTokenError('Token Redirect-Mismatch','LINKEDIN_TOKEN_REDIRECT',tokenResult(400,'{"error":"invalid_grant","error_description":"redirect_uri does not match"}'));
expectTokenError('Token HTTP 401','LINKEDIN_TOKEN_CLIENT',tokenResult(401,'{"error":"unauthorized"}'));
expectTokenError('Token HTTP 429','LINKEDIN_TOKEN_RATE_LIMIT',tokenResult(429,'{"error":"rate_limited"}'));
expectTokenError('Token HTTP 500','LINKEDIN_TOKEN_UPSTREAM',tokenResult(500,'{"error":"server_error"}'));
expectTokenError('Token ungültiges JSON','LINKEDIN_TOKEN_RESPONSE',tokenResult(200,'nicht-json'));
expectTokenError('Token leere Antwort','LINKEDIN_TOKEN_RESPONSE',tokenResult(200,''));
expectTokenError('Token Timeout','LINKEDIN_TOKEN_TIMEOUT',tokenResult(0,false,'Connection timed out'));
expectTokenError('Token TLS-Fehler','LINKEDIN_TOKEN_TLS',tokenResult(0,false,'SSL certificate verify failed'));
expectTokenError('Token DNS-Fehler','LINKEDIN_TOKEN_DNS',tokenResult(0,false,'php_network_getaddresses: getaddrinfo failed'));
expectTokenError('Token Connection refused','LINKEDIN_TOKEN_CONNECTION',tokenResult(0,false,'Connection refused'));
$contents=(string)file_get_contents($log);foreach(['unit-secret-never-log','unit-code-never-log','unit-access-never-log'] as $forbidden)if(str_contains($contents,$forbidden))throw new RuntimeException('Secret in Token-Log');foreach(['"phase":"token_exchange"','"httpStatus":400','"linkedinError":"invalid_grant"','"linkedinErrorDescription":"code expired"','"transportError":"tls"','"redirectUri":"https://social.roederstein.de/api/oauth/linkedin/callback"'] as $required)if(!str_contains($contents,$required))throw new RuntimeException("Diagnosefeld fehlt: $required");echo "PASS Token-Logs strukturiert und secretfrei\n";
