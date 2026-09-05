<?php
declare(strict_types=1);

// Only scope names and application-owned error classifications may cross this boundary.
function linkedInSafeScopeNames(mixed $names): array {
    if(!is_array($names))return [];
    return array_values(array_unique(array_filter($names,fn($name)=>is_string($name)&&preg_match('/^[A-Za-z][A-Za-z0-9_:.\/-]{0,99}$/D',$name)===1)));
}
function linkedInOAuthCategory(string $code): string {
    if($code==='OAUTH_STATE')return 'state';
    if($code==='OAUTH_DENIED')return 'access_denied';
    if($code==='LINKEDIN_SCOPE_DENIED')return 'scope';
    if($code==='LINKEDIN_USERINFO')return 'userinfo';
    if(str_starts_with($code,'LINKEDIN_TOKEN'))return 'token_exchange';
    return 'authorization';
}
function linkedInRecordOAuthResult(?array $context,string $result,?string $errorCode=null,array $granted=[]): array {
    $entry=['result'=>$result,'requestedScopes'=>linkedInSafeScopeNames($context['requestedScopes']??[]),
        'scopeSnapshotAvailable'=>isset($context['requestedScopes']),
        'oauthError'=>$errorCode,'oauthErrorCategory'=>$errorCode?linkedInOAuthCategory($errorCode):null,
        'grantedScopes'=>linkedInSafeScopeNames($granted),'existingConnection'=>(bool)($context['existingConnection']??false),'createdAt'=>time()];
    $_SESSION['linkedin_oauth_result']=$entry;
    error_log('SocialPost LinkedIn OAuth '.json_encode(['phase'=>$result==='success'?'complete':($entry['oauthErrorCategory']??'authorization'),'requestedScopes'=>$entry['requestedScopes'],'oauthError'=>$errorCode,'oauthErrorCategory'=>$entry['oauthErrorCategory'],'grantedScopes'=>$entry['grantedScopes']],JSON_UNESCAPED_SLASHES));
    return $entry;
}
function linkedInLastOAuthResult(): ?array {
    $entry=$_SESSION['linkedin_oauth_result']??null;
    return is_array($entry)&&($entry['createdAt']??0)>=time()-3600?$entry:null;
}
