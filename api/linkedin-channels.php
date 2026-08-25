<?php
declare(strict_types=1);

const LINKEDIN_API_VERSION = '202608';
const LINKEDIN_ORGANIZATION_DISCOVERY_SCOPES = ['r_organization_admin', 'rw_organization_admin'];
const LINKEDIN_ORGANIZATION_PUBLISH_SCOPE = 'w_organization_social';
const LINKEDIN_ORGANIZATION_PUBLISH_ROLES = ['ADMINISTRATOR', 'DIRECT_SPONSORED_CONTENT_POSTER', 'CONTENT_ADMIN', 'CONTENT_ADMINISTRATOR'];

function linkedInScopeList(?string $scopes): array {
    return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $scopes) ?: []))));
}

function hasLinkedInOrganizationDiscoveryScope(array $scopes): bool {
    return count(array_intersect(LINKEDIN_ORGANIZATION_DISCOVERY_SCOPES, $scopes)) > 0;
}

function upsertLinkedInPersonalChannel(PDO $pdo, int $accountId, string $externalId, string $name, array $scopes, string $now): void {
    $metadata = json_encode(['identitySource' => 'openid'], JSON_THROW_ON_ERROR);
    $pdo->prepare("INSERT INTO social_channels(social_account_id,provider_id,external_channel_id,channel_type,display_name,external_urn,role,can_publish,status,metadata_json,last_synced_at,created_at,updated_at) VALUES(?,'linkedin',?,'personal',?,NULL,NULL,?,'active',?,?,?,?) ON DUPLICATE KEY UPDATE channel_type='personal',display_name=VALUES(display_name),can_publish=VALUES(can_publish),status='active',metadata_json=VALUES(metadata_json),last_synced_at=VALUES(last_synced_at),updated_at=VALUES(updated_at)")
        ->execute([$accountId, $externalId, $name, in_array('w_member_social', $scopes, true) ? 1 : 0, $metadata, $now, $now, $now]);
}

function linkedInApiHeaders(string $accessToken): array {
    return ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer '.$accessToken, 'X-Restli-Protocol-Version: 2.0.0', 'Linkedin-Version: '.LINKEDIN_API_VERSION];
}

function linkedInOrganizationId(string $urn): ?string {
    return preg_match('/^urn:li:organization:([0-9]+)$/', $urn, $match) ? $match[1] : null;
}

function syncLinkedInOrganizationChannels(bool $respond=true, ?int $requestedAccountId=null): ?array {
    if($respond){requireAdmin();requireCsrf();}
    $sql="SELECT id,external_account_id,display_name,access_token_encrypted,token_expires_at,scopes,status FROM social_accounts WHERE provider_id='linkedin' AND status='connected'";
    $params=[];if($requestedAccountId!==null){$sql.=' AND id=?';$params[]=$requestedAccountId;}$sql.=' ORDER BY id DESC LIMIT 1';$statement=db()->prepare($sql);$statement->execute($params);$account=$statement->fetch();
    if(!$account){if($respond)fail('LINKEDIN_NOT_CONNECTED','Kein verbundenes LinkedIn-Konto vorhanden.',422);return null;}
    $scopes=linkedInScopeList($account['scopes']);$now=date('Y-m-d H:i:s');
    upsertLinkedInPersonalChannel(db(),(int)$account['id'],(string)$account['external_account_id'],(string)$account['display_name'],$scopes,$now);
    if(!hasLinkedInOrganizationDiscoveryScope($scopes)){
        $result=['status'=>'permission_required','message'=>'Unternehmensseiten können nicht synchronisiert werden, da die erforderlichen LinkedIn-Berechtigungen fehlen.','requiredProduct'=>'Community Management API','missingScopes'=>['r_organization_admin'],'reauthorizationRequired'=>true];
        if($respond)ok($result);return $result;
    }
    if(is_string($account['token_expires_at'])&&strtotime($account['token_expires_at'])<=time())throw new OAuthException('LINKEDIN_TOKEN_EXPIRED','Die LinkedIn-Verbindung ist abgelaufen. Bitte LinkedIn erneut verbinden.',422);
    try{$accessToken=decryptSecret((string)$account['access_token_encrypted']);}catch(CryptoException){throw new OAuthException('LINKEDIN_TOKEN_INVALID','Die LinkedIn-Verbindung ist ungültig. Bitte LinkedIn erneut verbinden.',422);}
    $headers=linkedInApiHeaders($accessToken);$base=rtrim(linkedInEndpoint('api'),'/');
    $acl=linkedInHttpJson('GET',$base.'/rest/organizationAcls?q=roleAssignee&state=APPROVED',$headers);$rolesByOrganization=[];
    foreach(($acl['elements']??[]) as $element){if(!is_array($element)||!is_string($element['organization']??null)||!is_string($element['role']??null))continue;$id=linkedInOrganizationId($element['organization']);if($id===null)continue;$state=is_string($element['state']??null)?$element['state']:'APPROVED';$current=$rolesByOrganization[$id]??null;if($current===null||in_array($element['role'],LINKEDIN_ORGANIZATION_PUBLISH_ROLES,true))$rolesByOrganization[$id]=['role'=>$element['role'],'state'=>$state,'urn'=>$element['organization']];}
    $organizations=[];foreach($rolesByOrganization as $id=>$aclEntry){$organization=linkedInHttpJson('GET',$base.'/rest/organizations/'.rawurlencode((string)$id),$headers);$name=$organization['localizedName']??null;if(!is_string($name)||trim($name)===''||strlen($name)>255)continue;$organizations[$id]=['name'=>trim($name),'role'=>$aclEntry['role'],'state'=>$aclEntry['state'],'urn'=>$aclEntry['urn'],'metadata'=>['vanityName'=>is_string($organization['vanityName']??null)?$organization['vanityName']:null,'linkedinVersion'=>LINKEDIN_API_VERSION]];}
    $pdo=db();$pdo->beginTransaction();try{
        $pdo->prepare("UPDATE social_channels SET status='inactive',can_publish=0,updated_at=? WHERE social_account_id=? AND provider_id='linkedin' AND channel_type='organization'")->execute([$now,$account['id']]);
        $upsert=$pdo->prepare("INSERT INTO social_channels(social_account_id,provider_id,external_channel_id,channel_type,display_name,external_urn,role,can_publish,status,metadata_json,last_synced_at,created_at,updated_at) VALUES(?,'linkedin',?,'organization',?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),external_urn=VALUES(external_urn),role=VALUES(role),can_publish=VALUES(can_publish),status=VALUES(status),metadata_json=VALUES(metadata_json),last_synced_at=VALUES(last_synced_at),updated_at=VALUES(updated_at)");
        foreach($organizations as $id=>$organization){$approved=$organization['state']==='APPROVED';$canPublish=$approved&&in_array($organization['role'],LINKEDIN_ORGANIZATION_PUBLISH_ROLES,true)&&in_array(LINKEDIN_ORGANIZATION_PUBLISH_SCOPE,$scopes,true);$status=$organization['state']==='REVOKED'?'revoked':($approved?'active':'inactive');$upsert->execute([(int)$account['id'],$id,$organization['name'],$organization['urn'],$organization['role'],$canPublish?1:0,$status,json_encode($organization['metadata'],JSON_THROW_ON_ERROR),$now,$now,$now]);}
        $pdo->commit();
    }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    $result=['status'=>'synced','message'=>'LinkedIn-Kanäle aktualisiert.','organizationCount'=>count($organizations),'missingPublishScope'=>!in_array(LINKEDIN_ORGANIZATION_PUBLISH_SCOPE,$scopes,true),'reauthorizationRequired'=>!in_array(LINKEDIN_ORGANIZATION_PUBLISH_SCOPE,$scopes,true)];if($respond)ok($result);return $result;
}
