<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/bootstrap.php';
function expectOAuthFailure(string $name,callable $callback):void{try{$callback();throw new RuntimeException("$name wurde akzeptiert");}catch(OAuthException){echo "PASS $name\n";}}
$state=base64Url(random_bytes(32));if($state===''||strlen($state)!==43)throw new RuntimeException('OAuth-State wurde nicht sicher erzeugt');echo "PASS kryptographischer OAuth-State erzeugt\n";
$_SESSION=['linkedin_oauth_states'=>[hash('sha256',$state)=>time()]];consumeLinkedInState($state);echo "PASS gültiger OAuth-State\n";expectOAuthFailure('OAuth-State nur einmal verwendbar',fn()=>consumeLinkedInState($state));
$_SESSION=['linkedin_oauth_states'=>[hash('sha256','richtig')=>time()]];expectOAuthFailure('falscher OAuth-State',fn()=>consumeLinkedInState('falsch'));consumeLinkedInState('richtig');echo "PASS falscher State verbraucht gültigen State nicht\n";
$_SESSION=['linkedin_oauth_states'=>[hash('sha256','alt')=>time()-601]];expectOAuthFailure('abgelaufener OAuth-State',fn()=>consumeLinkedInState('alt'));
$_SESSION=['linkedin_oauth_states'=>[hash('sha256','parallel-a')=>time(),hash('sha256','parallel-b')=>time()]];consumeLinkedInState('parallel-a');consumeLinkedInState('parallel-b');echo "PASS parallele OAuth-Starts bleiben gültig\n";
$_SESSION=['linkedin_oauth'=>['state_hash'=>hash('sha256','legacy'),'created_at'=>time()]];consumeLinkedInState('legacy');echo "PASS laufender Legacy-OAuth-State bleibt kompatibel\n";
$_SESSION=['linkedin_oauth_states'=>[]];for($i=0;$i<8;$i++)$_SESSION['linkedin_oauth_states'][hash('sha256',(string)$i)]=time()+$i;$states=activeLinkedInOAuthStates(time());$states[hash('sha256','neu')]=time();if(count($states)!==5)throw new RuntimeException('State-Limit nicht eingehalten');echo "PASS OAuth-State-Limit\n";
if(linkedInAuthorizationError('access_denied','The user denied access')!=='OAUTH_DENIED')throw new RuntimeException('Benutzerablehnung falsch klassifiziert');echo "PASS LinkedIn-Benutzerablehnung klassifiziert\n";
foreach(['unauthorized_scope_error','invalid_scope'] as $error)if(linkedInAuthorizationError($error,'Requested permission is unavailable')!=='LINKEDIN_SCOPE_DENIED')throw new RuntimeException('Scope-Ablehnung falsch klassifiziert');echo "PASS LinkedIn-Scope-Ablehnung klassifiziert\n";
if(linkedInAuthorizationError('server_error','Temporary failure')!=='LINKEDIN_AUTHORIZATION')throw new RuntimeException('OAuth-Providerfehler falsch klassifiziert');echo "PASS LinkedIn-Providerfehler klassifiziert\n";
