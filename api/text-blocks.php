<?php
declare(strict_types=1);

function textBlockPayload(array $data,?array $existing=null): array {
    if(array_key_exists('isSystem',$data)||array_key_exists('is_system',$data))fail('SYSTEM_STATUS_IMMUTABLE','Der Systemstatus kann nicht über die Textbaustein-API geändert werden.',422);
    $title=requireStringField($data,'title',255);if($title==='')fail('INVALID_INPUT','Titel ist erforderlich.',422);
    $key=requireStringField($data,'key',100);if($key===''||preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,99}$/',$key)!==1)fail('INVALID_INPUT','Schlüssel darf nur Buchstaben, Zahlen, _ und - enthalten.',422);
    $content=requireStringField($data,'content',50000,false);$category=requireStringField($data,'category',100);
    if(!array_key_exists('isActive',$data)||!is_bool($data['isActive']))fail('INVALID_INPUT','isActive muss ein Boolean sein.',422);
    if(!array_key_exists('sortOrder',$data)||!is_int($data['sortOrder'])||$data['sortOrder'] < -1000000||$data['sortOrder'] > 1000000)fail('INVALID_INPUT','sortOrder muss eine Ganzzahl zwischen -1000000 und 1000000 sein.',422);
    if($existing&&(bool)$existing['is_system']&&$key!==$existing['block_key'])fail('SYSTEM_KEY_IMMUTABLE','Der Schlüssel eines System-Textbausteins kann nicht geändert werden.',409);
    if($existing&&(bool)$existing['is_system']&&!$data['isActive'])fail('SYSTEM_BLOCK_REQUIRED','System-Textbausteine können nicht deaktiviert werden.',409);
    if($existing&&(bool)$existing['is_active']&&!$data['isActive']){$references=referencingTextBlocks($existing['block_key'],(int)$existing['id']);if($references)fail('TEXT_BLOCK_REFERENCED','Der Textbaustein kann nicht deaktiviert werden, weil er von „'.implode('“, „',$references).'“ verwendet wird.',409);}
    if($existing&&$key!==$existing['block_key']){$references=referencingTextBlocks($existing['block_key'],(int)$existing['id']);if($references)fail('TEXT_BLOCK_REFERENCED','Der Schlüssel kann nicht geändert werden, weil der Textbaustein von „'.implode('“, „',$references).'“ verwendet wird.',409);}
    validateTextBlockReferences($key,$content,$existing?(int)$existing['id']:null);
    return ['key'=>$key,'title'=>$title,'content'=>$content,'category'=>$category,'isActive'=>$data['isActive'],'sortOrder'=>$data['sortOrder']];
}

function publicTextBlock(array $row): array {
    return ['id'=>(int)$row['id'],'key'=>$row['block_key'],'title'=>$row['title'],'content'=>$row['content'],'category'=>$row['category'],'isActive'=>(bool)$row['is_active'],'isSystem'=>(bool)$row['is_system'],'sortOrder'=>(int)$row['sort_order'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at']];
}

function listTextBlocks(): never {
    requireAdmin();$rows=db()->query('SELECT id,block_key,title,content,category,is_active,is_system,sort_order,created_at,updated_at FROM text_blocks ORDER BY category,sort_order,title,id')->fetchAll();ok(['textBlocks'=>array_map('publicTextBlock',$rows),'placeholderSyntax'=>'{{key}}']);
}

function listActiveTextBlocks(): never {
    $rows=db()->query('SELECT id,block_key,title,category,sort_order FROM text_blocks WHERE is_active=1 ORDER BY category,sort_order,title,id')->fetchAll();
    ok(['textBlocks'=>array_map(fn(array $row)=>['id'=>(int)$row['id'],'key'=>$row['block_key'],'title'=>$row['title'],'category'=>$row['category'],'sortOrder'=>(int)$row['sort_order']],$rows),'placeholderSyntax'=>'{{key}}']);
}

function resolvePostText(): never {
    $text=requireStringField(input(),'text',50000,false);
    try{
        textBlockReferenceKeys($text);$rows=textBlockRowsByKey(db());
        $resolved=(string)preg_replace_callback('/{{\s*([A-Za-z0-9][A-Za-z0-9_-]*)\s*}}/',fn(array $match)=>resolveTextBlockFromRows($match[1],$rows),$text);
        ok(['text'=>$resolved]);
    }catch(TextBlockResolutionException $exception){fail($exception->errorCode,$exception->getMessage(),422);}
}

function createTextBlock(): never {
    requireAdmin();requireCsrf();$block=textBlockPayload(input());$now=date('Y-m-d H:i:s');
    try{db()->prepare('INSERT INTO text_blocks(block_key,title,content,category,is_active,is_system,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,0,?,?,?)')->execute([$block['key'],$block['title'],$block['content'],$block['category'],$block['isActive']?1:0,$block['sortOrder'],$now,$now]);}
    catch(PDOException $exception){if($exception->getCode()==='23000')fail('DUPLICATE_KEY','Dieser Schlüssel ist bereits vergeben.',409);throw $exception;}
    $id=(int)db()->lastInsertId();$stmt=db()->prepare('SELECT * FROM text_blocks WHERE id=?');$stmt->execute([$id]);ok(['textBlock'=>publicTextBlock($stmt->fetch())],201);
}

function updateTextBlock(int $id): never {
    requireAdmin();requireCsrf();$existing=db()->prepare('SELECT * FROM text_blocks WHERE id=?');$existing->execute([$id]);$existingRow=$existing->fetch();if(!$existingRow)fail('NOT_FOUND','Textbaustein nicht gefunden.',404);$block=textBlockPayload(input(),$existingRow);$now=date('Y-m-d H:i:s');
    try{$stmt=db()->prepare('UPDATE text_blocks SET block_key=?,title=?,content=?,category=?,is_active=?,sort_order=?,updated_at=? WHERE id=?');$stmt->execute([$block['key'],$block['title'],$block['content'],$block['category'],$block['isActive']?1:0,$block['sortOrder'],$now,$id]);}
    catch(PDOException $exception){if($exception->getCode()==='23000')fail('DUPLICATE_KEY','Dieser Schlüssel ist bereits vergeben.',409);throw $exception;}
    if($stmt->rowCount()!==1){$exists=db()->prepare('SELECT 1 FROM text_blocks WHERE id=?');$exists->execute([$id]);if(!$exists->fetchColumn())fail('NOT_FOUND','Textbaustein nicht gefunden.',404);}
    $saved=db()->prepare('SELECT * FROM text_blocks WHERE id=?');$saved->execute([$id]);ok(['textBlock'=>publicTextBlock($saved->fetch())]);
}

function deleteTextBlock(int $id): never {
    requireAdmin();requireCsrf();$find=db()->prepare('SELECT block_key,is_system FROM text_blocks WHERE id=?');$find->execute([$id]);$block=$find->fetch();if(!$block)fail('NOT_FOUND','Textbaustein nicht gefunden.',404);if((bool)$block['is_system'])fail('SYSTEM_BLOCK_REQUIRED','System-Textbausteine können nicht gelöscht werden.',409);$references=referencingTextBlocks($block['block_key'],$id);if($references)fail('TEXT_BLOCK_REFERENCED','Der Textbaustein kann nicht gelöscht werden, weil er von „'.implode('“, „',$references).'“ verwendet wird.',409);$stmt=db()->prepare('DELETE FROM text_blocks WHERE id=?');$stmt->execute([$id]);ok(['deleted'=>true,'id'=>$id]);
}
