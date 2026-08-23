<?php
declare(strict_types=1);

function envValue(string $key, ?string $fallback=null): ?string { $value=$_ENV[$key]??getenv($key); return $value===false||$value===null?$fallback:(string)$value; }
require_once __DIR__.'/crypto.php'; require_once __DIR__.'/providers.php';
require_once __DIR__.'/linkedin-oauth.php';
require_once __DIR__.'/text-block-placeholders.php';
require_once __DIR__.'/text-blocks.php';
require_once __DIR__.'/email-settings.php';
function db(): PDO { static $pdo; if($pdo instanceof PDO)return $pdo; $dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',envValue('DB_HOST','db'),envValue('DB_PORT','3306'),envValue('DB_NAME','social_post')); return $pdo=new PDO($dsn,envValue('DB_USER','social_post'),envValue('DB_PASSWORD',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
function jsonResponse(array $body,int $status=200,array $headers=[]): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); foreach($headers as $name=>$value)header($name.': '.$value); echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function ok(array $data=[],int $status=200): never { jsonResponse(['ok'=>true,'data'=>$data],$status); }
function fail(string $code,string $message,int $status,array $headers=[]): never { jsonResponse(['ok'=>false,'error'=>['code'=>$code,'message'=>$message]],$status,$headers); }
function input(): array { $raw=file_get_contents('php://input'); if($raw===false||trim($raw)==='')return []; try{$decoded=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(JsonException){fail('INVALID_JSON','Ungültiges JSON.',400);} if(!is_array($decoded)||array_is_list($decoded))fail('INVALID_JSON','Ein JSON-Objekt wird erwartet.',400); return $decoded; }
function cookieParams(): array { return ['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>envValue('APP_ENV','local')==='production','httponly'=>true,'samesite'=>'Lax']; }
function startAppSession(): void { ini_set('session.use_only_cookies','1'); ini_set('session.use_strict_mode','1'); ini_set('session.use_trans_sid','0'); session_name('SOCIALPOSTSESSID'); session_set_cookie_params(cookieParams()); session_start(); }
function destroyAppSession(): void { $_SESSION=[]; if(session_status()===PHP_SESSION_ACTIVE){$params=cookieParams();unset($params['lifetime']);setcookie(session_name(),'',['expires'=>time()-42000]+$params);session_destroy();} }
function csrfToken(): string { if(!isset($_SESSION['csrf'])||!is_string($_SESSION['csrf'])||strlen($_SESSION['csrf'])<64)$_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function requireCsrf(): void { $stored=$_SESSION['csrf']??null;$request=$_SERVER['HTTP_X_CSRF_TOKEN']??null; if(!is_string($stored)||!is_string($request)||strlen($stored)<64||strlen($request)<64||!hash_equals($stored,$request))fail('CSRF','Ungültiger oder fehlender CSRF-Token.',403); }
function currentUserId(): ?int { $id=$_SESSION['user_id']??null; return is_int($id)&&$id>0?$id:null; }
function loadCurrentUser(): ?array { $id=currentUserId();if($id===null)return null;$stmt=db()->prepare('SELECT id,username,email,role,is_active FROM users WHERE id=?');$stmt->execute([$id]);$user=$stmt->fetch();return $user?:null; }
function publicUser(array $user): array { return ['id'=>(int)$user['id'],'username'=>$user['username'],'email'=>$user['email'],'role'=>$user['role']]; }
function requireAuth(): array { $user=loadCurrentUser();if(!$user||(int)$user['is_active']!==1){destroyAppSession();fail('UNAUTHORIZED','Anmeldung erforderlich.',401);}return $user; }
function requireAdmin(): array { $user=requireAuth();if($user['role']!=='admin'){destroyAppSession();fail('FORBIDDEN','Administratorrechte erforderlich.',403);}return $user; }
function requireStringField(array $data,string $field,int $max,bool $trim=true): string { if(!array_key_exists($field,$data)||!is_string($data[$field]))fail('INVALID_INPUT',"$field muss ein String sein.",422);$value=$trim?trim($data[$field]):$data[$field];if(strlen($value)>$max)fail('INVALID_INPUT',"$field ist zu lang.",422);return $value; }
if(PHP_SAPI!=='cli')startAppSession();
