<?php
declare(strict_types=1);

final class OAuthException extends RuntimeException {
    public function __construct(public readonly string $errorCode, string $message, public readonly int $httpStatus=502) { parent::__construct($message); }
}

function linkedInEndpoint(string $type): string {
    $official=['authorize'=>'https://www.linkedin.com/oauth/v2/authorization','token'=>'https://www.linkedin.com/oauth/v2/accessToken','userinfo'=>'https://api.linkedin.com/v2/userinfo','api'=>'https://api.linkedin.com'];
    $override=['authorize'=>'LINKEDIN_AUTHORIZE_ENDPOINT','token'=>'LINKEDIN_TOKEN_ENDPOINT','userinfo'=>'LINKEDIN_USERINFO_ENDPOINT','api'=>'LINKEDIN_API_ENDPOINT'][$type];
    return envValue('APP_ENV','local')==='test' ? (envValue($override,$official[$type])??$official[$type]) : $official[$type];
}
function base64Url(string $bytes): string { return rtrim(strtr(base64_encode($bytes),'+/','-_'),'='); }
function shortSha256(?string $value): ?string { return is_string($value)&&$value!==''?substr(hash('sha256',$value),0,12):null; }
function linkedInOAuthDiagnostic(string $phase,?string $state=null,?array $saved=null,?string $result=null): void {
    $createdAt=is_array($saved)&&isset($saved['created_at'])&&is_int($saved['created_at'])?$saved['created_at']:null;
    $forwardedProto=strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''));$forwardedProto=in_array($forwardedProto,['http','https'],true)?$forwardedProto:($forwardedProto===''?null:'other');
    error_log('SocialPost LinkedIn OAuth '.json_encode(['phase'=>$phase,'sessionHash'=>shortSha256(session_id()),'stateHash'=>shortSha256($state),'storedStateHash'=>is_array($saved)&&is_string($saved['state_hash']??null)?substr($saved['state_hash'],0,12):null,'storedStatePresent'=>$saved!==null,'sessionStateCount'=>is_array($_SESSION['linkedin_oauth_states']??null)?count($_SESSION['linkedin_oauth_states']):0,'stateCreatedAt'=>$createdAt,'now'=>time(),'cookiePresent'=>isset($_COOKIE[session_name()]),'requestScheme'=>requestScheme(),'forwardedProto'=>$forwardedProto,'result'=>$result],JSON_UNESCAPED_SLASHES));
}
function activeLinkedInOAuthStates(int $now): array {
    $states=$_SESSION['linkedin_oauth_states']??[];if(!is_array($states))$states=[];
    foreach($states as $hash=>$createdAt)if(!is_string($hash)||strlen($hash)!==64||!is_int($createdAt)||$createdAt<$now-600||$createdAt>$now+30)unset($states[$hash]);
    return array_slice($states,-4,null,true);
}
function linkedInConfig(): array {
    $stmt=db()->prepare('SELECT enabled,client_id,client_secret_encrypted,redirect_uri,scopes FROM provider_configs WHERE provider_id=?');$stmt->execute(['linkedin']);$row=$stmt->fetch();
    if(!$row||(int)$row['enabled']!==1)throw new OAuthException('LINKEDIN_DISABLED','LinkedIn ist nicht aktiviert.',422);
    if(!is_string($row['client_id'])||trim($row['client_id'])===''||!is_string($row['client_secret_encrypted'])||$row['client_secret_encrypted']===''||!is_string($row['redirect_uri'])||trim($row['redirect_uri'])==='')throw new OAuthException('LINKEDIN_INCOMPLETE','LinkedIn ist unvollständig konfiguriert.',422);
    $scopes=array_values(array_unique(array_filter(array_map('trim',preg_split('/[\s,]+/',(string)$row['scopes'])))));
    if(!in_array('openid',$scopes,true)||!in_array('profile',$scopes,true))throw new OAuthException('LINKEDIN_SCOPES','LinkedIn benötigt mindestens die Scopes openid und profile.',422);
    try{$secret=decryptSecret($row['client_secret_encrypted']);}catch(CryptoException){throw new OAuthException('LINKEDIN_SECRET_INVALID','Das gespeicherte LinkedIn-Secret ist ungültig.',500);}
    return ['clientId'=>trim($row['client_id']),'clientSecret'=>$secret,'redirectUri'=>trim($row['redirect_uri']),'scopes'=>$scopes];
}
function linkedInHttpJson(string $method,string $url,array $headers=[],?array $form=null): array {
    $options=['http'=>['method'=>$method,'timeout'=>10,'ignore_errors'=>true,'header'=>implode("\r\n",$headers)]];
    if($form!==null){$options['http']['content']=http_build_query($form,'','&',PHP_QUERY_RFC3986);$options['http']['header'].="\r\nContent-Type: application/x-www-form-urlencoded";}
    $body=@file_get_contents($url,false,stream_context_create($options));$responseHeaders=$http_response_header??[];$status=0;if(isset($responseHeaders[0])&&preg_match('#\s(\d{3})\s#',$responseHeaders[0],$match))$status=(int)$match[1];
    if($body===false||$status<200||$status>=300){
        if($status===401)throw new OAuthException('LINKEDIN_TOKEN_EXPIRED','Die LinkedIn-Verbindung ist abgelaufen. Bitte LinkedIn erneut verbinden.',422);
        if($status===403)throw new OAuthException('LINKEDIN_ORGANIZATION_PERMISSION','Unternehmensseiten können derzeit nicht ausgelesen werden. Zusätzliche LinkedIn-Produkt- oder Scope-Berechtigung erforderlich.',422);
        if($status===429)throw new OAuthException('LINKEDIN_RATE_LIMIT','LinkedIn erlaubt derzeit keine weitere Aktualisierung. Bitte später erneut versuchen.',429);
        throw new OAuthException('LINKEDIN_UPSTREAM','LinkedIn hat die Anfrage abgelehnt.',502);
    }
    if(strlen($body)>1048576)throw new OAuthException('LINKEDIN_RESPONSE','LinkedIn hat eine ungültige Antwort geliefert.',502);
    try{$data=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(JsonException){throw new OAuthException('LINKEDIN_RESPONSE','LinkedIn hat eine ungültige Antwort geliefert.',502);}
    if(!is_array($data))throw new OAuthException('LINKEDIN_RESPONSE','LinkedIn hat eine ungültige Antwort geliefert.',502);return $data;
}
function safeLinkedInLogValue(mixed $value,int $max=500): ?string { if(!is_string($value)||$value==='')return null;$value=preg_replace('/[\x00-\x1F\x7F]+/',' ',trim($value))??'';return $value===''?null:substr($value,0,$max); }
function linkedInTransportError(?string $message): ?string { $value=strtolower((string)$message);if($value==='')return null;if(str_contains($value,'php_network_getaddresses')||str_contains($value,'getaddrinfo'))return 'dns';if(str_contains($value,'ssl')||str_contains($value,'tls')||str_contains($value,'certificate')||str_contains($value,'crypto'))return 'tls';if(str_contains($value,'timed out')||str_contains($value,'timeout'))return 'timeout';if(str_contains($value,'connection refused'))return 'connection_refused';return 'transport'; }
function defaultLinkedInTokenTransport(string $endpoint,string $content,int $timeout): array { $warning=null;$started=microtime(true);set_error_handler(function(int $severity,string $message)use(&$warning):bool{$warning=$message;return true;});try{$body=file_get_contents($endpoint,false,stream_context_create(['http'=>['method'=>'POST','timeout'=>$timeout,'ignore_errors'=>true,'header'=>"Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded",'content'=>$content]]));$headers=$http_response_header??[];}finally{restore_error_handler();}$duration=(int)round((microtime(true)-$started)*1000);return ['body'=>$body,'headers'=>$headers,'warning'=>$warning,'durationMs'=>$duration]; }
function linkedInTokenExchange(array $config,string $code,?callable $transport=null): array {
    $endpoint=linkedInEndpoint('token');$timeout=envValue('APP_ENV','local')==='test'?(int)envValue('LINKEDIN_TOKEN_TIMEOUT','10'):10;$content=http_build_query(['grant_type'=>'authorization_code','code'=>$code,'client_id'=>$config['clientId'],'client_secret'=>$config['clientSecret'],'redirect_uri'=>$config['redirectUri']],'','&',PHP_QUERY_RFC3986);$result=($transport??'defaultLinkedInTokenTransport')($endpoint,$content,$timeout);$headers=is_array($result['headers']??null)?$result['headers']:[];$status=0;if(isset($headers[0])&&is_string($headers[0])&&preg_match('#\s(\d{3})\s#',$headers[0],$match))$status=(int)$match[1];$body=$result['body']??false;$decoded=null;$isJson=false;if(is_string($body)&&$body!==''){try{$candidate=json_decode($body,true,32,JSON_THROW_ON_ERROR);if(is_array($candidate)){$decoded=$candidate;$isJson=true;}}catch(JsonException){}}$linkedinError=safeLinkedInLogValue($decoded['error']??null,100);$linkedinDescription=safeLinkedInLogValue($decoded['error_description']??null);$rawLinkedinCode=$decoded['errorCode']??$decoded['error_code']??null;$linkedinCode=is_int($rawLinkedinCode)||is_string($rawLinkedinCode)?safeLinkedInLogValue((string)$rawLinkedinCode,100):null;$transportMessage=safeLinkedInLogValue($result['warning']??null);$transportError=linkedInTransportError($transportMessage);
    error_log('SocialPost LinkedIn OAuth '.json_encode(['phase'=>'token_exchange','tokenEndpoint'=>$endpoint,'httpStatus'=>$status?:null,'durationMs'=>(int)($result['durationMs']??0),'responseIsJson'=>$isJson,'linkedinError'=>$linkedinError,'linkedinErrorDescription'=>$linkedinDescription,'linkedinErrorCode'=>$linkedinCode,'redirectUri'=>$config['redirectUri'],'transportError'=>$transportError,'transportErrorMessage'=>$transportMessage],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    if($body===false||$transportError!==null){$map=['dns'=>['LINKEDIN_TOKEN_DNS','LinkedIn konnte wegen eines DNS-Fehlers nicht erreicht werden.'],'tls'=>['LINKEDIN_TOKEN_TLS','Die TLS-Verbindung zu LinkedIn ist fehlgeschlagen.'],'timeout'=>['LINKEDIN_TOKEN_TIMEOUT','Die Verbindung zu LinkedIn hat das Zeitlimit überschritten.'],'connection_refused'=>['LINKEDIN_TOKEN_CONNECTION','Die Verbindung zu LinkedIn wurde abgelehnt.']];[$errorCode,$message]=$map[$transportError]??['LINKEDIN_TOKEN_TRANSPORT','LinkedIn konnte technisch nicht erreicht werden.'];throw new OAuthException($errorCode,$message,502);}
    $normalized=strtolower(($linkedinError??'').' '.($linkedinDescription??''));if($linkedinError==='invalid_client'||$status===401)throw new OAuthException('LINKEDIN_TOKEN_CLIENT','LinkedIn hat die Client-Anmeldedaten abgelehnt.',422);if($linkedinError==='invalid_redirect_uri'||str_contains($normalized,'redirect_uri')||str_contains($normalized,'redirect uri'))throw new OAuthException('LINKEDIN_TOKEN_REDIRECT','Die Redirect URI stimmt nicht mit dem OAuth-Request überein.',422);if($linkedinError==='invalid_grant')throw new OAuthException('LINKEDIN_TOKEN_CODE','Der LinkedIn-Autorisierungscode ist ungültig oder abgelaufen.',422);if($status===429)throw new OAuthException('LINKEDIN_TOKEN_RATE_LIMIT','LinkedIn erlaubt derzeit keinen weiteren Token-Austausch. Bitte später erneut versuchen.',429);if($status<200||$status>=300)throw new OAuthException('LINKEDIN_TOKEN_UPSTREAM','LinkedIn hat den Token-Austausch abgelehnt.',502);if(!$isJson)throw new OAuthException('LINKEDIN_TOKEN_RESPONSE','LinkedIn hat eine ungültige Token-Antwort geliefert.',502);return $decoded;
}
function startLinkedInOAuth(): never {
    requireAdmin();requireCsrf();$config=linkedInConfig();$state=base64Url(random_bytes(32));$now=time();$states=activeLinkedInOAuthStates($now);$states[hash('sha256',$state)]=$now;$_SESSION['linkedin_oauth_states']=array_slice($states,-5,null,true);unset($_SESSION['linkedin_oauth']);linkedInOAuthDiagnostic('start',$state,['state_hash'=>hash('sha256',$state),'created_at'=>$now],'stored');
    $query=http_build_query(['response_type'=>'code','client_id'=>$config['clientId'],'redirect_uri'=>$config['redirectUri'],'state'=>$state,'scope'=>implode(' ',$config['scopes'])],'','&',PHP_QUERY_RFC3986);
    $authorizationUrl=linkedInEndpoint('authorize').'?'.$query;header('Cache-Control: no-store');
    if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json'))ok(['authorizationUrl'=>$authorizationUrl]);
    header('Location: '.$authorizationUrl,true,302);exit;
}
function consumeLinkedInState(?string $provided): void {
    $now=time();$providedHash=is_string($provided)&&$provided!==''?hash('sha256',$provided):null;$states=activeLinkedInOAuthStates($now);$createdAt=null;
    if(is_string($providedHash))foreach($states as $storedHash=>$storedAt)if(hash_equals($storedHash,$providedHash)){$createdAt=$storedAt;break;}
    $legacy=$_SESSION['linkedin_oauth']??null;if($createdAt===null&&is_array($legacy)&&is_string($legacy['state_hash']??null)&&is_int($legacy['created_at']??null)&&is_string($providedHash)&&hash_equals($legacy['state_hash'],$providedHash))$createdAt=$legacy['created_at'];
    $saved=$createdAt===null?null:['state_hash'=>$providedHash,'created_at'=>$createdAt];$valid=is_string($provided)&&$provided!==''&&is_string($providedHash)&&$createdAt!==null&&$now-$createdAt<=600&&$createdAt<=$now+30;
    linkedInOAuthDiagnostic('callback',$provided,$saved,$valid?'valid':'invalid');
    if(!$valid)throw new OAuthException('OAUTH_STATE','OAuth-State ist ungültig oder abgelaufen.',403);
    unset($states[$providedHash],$_SESSION['linkedin_oauth']);$_SESSION['linkedin_oauth_states']=$states;
}
function linkedInAdminRedirect(string $result,?string $errorCode=null): never { header('Cache-Control: no-store');$query=['linkedin_oauth'=>$result];if($errorCode!==null)$query['linkedin_oauth_error']=$errorCode;header('Location: /?'.http_build_query($query,'','&',PHP_QUERY_RFC3986).'#/admin',true,303);exit; }
function linkedInAuthorizationError(?string $error,?string $description): ?string {
    if(!is_string($error)||$error==='')return null;$normalized=strtolower($error.' '.(is_string($description)?$description:''));
    if(str_contains($normalized,'scope')||str_contains($normalized,'permission'))return 'LINKEDIN_SCOPE_DENIED';
    return $error==='access_denied'?'OAUTH_DENIED':'LINKEDIN_AUTHORIZATION';
}
function linkedInCallback(): never {
    consumeLinkedInState(isset($_GET['state'])&&is_string($_GET['state'])?$_GET['state']:null);requireAdmin();
    $authorizationError=linkedInAuthorizationError(is_string($_GET['error']??null)?$_GET['error']:null,is_string($_GET['error_description']??null)?$_GET['error_description']:null);if($authorizationError!==null)linkedInAdminRedirect($authorizationError==='OAUTH_DENIED'?'denied':'failed',$authorizationError);
    $code=$_GET['code']??null;if(!is_string($code)||$code===''||strlen($code)>4096)throw new OAuthException('OAUTH_CODE','Authorization Code fehlt oder ist ungültig.',422);
    $config=linkedInConfig();$token=linkedInTokenExchange($config,$code);
    $access=$token['access_token']??null;if(!is_string($access)||$access===''||strlen($access)>16384)throw new OAuthException('LINKEDIN_TOKEN','LinkedIn hat kein gültiges Access Token geliefert.',502);$expires=$token['expires_in']??null;if($expires!==null&&(!is_int($expires)||$expires<1||$expires>315360000))throw new OAuthException('LINKEDIN_TOKEN','LinkedIn hat eine ungültige Token-Laufzeit geliefert.',502);$refresh=$token['refresh_token']??null;if($refresh!==null&&(!is_string($refresh)||$refresh===''||strlen($refresh)>16384))throw new OAuthException('LINKEDIN_TOKEN','LinkedIn hat ein ungültiges Refresh Token geliefert.',502);
    try{$identity=linkedInHttpJson('GET',linkedInEndpoint('userinfo'),['Accept: application/json','Authorization: Bearer '.$access]);}catch(OAuthException){throw new OAuthException('LINKEDIN_USERINFO','LinkedIn konnte die Profilinformationen nicht laden.',502);}$external=$identity['sub']??null;$name=$identity['name']??null;if(!is_string($external)||$external===''||strlen($external)>255||!is_string($name)||trim($name)===''||strlen($name)>255)throw new OAuthException('LINKEDIN_USERINFO','LinkedIn hat keine gültige Account-Identität geliefert.',502);
    $scopes=is_string($token['scope']??null)?implode("\n",array_values(array_unique(array_filter(array_map('trim',preg_split('/[\s,]+/',$token['scope'])))))):'';if(strlen($scopes)>4000)throw new OAuthException('LINKEDIN_TOKEN','LinkedIn hat ungültige Scopes geliefert.',502);$expiry=is_int($expires)?date('Y-m-d H:i:s',time()+$expires):null;$now=date('Y-m-d H:i:s');$accessEncrypted=encryptSecret($access);$refreshEncrypted=is_string($refresh)?encryptSecret($refresh):null;
    $pdo=db();$pdo->beginTransaction();
    try{
        $pdo->prepare('INSERT INTO social_accounts(provider_id,external_account_id,display_name,account_type,access_token_encrypted,refresh_token_encrypted,token_expires_at,scopes,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),account_type=VALUES(account_type),access_token_encrypted=VALUES(access_token_encrypted),refresh_token_encrypted=VALUES(refresh_token_encrypted),token_expires_at=VALUES(token_expires_at),scopes=VALUES(scopes),status=VALUES(status),updated_at=VALUES(updated_at)')->execute(['linkedin',$external,trim($name),'member',$accessEncrypted,$refreshEncrypted,$expiry,$scopes,'connected',$now,$now]);
        $accountStatement=$pdo->prepare("SELECT id FROM social_accounts WHERE provider_id='linkedin' AND external_account_id=?");$accountStatement->execute([$external]);$accountId=(int)$accountStatement->fetchColumn();
        upsertLinkedInPersonalChannel($pdo,$accountId,$external,trim($name),preg_split('/\s+/',$scopes)?:[],$now);
        $pdo->commit();
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    try{syncLinkedInOrganizationChannels(false,$accountId);}catch(OAuthException){/* Verbindung bleibt erhalten; Status und manueller Sync erklären fehlende Rechte. */}
    linkedInAdminRedirect('connected');
}
function disconnectLinkedIn(): never {
    requireAdmin();requireCsrf();$data=input();$id=$data['accountId']??null;if(!is_int($id)||$id<1)fail('INVALID_INPUT','accountId muss eine positive Ganzzahl sein.',422);$now=date('Y-m-d H:i:s');$pdo=db();$pdo->beginTransaction();try{$stmt=$pdo->prepare("UPDATE social_accounts SET access_token_encrypted=NULL,refresh_token_encrypted=NULL,token_expires_at=NULL,status='disconnected',updated_at=? WHERE id=? AND provider_id='linkedin'");$stmt->execute([$now,$id]);if($stmt->rowCount()!==1){$pdo->rollBack();fail('NOT_FOUND','LinkedIn-Account nicht gefunden.',404);}$pdo->prepare("UPDATE social_channels SET status='inactive',can_publish=0,updated_at=? WHERE social_account_id=?")->execute([$now,$id]);$pdo->commit();}catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}ok(['accountId'=>$id,'status'=>'disconnected']);
}
