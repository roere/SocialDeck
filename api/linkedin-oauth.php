<?php
declare(strict_types=1);

final class OAuthException extends RuntimeException {
    public function __construct(public readonly string $errorCode, string $message, public readonly int $httpStatus=502) { parent::__construct($message); }
}

function linkedInEndpoint(string $type): string {
    $official=['authorize'=>'https://www.linkedin.com/oauth/v2/authorization','token'=>'https://www.linkedin.com/oauth/v2/accessToken','userinfo'=>'https://api.linkedin.com/v2/userinfo'];
    $override=['authorize'=>'LINKEDIN_AUTHORIZE_ENDPOINT','token'=>'LINKEDIN_TOKEN_ENDPOINT','userinfo'=>'LINKEDIN_USERINFO_ENDPOINT'][$type];
    return envValue('APP_ENV','local')==='test' ? (envValue($override,$official[$type])??$official[$type]) : $official[$type];
}
function base64Url(string $bytes): string { return rtrim(strtr(base64_encode($bytes),'+/','-_'),'='); }
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
    if($body===false||$status<200||$status>=300)throw new OAuthException('LINKEDIN_UPSTREAM','LinkedIn hat die Anfrage abgelehnt.',502);
    if(strlen($body)>1048576)throw new OAuthException('LINKEDIN_RESPONSE','LinkedIn hat eine ungültige Antwort geliefert.',502);
    try{$data=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(JsonException){throw new OAuthException('LINKEDIN_RESPONSE','LinkedIn hat eine ungültige Antwort geliefert.',502);}
    if(!is_array($data))throw new OAuthException('LINKEDIN_RESPONSE','LinkedIn hat eine ungültige Antwort geliefert.',502);return $data;
}
function startLinkedInOAuth(): never {
    requireAdmin();requireCsrf();$config=linkedInConfig();$state=base64Url(random_bytes(32));$_SESSION['linkedin_oauth']=['state_hash'=>hash('sha256',$state),'created_at'=>time()];
    $query=http_build_query(['response_type'=>'code','client_id'=>$config['clientId'],'redirect_uri'=>$config['redirectUri'],'state'=>$state,'scope'=>implode(' ',$config['scopes'])],'','&',PHP_QUERY_RFC3986);
    $authorizationUrl=linkedInEndpoint('authorize').'?'.$query;header('Cache-Control: no-store');
    if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json'))ok(['authorizationUrl'=>$authorizationUrl]);
    header('Location: '.$authorizationUrl,true,302);exit;
}
function consumeLinkedInState(?string $provided): void {
    $saved=$_SESSION['linkedin_oauth']??null;unset($_SESSION['linkedin_oauth']);
    if(!is_array($saved)||!is_string($provided)||$provided===''||!isset($saved['state_hash'],$saved['created_at'])||!is_string($saved['state_hash'])||!is_int($saved['created_at'])||time()-$saved['created_at']>600||$saved['created_at']>time()+30||!hash_equals($saved['state_hash'],hash('sha256',$provided)))throw new OAuthException('OAUTH_STATE','OAuth-State ist ungültig oder abgelaufen.',403);
}
function linkedInAdminRedirect(string $result): never { header('Cache-Control: no-store');header('Location: /?linkedin_oauth='.rawurlencode($result).'#/admin',true,303);exit; }
function linkedInCallback(): never {
    requireAdmin();consumeLinkedInState(isset($_GET['state'])&&is_string($_GET['state'])?$_GET['state']:null);
    if(isset($_GET['error']))linkedInAdminRedirect('denied');$code=$_GET['code']??null;if(!is_string($code)||$code===''||strlen($code)>4096)throw new OAuthException('OAUTH_CODE','Authorization Code fehlt oder ist ungültig.',422);
    $config=linkedInConfig();$token=linkedInHttpJson('POST',linkedInEndpoint('token'),['Accept: application/json'],['grant_type'=>'authorization_code','code'=>$code,'client_id'=>$config['clientId'],'client_secret'=>$config['clientSecret'],'redirect_uri'=>$config['redirectUri']]);
    $access=$token['access_token']??null;if(!is_string($access)||$access===''||strlen($access)>16384)throw new OAuthException('LINKEDIN_TOKEN','LinkedIn hat kein gültiges Access Token geliefert.',502);$expires=$token['expires_in']??null;if($expires!==null&&(!is_int($expires)||$expires<1||$expires>315360000))throw new OAuthException('LINKEDIN_TOKEN','LinkedIn hat eine ungültige Token-Laufzeit geliefert.',502);$refresh=$token['refresh_token']??null;if($refresh!==null&&(!is_string($refresh)||$refresh===''||strlen($refresh)>16384))throw new OAuthException('LINKEDIN_TOKEN','LinkedIn hat ein ungültiges Refresh Token geliefert.',502);
    $identity=linkedInHttpJson('GET',linkedInEndpoint('userinfo'),['Accept: application/json','Authorization: Bearer '.$access]);$external=$identity['sub']??null;$name=$identity['name']??null;if(!is_string($external)||$external===''||strlen($external)>255||!is_string($name)||trim($name)===''||strlen($name)>255)throw new OAuthException('LINKEDIN_IDENTITY','LinkedIn hat keine gültige Account-Identität geliefert.',502);
    $scopes=is_string($token['scope']??null)?implode("\n",array_values(array_unique(array_filter(array_map('trim',preg_split('/[\s,]+/',$token['scope'])))))):implode("\n",$config['scopes']);if(strlen($scopes)>4000)throw new OAuthException('LINKEDIN_TOKEN','LinkedIn hat ungültige Scopes geliefert.',502);$expiry=is_int($expires)?date('Y-m-d H:i:s',time()+$expires):null;$now=date('Y-m-d H:i:s');$accessEncrypted=encryptSecret($access);$refreshEncrypted=is_string($refresh)?encryptSecret($refresh):null;
    db()->prepare('INSERT INTO social_accounts(provider_id,external_account_id,display_name,account_type,access_token_encrypted,refresh_token_encrypted,token_expires_at,scopes,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),account_type=VALUES(account_type),access_token_encrypted=VALUES(access_token_encrypted),refresh_token_encrypted=VALUES(refresh_token_encrypted),token_expires_at=VALUES(token_expires_at),scopes=VALUES(scopes),status=VALUES(status),updated_at=VALUES(updated_at)')->execute(['linkedin',$external,trim($name),'member',$accessEncrypted,$refreshEncrypted,$expiry,$scopes,'connected',$now,$now]);
    linkedInAdminRedirect('connected');
}
function disconnectLinkedIn(): never {
    requireAdmin();requireCsrf();$data=input();$id=$data['accountId']??null;if(!is_int($id)||$id<1)fail('INVALID_INPUT','accountId muss eine positive Ganzzahl sein.',422);$stmt=db()->prepare("UPDATE social_accounts SET access_token_encrypted=NULL,refresh_token_encrypted=NULL,token_expires_at=NULL,status='disconnected',updated_at=? WHERE id=? AND provider_id='linkedin'");$stmt->execute([date('Y-m-d H:i:s'),$id]);if($stmt->rowCount()!==1)fail('NOT_FOUND','LinkedIn-Account nicht gefunden.',404);ok(['accountId'=>$id,'status'=>'disconnected']);
}
