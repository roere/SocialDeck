<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/bootstrap.php';
if(envValue('APP_ENV','local')==='production'){fwrite(STDERR,"Seed ist in Produktion deaktiviert.\n");exit(2);}
try{encryptionKey();$username=trim((string)envValue('INITIAL_ADMIN_USERNAME',''));$email=trim((string)envValue('INITIAL_ADMIN_EMAIL',''));$password=(string)envValue('INITIAL_ADMIN_PASSWORD','');
if($username===''||strlen($username)>100||!preg_match('/^[A-Za-z0-9._-]+$/',$username))throw new RuntimeException('INITIAL_ADMIN_USERNAME fehlt oder ist ungültig.');
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)throw new RuntimeException('INITIAL_ADMIN_EMAIL fehlt oder ist ungültig.');if(strlen($password)<12||strlen($password)>1024)throw new RuntimeException('INITIAL_ADMIN_PASSWORD muss 12 bis 1024 Zeichen lang sein.');
$stmt=db()->prepare('SELECT id,username,email FROM users WHERE username=? OR email=?');$stmt->execute([$username,$email]);$rows=$stmt->fetchAll();if(count($rows)>1||(count($rows)===1&&($rows[0]['username']!==$username||$rows[0]['email']!==$email)))throw new RuntimeException('Username/E-Mail kollidiert mit einem bestehenden Benutzer.');if(count($rows)===1){echo "Admin existiert bereits; keine Änderung.\n";exit(0);} $now=date('Y-m-d H:i:s');db()->prepare('INSERT INTO users(username,email,password_hash,role,is_active,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT),'admin',1,$now,$now]);echo "Admin wurde angelegt.\n";
}catch(Throwable $exception){fwrite(STDERR,$exception->getMessage()."\n");exit(1);}
