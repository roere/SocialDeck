<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/bootstrap.php';
function expectOAuthFailure(string $name,callable $callback):void{try{$callback();throw new RuntimeException("$name wurde akzeptiert");}catch(OAuthException){echo "PASS $name\n";}}
$state=base64Url(random_bytes(32));if($state===''||strlen($state)!==43)throw new RuntimeException('OAuth-State wurde nicht sicher erzeugt');echo "PASS kryptographischer OAuth-State erzeugt\n";$_SESSION=['linkedin_oauth'=>['state_hash'=>hash('sha256',$state),'created_at'=>time()]];consumeLinkedInState($state);echo "PASS gültiger OAuth-State\n";expectOAuthFailure('OAuth-State nur einmal verwendbar',fn()=>consumeLinkedInState($state));
$_SESSION=['linkedin_oauth'=>['state_hash'=>hash('sha256','richtig'),'created_at'=>time()]];expectOAuthFailure('falscher OAuth-State',fn()=>consumeLinkedInState('falsch'));
$_SESSION=['linkedin_oauth'=>['state_hash'=>hash('sha256','alt'),'created_at'=>time()-601]];expectOAuthFailure('abgelaufener OAuth-State',fn()=>consumeLinkedInState('alt'));
