<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/text-block-placeholders.php';

function row(string $key,string $content,bool $active=true): array{return ['id'=>1,'block_key'=>$key,'title'=>$key,'content'=>$content,'is_active'=>$active?1:0,'is_system'=>0];}
function expectFailure(string $name,string $code,callable $call): void{try{$call();throw new RuntimeException($name.' wurde nicht abgelehnt');}catch(TextBlockResolutionException $exception){if($exception->errorCode!==$code)throw $exception;echo "PASS $name\n";}}

$rows=['abschluss'=>row('abschluss','Viele Grüße'),'begruessung'=>row('begruessung',"Hallo\n\n{{abschluss}}"),'haupttext'=>row('haupttext','Start: {{begruessung}}')];
if(resolveTextBlockFromRows('begruessung',$rows)!=="Hallo\n\nViele Grüße")throw new RuntimeException('Einfache Verschachtelung fehlgeschlagen');echo "PASS einfache Textbaustein-Verschachtelung\n";
if(resolveTextBlockFromRows('haupttext',$rows)!=="Start: Hallo\n\nViele Grüße")throw new RuntimeException('Mehrstufige Verschachtelung fehlgeschlagen');echo "PASS mehrstufige Textbaustein-Verschachtelung\n";
$multiple=['a'=>row('a','A'),'b'=>row('b','B'),'root'=>row('root','{{a}} + {{b}} + {{a}}')];if(resolveTextBlockFromRows('root',$multiple)!=='A + B + A')throw new RuntimeException('Mehrere Referenzen fehlgeschlagen');echo "PASS mehrere Textbaustein-Referenzen\n";
$exact=[];for($index=1;$index<=10;$index++)$exact['e'.$index]=row('e'.$index,$index===10?'Ende':'{{e'.($index+1).'}}');if(resolveTextBlockFromRows('e1',$exact,10)!=='Ende')throw new RuntimeException('Tiefe zehn fehlgeschlagen');echo "PASS Textbaustein-Tiefe zehn\n";
expectFailure('unbekannte Textbaustein-Referenz','UNKNOWN_PLACEHOLDER',fn()=>resolveTextBlockFromRows('missing',['root'=>row('root','{{missing}}')]));
expectFailure('direkter Textbaustein-Zyklus','TEXT_BLOCK_CYCLE',fn()=>resolveTextBlockFromRows('a',['a'=>row('a','{{a}}')]));
expectFailure('indirekter Textbaustein-Zyklus','TEXT_BLOCK_CYCLE',fn()=>resolveTextBlockFromRows('a',['a'=>row('a','{{b}}'),'b'=>row('b','{{c}}'),'c'=>row('c','{{a}}')]));
$deep=[];for($index=1;$index<=11;$index++)$deep['d'.$index]=row('d'.$index,$index===11?'Ende':'{{d'.($index+1).'}}');
expectFailure('maximale Textbaustein-Tiefe','TEXT_BLOCK_DEPTH',fn()=>resolveTextBlockFromRows('d1',$deep,10));
expectFailure('inaktive Textbaustein-Referenz','INACTIVE_PLACEHOLDER',fn()=>resolveTextBlockFromRows('a',['a'=>row('a','{{b}}'),'b'=>row('b','Text',false)]));
