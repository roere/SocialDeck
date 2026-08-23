<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
header('Content-Type: application/json');
if($path==='/token'){$body=[];parse_str(file_get_contents('php://input'),$body);if(($body['code']??'')==='token-failure'){http_response_code(500);echo '{"error":"mock_failure"}';exit;}if(($body['grant_type']??'')!=='authorization_code'||empty($body['client_id'])||empty($body['client_secret'])||empty($body['redirect_uri'])){http_response_code(400);echo '{"error":"invalid_request"}';exit;}$access=($body['code']??'')==='identity-failure'?'mock-identity-failure':'mock-access-token';echo json_encode(['access_token'=>$access,'expires_in'=>3600,'refresh_token'=>'mock-refresh-token','scope'=>'openid profile']);exit;}
if($path==='/userinfo'){if(($_SERVER['HTTP_AUTHORIZATION']??'')==='Bearer mock-identity-failure'){http_response_code(500);echo '{"error":"mock_identity_failure"}';exit;}if(($_SERVER['HTTP_AUTHORIZATION']??'')!=='Bearer mock-access-token'){http_response_code(401);echo '{"error":"invalid_token"}';exit;}echo json_encode(['sub'=>'linkedin-test-account','name'=>'LinkedIn Test Account']);exit;}
http_response_code(404);echo '{"error":"not_found"}';
