<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/bootstrap.php';
$mode=$argv[1]??'crypto';
function pass(string $name):void{echo "PASS $name\n";}
if($mode==='key'){try{encryptionKey();fwrite(STDERR,"FAIL ungültiger Key akzeptiert\n");exit(1);}catch(CryptoException){pass('ungültiger Encryption Key abgelehnt');exit(0);}}
$first=encryptSecret('streng-geheim');$second=encryptSecret('streng-geheim');if($first===$second)throw new RuntimeException('Nonce-Wiederverwendung');pass('gleiche Secrets erzeugen verschiedene Ciphertexte');if(decryptSecret($first)!=='streng-geheim')throw new RuntimeException('Roundtrip fehlgeschlagen');pass('Crypto Roundtrip');
$decoded=base64_decode(substr($first,6),true);$payload=json_decode($decoded,true,8,JSON_THROW_ON_ERROR);$tag=base64_decode($payload['tag'],true);$tag[0]=chr(ord($tag[0])^1);$payload['tag']=base64_encode($tag);$tampered='spsec:'.base64_encode(json_encode($payload,JSON_THROW_ON_ERROR));try{decryptSecret($tampered);throw new RuntimeException('Manipulation akzeptiert');}catch(CryptoException){pass('falscher Auth-Tag abgelehnt');}
$raw=$first;$raw[strlen($raw)-2]=$raw[strlen($raw)-2]==='A'?'B':'A';try{decryptSecret($raw);throw new RuntimeException('Manipulation akzeptiert');}catch(CryptoException){pass('manipulierter Ciphertext abgelehnt');}
