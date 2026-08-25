<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
header('Content-Type: application/json');
if($path==='/token'){$body=[];parse_str(file_get_contents('php://input'),$body);if(($body['code']??'')==='token-failure'){http_response_code(500);echo '{"error":"mock_failure"}';exit;}if(($body['grant_type']??'')!=='authorization_code'||empty($body['client_id'])||empty($body['client_secret'])||empty($body['redirect_uri'])){http_response_code(400);echo '{"error":"invalid_request"}';exit;}$access=($body['code']??'')==='identity-failure'?'mock-identity-failure':'mock-access-token';$scope=($body['code']??'')==='basic-code'?'openid profile w_member_social':'openid profile w_member_social r_organization_admin w_organization_social';echo json_encode(['access_token'=>$access,'expires_in'=>3600,'refresh_token'=>'mock-refresh-token','scope'=>$scope]);exit;}
if($path==='/userinfo'){if(($_SERVER['HTTP_AUTHORIZATION']??'')==='Bearer mock-identity-failure'){http_response_code(500);echo '{"error":"mock_identity_failure"}';exit;}if(($_SERVER['HTTP_AUTHORIZATION']??'')!=='Bearer mock-access-token'){http_response_code(401);echo '{"error":"invalid_token"}';exit;}echo json_encode(['sub'=>'linkedin-test-account','name'=>'LinkedIn Test Account']);exit;}
$mode=is_file('/tmp/linkedin-mock-mode')?trim((string)file_get_contents('/tmp/linkedin-mock-mode')):'multiple';
if($path==='/rest/organizationAcls'){
    if(($_SERVER['HTTP_AUTHORIZATION']??'')!=='Bearer mock-access-token'){http_response_code(401);echo '{"error":"invalid_token"}';exit;}
    if($mode==='forbidden'){http_response_code(403);echo '{"message":"forbidden"}';exit;}
    if($mode==='rate-limit'){http_response_code(429);echo '{"message":"rate limit"}';exit;}
    $elements=$mode==='none'?[]:($mode==='revoked'?[['organization'=>'urn:li:organization:101','role'=>'ADMINISTRATOR','state'=>'REVOKED']]:[['organization'=>'urn:li:organization:101','role'=>'ADMINISTRATOR','state'=>'APPROVED'],['organization'=>'urn:li:organization:102','role'=>'ANALYST','state'=>'APPROVED']]);
    echo json_encode(['elements'=>$elements,'paging'=>['start'=>0,'count'=>count($elements),'total'=>count($elements)]]);exit;
}
if(preg_match('#^/rest/organizations/(101|102)$#',$path,$match)){$names=['101'=>$mode==='updated'?'Firma A aktualisiert':'Firma A','102'=>'Firma B'];echo json_encode(['id'=>(int)$match[1],'localizedName'=>$names[$match[1]],'vanityName'=>'firma-'.$match[1],'$URN'=>'urn:li:organization:'.$match[1]]);exit;}
http_response_code(404);echo '{"error":"not_found"}';
