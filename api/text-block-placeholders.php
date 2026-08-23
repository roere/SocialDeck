<?php
declare(strict_types=1);

final class TextBlockResolutionException extends RuntimeException {
    public function __construct(public readonly string $errorCode,string $message){parent::__construct($message);}
}

function systemTextBlockCatalog(): array {
    // Zurzeit benötigt keine Anwendungsfunktion einen festen Systembaustein.
    return [];
}

function seedSystemTextBlocks(PDO $pdo,?array $catalog=null): void {
    $now=date('Y-m-d H:i:s');$find=$pdo->prepare('SELECT id FROM text_blocks WHERE block_key=?');
    $insert=$pdo->prepare('INSERT INTO text_blocks(block_key,title,content,category,is_active,is_system,sort_order,created_at,updated_at) VALUES(?,?,?,?,1,1,?,?,?)');
    $mark=$pdo->prepare('UPDATE text_blocks SET is_system=1,is_active=1,updated_at=? WHERE id=?');
    foreach($catalog??systemTextBlockCatalog() as $definition){$find->execute([$definition['key']]);$id=$find->fetchColumn();if($id!==false)$mark->execute([$now,$id]);else $insert->execute([$definition['key'],$definition['title'],$definition['defaultContent'],$definition['category'],$definition['sortOrder']??0,$now,$now]);}
}

function textBlockReferenceKeys(string $content): array {
    $pattern='/{{\s*([A-Za-z0-9][A-Za-z0-9_-]*)\s*}}/';preg_match_all($pattern,$content,$matches);$remainder=preg_replace($pattern,'',$content);
    if($remainder===null||preg_match('/{{|}}|(?<!{){[A-Za-z0-9][A-Za-z0-9_-]*}(?!})/',$remainder)===1)throw new TextBlockResolutionException('INVALID_PLACEHOLDER','Ungültige Platzhaltersyntax. Verwende {{schluessel}}.');
    return array_values(array_unique($matches[1]));
}

function textBlockRowsByKey(PDO $pdo): array {
    $rows=$pdo->query('SELECT id,block_key,title,content,is_active,is_system FROM text_blocks')->fetchAll();$result=[];foreach($rows as $row)$result[$row['block_key']]=$row;return $result;
}

function resolveTextBlockFromRows(string $key,array $rows,int $maxDepth=10,array $path=[]): string {
    if(in_array($key,$path,true))throw new TextBlockResolutionException('TEXT_BLOCK_CYCLE','Zirkuläre Textbaustein-Referenz: '.implode(' -> ',array_merge($path,[$key])).'.');
    if(count($path)>=$maxDepth)throw new TextBlockResolutionException('TEXT_BLOCK_DEPTH','Maximale Verschachtelungstiefe von '.$maxDepth.' Textbausteinen überschritten.');
    $row=$rows[$key]??null;if(!$row)throw new TextBlockResolutionException('UNKNOWN_PLACEHOLDER','Unbekannter Platzhalter: {{'.$key.'}}');
    if(!(bool)$row['is_active'])throw new TextBlockResolutionException('INACTIVE_PLACEHOLDER','Inaktiver Textbaustein kann nicht verwendet werden: {{'.$key.'}}');
    $nextPath=array_merge($path,[$key]);
    return (string)preg_replace_callback('/{{\s*([A-Za-z0-9][A-Za-z0-9_-]*)\s*}}/',fn(array $match)=>resolveTextBlockFromRows($match[1],$rows,$maxDepth,$nextPath),(string)$row['content']);
}

function resolveTextBlock(string $key,int $maxDepth=10): string {
    return resolveTextBlockFromRows($key,textBlockRowsByKey(db()),$maxDepth);
}

function validateTextBlockReferences(string $key,string $content,?int $id=null): void {
    try{
        textBlockReferenceKeys($content);$rows=textBlockRowsByKey(db());
        if($id!==null)foreach($rows as $existingKey=>$row)if((int)$row['id']===$id){unset($rows[$existingKey]);break;}
        $rows[$key]=['id'=>$id??0,'block_key'=>$key,'title'=>'','content'=>$content,'is_active'=>1,'is_system'=>0];resolveTextBlockFromRows($key,$rows);
        foreach($rows as $row)if((bool)$row['is_active'])resolveTextBlockFromRows($row['block_key'],$rows);
    }catch(TextBlockResolutionException $exception){fail($exception->errorCode,$exception->getMessage(),422);}
}

function referencingTextBlocks(string $key,?int $excludeId=null): array {
    $rows=db()->query('SELECT id,title,content FROM text_blocks ORDER BY title,id')->fetchAll();$references=[];
    foreach($rows as $row){if($excludeId!==null&&(int)$row['id']===$excludeId)continue;try{$keys=textBlockReferenceKeys($row['content']);}catch(TextBlockResolutionException){continue;}if(in_array($key,$keys,true))$references[]=$row['title'];}
    return $references;
}
