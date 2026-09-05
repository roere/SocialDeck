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

$snapshot=['created_at'=>time(),'requestedScopes'=>['openid','profile','w_member_social_feed'],'existingConnection'=>true];
$_SESSION=['linkedin_oauth_states'=>[hash('sha256','snapshot-a')=>$snapshot,hash('sha256','snapshot-b')=>['created_at'=>time(),'requestedScopes'=>['openid','profile']]]];
$context=consumeLinkedInState('snapshot-a');if($context!==$snapshot)throw new RuntimeException('Scope snapshot lost');echo "PASS Scope-Snapshot bleibt pro OAuth-Versuch erhalten\n";
$other=consumeLinkedInState('snapshot-b');if($other['requestedScopes']!==['openid','profile'])throw new RuntimeException('Parallel snapshots mixed');echo "PASS parallele Scope-Snapshots isoliert\n";
if(linkedInAuthorizationError('access_denied','scope permission cancelled')!=='OAUTH_DENIED')throw new RuntimeException('Cancel misdiagnosed');echo "PASS Abbruch ist keine Scope-Diagnose\n";
if(linkedInAuthorizationError('server_error','r_member_social_feed token=private')!=='LINKEDIN_AUTHORIZATION')throw new RuntimeException('Raw description inferred a scope');echo "PASS keine Einzel-Scope-Diagnose aus Freitext\n";
$log=tempnam(sys_get_temp_dir(),'oauth-log-');$previous=ini_get('error_log');ini_set('error_log',$log);
linkedInOAuthDiagnostic('start','SECRET_STATE',$snapshot,'stored');
$result=linkedInRecordOAuthResult($snapshot,'failed','LINKEDIN_SCOPE_DENIED');
$contents=file_get_contents($log);ini_set('error_log',$previous);unlink($log);
if(str_contains($contents,'SECRET_STATE')||str_contains($contents,'sessionHash')||!str_contains($contents,'requestedScopes')||!str_contains($contents,'w_member_social_feed'))throw new RuntimeException('Unsafe diagnostics');echo "PASS OAuth-Logs enthalten Scopes ohne State oder Sessiondaten\n";
if($result['requestedScopes']!==$snapshot['requestedScopes']||$result['oauthErrorCategory']!=='scope'||!$result['existingConnection'])throw new RuntimeException('Invalid safe outcome');echo "PASS sicheres OAuth-Ergebnis für Admin\n";
