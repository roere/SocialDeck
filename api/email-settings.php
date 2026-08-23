<?php
declare(strict_types=1);

function emailSettings(bool $includePassword=false): array {
    $row=db()->query('SELECT enabled,smtp_host,smtp_port,encryption,smtp_username,smtp_password_encrypted,from_email,from_name,reply_to FROM email_settings WHERE id=1')->fetch()?:[];
    $encrypted=$row['smtp_password_encrypted']??null;
    $settings=['enabled'=>(bool)($row['enabled']??false),'host'=>$row['smtp_host']??'','port'=>(int)($row['smtp_port']??587),'encryption'=>$row['encryption']??'tls','user'=>$row['smtp_username']??'','hasPassword'=>is_string($encrypted)&&$encrypted!=='','fromEmail'=>$row['from_email']??'','fromName'=>$row['from_name']??'','replyTo'=>$row['reply_to']??''];
    if($includePassword)$settings['password']=$settings['hasPassword']?decryptSecret($encrypted):'';
    return $settings;
}

function emailSettingsPayload(array $data): array {
    if(!array_key_exists('enabled',$data)||!is_bool($data['enabled']))fail('INVALID_INPUT','enabled muss ein Boolean sein.',422);$enabled=$data['enabled'];
    $host=requireStringField($data,'host',255);$user=requireStringField($data,'user',255);$password=requireStringField($data,'password',4096,false);$fromEmail=requireStringField($data,'fromEmail',254);$fromName=requireStringField($data,'fromName',255);$replyTo=requireStringField($data,'replyTo',254);
    if(!array_key_exists('port',$data)||!is_int($data['port'])||$data['port']<1||$data['port']>65535)fail('INVALID_INPUT','SMTP-Port muss eine Ganzzahl zwischen 1 und 65535 sein.',422);$port=$data['port'];
    $encryption=requireStringField($data,'encryption',10);if(!in_array($encryption,['none','tls','ssl'],true))fail('INVALID_INPUT','Verschlüsselung muss none, tls oder ssl sein.',422);
    if($fromEmail!==''&&!filter_var($fromEmail,FILTER_VALIDATE_EMAIL))fail('INVALID_INPUT','Absender-E-Mail ist ungültig.',422);
    if($replyTo!==''&&!filter_var($replyTo,FILTER_VALIDATE_EMAIL))fail('INVALID_INPUT','Reply-To-Adresse ist ungültig.',422);
    if($enabled&&$host==='')fail('INVALID_INPUT','SMTP-Host ist bei aktiviertem Versand erforderlich.',422);
    if($enabled&&$fromEmail==='')fail('INVALID_INPUT','Absender-E-Mail ist bei aktiviertem Versand erforderlich.',422);
    return compact('enabled','host','port','encryption','user','password','fromEmail','fromName','replyTo');
}

function getEmailSettings(): never {requireAdmin();ok(['settings'=>emailSettings(false)]);}

function saveEmailSettings(): never {
    requireAdmin();requireCsrf();$settings=emailSettingsPayload(input());$existing=db()->query('SELECT smtp_password_encrypted FROM email_settings WHERE id=1')->fetchColumn();$encrypted=$settings['password']!==''?encryptSecret($settings['password']):($existing?:null);$now=date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO email_settings(id,enabled,smtp_host,smtp_port,encryption,smtp_username,smtp_password_encrypted,from_email,from_name,reply_to,created_at,updated_at) VALUES(1,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),smtp_host=VALUES(smtp_host),smtp_port=VALUES(smtp_port),encryption=VALUES(encryption),smtp_username=VALUES(smtp_username),smtp_password_encrypted=VALUES(smtp_password_encrypted),from_email=VALUES(from_email),from_name=VALUES(from_name),reply_to=VALUES(reply_to),updated_at=VALUES(updated_at)')->execute([$settings['enabled']?1:0,$settings['host'],$settings['port'],$settings['encryption'],$settings['user'],$encrypted,$settings['fromEmail'],$settings['fromName'],$settings['replyTo'],$now,$now]);
    ok(['settings'=>emailSettings(false)]);
}
