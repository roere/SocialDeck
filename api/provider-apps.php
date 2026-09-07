<?php
declare(strict_types=1);

function providerAppRow(string $provider,int $id): ?array {
    $s=db()->prepare('SELECT * FROM provider_apps WHERE id=? AND provider_id=?');$s->execute([$id,$provider]);return $s->fetch()?:null;
}
function soleProviderApp(string $provider): ?array {
    $s=db()->prepare('SELECT * FROM provider_apps WHERE provider_id=? ORDER BY id LIMIT 2');$s->execute([$provider]);$rows=$s->fetchAll();
    if(count($rows)>1)throw new OAuthException('APP_REQUIRED','Bitte eine konkrete Provider-App auswählen.',422);
    return $rows[0]??null;
}
function publicProviderApp(array $a): array {
    return ['id'=>(int)$a['id'],'providerId'=>$a['provider_id'],'name'=>$a['name'],'clientId'=>$a['client_id']??'',
        'hasClientSecret'=>!empty($a['client_secret_encrypted']),'redirectUri'=>$a['redirect_uri']??'','scopes'=>$a['configured_scopes']??'',
        'enabled'=>(bool)$a['enabled'],'purpose'=>$a['purpose'],'oauthResult'=>linkedInLastOAuthResult((int)$a['id'])];
}
function saveProviderApp(string $provider,?int $id,array $data): array {
    if(!providerDefinition($provider))fail('NOT_FOUND','Provider nicht gefunden.',404);
    $old=$id?providerAppRow($provider,$id):null;if($id&&!$old)fail('NOT_FOUND','Provider-App nicht gefunden.',404);
    $name=requireStringField($data,'name',190);if($name==='')fail('INVALID_INPUT','Name ist erforderlich.',422);
    if(!is_bool($data['enabled']??null))fail('INVALID_INPUT','enabled muss ein Boolean sein.',422);
    $enabled=$data['enabled'];$client=requireStringField($data,'clientId',255);$secret=requireStringField($data,'clientSecret',4096,false);$redirect=requireStringField($data,'redirectUri',2048);
    $purpose=$data['purpose']??null;if($purpose!==null&&(!is_string($purpose)||!in_array($purpose,['posting','community','events','advertising','custom',''],true)))fail('INVALID_INPUT','Ungültiger Zweck.',422);
    $value=$data['scopes']??null;if(is_array($value)){if(!array_is_list($value)||array_filter($value,fn($v)=>!is_string($v)))fail('INVALID_INPUT','Ungültige Scopes.',422);$parts=$value;}elseif(is_string($value))$parts=preg_split('/[\s,]+/',$value);else fail('INVALID_INPUT','Ungültige Scopes.',422);
    $scopes=implode("\n",array_values(array_unique(array_filter(array_map('trim',$parts)))));if(strlen($scopes)>4000||array_filter(preg_split('/\s+/',$scopes),fn($v)=>$v!==''&&!preg_match('/^[A-Za-z][A-Za-z0-9_:.\/-]{0,99}$/D',$v)))fail('INVALID_INPUT','Ungültige Scope-Namen.',422);
    $scheme=strtolower((string)parse_url($redirect,PHP_URL_SCHEME));
    if($redirect!==''&&(!filter_var($redirect,FILTER_VALIDATE_URL)||!in_array($scheme,['http','https'],true)||parse_url($redirect,PHP_URL_USER)!==null||parse_url($redirect,PHP_URL_PASS)!==null||parse_url($redirect,PHP_URL_FRAGMENT)!==null))fail('INVALID_INPUT','Redirect URI muss eine gültige absolute HTTP(S)-URL sein.',422);
    if($redirect!==''&&envValue('APP_ENV','local')==='production'&&$scheme!=='https')fail('INVALID_INPUT','In Produktion sind nur HTTPS Redirect URIs erlaubt.',422);
    if($enabled&&($client===''||$redirect===''||($secret===''&&empty($old['client_secret_encrypted']))))fail('INVALID_INPUT','Aktivierte Apps benötigen ID, Secret und Redirect URI.',422);
    if($enabled&&$provider==='linkedin'&&(!in_array('openid',preg_split('/\s+/',$scopes),true)||!in_array('profile',preg_split('/\s+/',$scopes),true)))fail('INVALID_INPUT','LinkedIn benötigt mindestens die Scopes openid und profile für den bestehenden Profilabruf.',422);
    $encrypted=$secret!==''?encryptSecret($secret):($old['client_secret_encrypted']??null);$now=date('Y-m-d H:i:s');$pdo=db();$pdo->beginTransaction();
    try {
        if($id){
            $lock=$pdo->prepare('SELECT * FROM provider_apps WHERE id=? FOR UPDATE');$lock->execute([$id]);$current=$lock->fetch();if(!$current){$pdo->rollBack();fail('NOT_FOUND','Provider-App nicht gefunden.',404);}if($secret==='')$encrypted=$current['client_secret_encrypted'];
            $count=$pdo->prepare('SELECT COUNT(*) FROM social_accounts WHERE provider_app_id=?');$count->execute([$id]);
            if($current['client_id']!==$client&&(int)$count->fetchColumn()>0){$pdo->rollBack();fail('APP_CLIENT_BOUND','Die Client ID einer App mit Konten kann nicht gewechselt werden. Bitte eine neue App anlegen.',409);}
            $pdo->prepare('UPDATE provider_apps SET name=?,client_id=?,client_secret_encrypted=?,redirect_uri=?,configured_scopes=?,enabled=?,purpose=?,updated_at=? WHERE id=? AND provider_id=?')->execute([$name,$client,$encrypted,$redirect,$scopes,(int)$enabled,$purpose?:null,$now,$id,$provider]);
        }else{
            $pdo->prepare('INSERT INTO provider_apps(provider_id,name,client_id,client_secret_encrypted,redirect_uri,configured_scopes,enabled,purpose,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$provider,$name,$client,$encrypted,$redirect,$scopes,(int)$enabled,$purpose?:null,$now,$now]);$id=(int)$pdo->lastInsertId();
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    return publicProviderApp(providerAppRow($provider,$id));
}
function providerAppRoutes(string $path,string $method): void {
    if(!preg_match('#^/api/admin/providers/([a-z0-9-]+)/apps(?:/([1-9][0-9]*)(?:/(connect|disconnect|channels-sync))?)?$#',$path,$m))return;
    requireAdmin();$provider=$m[1];if(!providerDefinition($provider))fail('NOT_FOUND','Provider nicht gefunden.',404);
    $id=isset($m[2])?(int)$m[2]:null;$action=$m[3]??null;$allowed=$action?['POST']:($id?['GET','PUT','DELETE']:['GET','POST']);
    if(!in_array($method,$allowed,true))fail('METHOD_NOT_ALLOWED','HTTP-Methode nicht erlaubt.',405,['Allow'=>implode(', ',$allowed)]);
    $app=$id?providerAppRow($provider,$id):null;if($id&&!$app)fail('NOT_FOUND','Provider-App nicht gefunden.',404);
    if($method==='GET'){if($id)ok(['app'=>publicProviderApp($app)]);$s=db()->prepare('SELECT * FROM provider_apps WHERE provider_id=? ORDER BY id');$s->execute([$provider]);ok(['apps'=>array_map('publicProviderApp',$s->fetchAll())]);}
    requireCsrf();
    if($action){if($provider!=='linkedin')fail('NOT_IMPLEMENTED','OAuth ist für diesen Provider noch nicht verfügbar.',422);
        if($action==='connect')startLinkedInOAuth($id);
        if($action==='disconnect')disconnectLinkedIn($id);
        if($action==='channels-sync'){
            $account=providerCapabilityAccount('linkedin','organizationDiscovery',$id);
            if(!$account)ok(['status'=>'permission_required','message'=>'Keine gültige Verbindung dieser App mit Organization-Rechten.']);
            syncLinkedInOrganizationChannels(true,(int)$account['id']);
        }
    }
    if($method==='DELETE'){
        $pdo=db();$pdo->beginTransaction();try{$s=$pdo->prepare('SELECT id FROM provider_apps WHERE id=? FOR UPDATE');$s->execute([$id]);$s=$pdo->prepare('SELECT COUNT(*) FROM social_accounts WHERE provider_app_id=?');$s->execute([$id]);
            if((int)$s->fetchColumn()>0){$pdo->rollBack();fail('APP_IN_USE','Diese Provider-App kann nicht gelöscht werden, solange verbundene Konten darauf verweisen.',409);}
            $pdo->prepare('DELETE FROM provider_apps WHERE id=? AND provider_id=?')->execute([$id,$provider]);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}ok(['deleted'=>true]);
    }
    ok(['app'=>saveProviderApp($provider,$id,input())],$id?200:201);
}
function linkedInCapabilityScopes(): array {
    return ['posting'=>['w_member_social'],'eventsRead'=>['r_events'],'eventsWrite'=>['rw_events'],'comments'=>['w_member_social_feed'],
        'feed'=>['r_member_social_feed'],'postRead'=>['r_member_social_feed','r_member_social'],'organizationDiscovery'=>['r_organization_admin','rw_organization_admin'],'organizationPublishing'=>['w_organization_social']];
}
function providerAccountUsable(array $a): bool {
    return (bool)($a['app_enabled']??false)&&$a['status']==='connected'&&!empty($a['access_token_encrypted'])&&(!$a['token_expires_at']||strtotime($a['token_expires_at'])>time());
}
function providerCapabilityAccount(string $provider,string $capability,?int $appId=null,?string $externalId=null): ?array {
    $required=$provider==='linkedin'?(linkedInCapabilityScopes()[$capability]??[]):[];if(!$required)return null;
    $sql='SELECT a.*,p.name app_name,p.enabled app_enabled FROM social_accounts a JOIN provider_apps p ON p.id=a.provider_app_id AND p.provider_id=a.provider_id WHERE a.provider_id=?';$params=[$provider];
    if($appId!==null){$sql.=' AND p.id=?';$params[]=$appId;}if($externalId!==null){$sql.=' AND a.external_account_id=?';$params[]=$externalId;}
    $sql.=' ORDER BY p.id,a.id';$s=db()->prepare($sql);$s->execute($params);
    foreach($s->fetchAll() as $a)if(providerAccountUsable($a)&&($capability==='postRead'?count(array_intersect($required,linkedInScopeList($a['scopes'])))===count($required):(bool)array_intersect($required,linkedInScopeList($a['scopes']))))return $a;
    return null;
}
function providerCapabilityMatrix(string $provider): array {
    $result=[];if($provider!=='linkedin')return $result;
    foreach(linkedInCapabilityScopes() as $cap=>$scopes){$a=providerCapabilityAccount($provider,$cap);$result[$cap]=['available'=>$a!==null,'providerAppId'=>$a?(int)$a['provider_app_id']:null,'appName'=>$a['app_name']??null,'accountId'=>$a?(int)$a['id']:null,'requiredScopes'=>$scopes];}return $result;
}
