<?php
declare(strict_types=1);
function campaignQuery(string $sql,array $params=[]): PDOStatement {$s=db()->prepare($sql);$s->execute($params);return $s;}
function campaignLoad(int $id,int $user): array {
    $c=campaignQuery('SELECT * FROM campaigns WHERE id=? AND created_by=?',[$id,$user])->fetch();
    if(!$c)throw new CampaignException('NOT_FOUND','Kampagne nicht gefunden.',404);
    $c['id']=(int)$c['id'];$c['revision']=(int)$c['revision'];$c['platforms']=json_decode($c['platform_settings_json'],true);
    $c['targets']=campaignQuery('SELECT t.*,e.post_excerpt,e.external_post_url,e.published_at post_published_at FROM campaign_targets t JOIN engagement_items e ON e.id=t.engagement_item_id WHERE t.campaign_id=? ORDER BY t.id',[$id])->fetchAll();
    foreach($c['targets'] as &$t){$t['id']=(int)$t['id'];$t['engagement_item_id']=(int)$t['engagement_item_id'];$t['enabled']=(bool)$t['enabled'];$t['reply_is_customized']=(bool)$t['reply_is_customized'];$t['publishable']=campaignPublishable($t);}
    unset($t,$c['review_json'],$c['review_token_hash'],$c['review_expires_at'],$c['platform_settings_json']);return $c;
}
function campaignAccount(?int $id,string $provider): ?array {
    if(!$id)return null;
    return campaignQuery('SELECT a.* FROM social_accounts a JOIN provider_configs p ON p.provider_id=a.provider_id AND p.enabled=1 WHERE a.id=? AND a.provider_id=?',[$id,$provider])->fetch()?:null;
}
function campaignPublishable(array $t): bool {
    $adapter=campaignProvider($t['provider_id']);$account=campaignAccount(isset($t['social_account_id'])?(int)$t['social_account_id']:null,$t['provider_id']);
    return $adapter && $account && $adapter->capabilities($account)['write'] && !empty($account['access_token_encrypted']) && $adapter->validPost($t['external_post_urn']??$t['external_post_id']);
}
function campaignSave(array $data,int $user,?int $id): array {
    $name=campaignString($data,'name',190);if($name==='')throw new CampaignException('NAME','Bitte einen Kampagnennamen eingeben.');
    $base=campaignString($data,'base_reply_text',50000,false);$platforms=[];
    if(isset($data['platforms'])&&!is_array($data['platforms']))throw new CampaignException('PLATFORMS','Ungültige Plattformauswahl.');
    foreach(providerDefinitions() as $provider=>$definition){$v=$data['platforms'][$provider]??true;if(!is_bool($v))throw new CampaignException('PLATFORMS','Ungültige Plattformauswahl.');$platforms[$provider]=$v;}
    $targets=$data['targets']??[];if(!is_array($targets)||!array_is_list($targets))throw new CampaignException('TARGETS','Ungültige Zielauswahl.');
    $pdo=db();$pdo->beginTransaction();
    try {
        $existing=$id?campaignLoad($id,$user):null;
        if($existing && ($data['revision']??null)!==$existing['revision'])throw new CampaignException('STALE','Kampagne wurde inzwischen geändert. Bitte neu laden.',409);
        if($existing && array_filter($existing['targets'],fn($t)=>$t['status']==='publishing'))throw new CampaignException('IN_PROGRESS','Ein Versandstatus ist noch ungeklärt. Zuerst den Originalbeitrag prüfen.',409);
        if(!$id){campaignQuery('INSERT INTO campaigns(name,base_reply_text,created_by,platform_settings_json) VALUES(?,?,?,?)',[$name,$base,$user,json_encode($platforms)]);$id=(int)$pdo->lastInsertId();}
        $byItem=[];foreach($existing['targets']??[] as $t)$byItem[$t['engagement_item_id']]=$t;
        $keep=[];
        foreach($targets as $incoming){
            if(!is_array($incoming)||!is_int($incoming['engagement_item_id']??null)||!is_bool($incoming['enabled']??null)||!is_bool($incoming['reply_is_customized']??null))throw new CampaignException('TARGET','Ungültiges Ziel.');
            $itemId=$incoming['engagement_item_id'];if(isset($keep[$itemId]))throw new CampaignException('DUPLICATE','Ein Beitrag darf nur einmal ausgewählt werden.');$keep[$itemId]=true;
            $item=campaignQuery('SELECT * FROM engagement_items WHERE id=? AND created_by=?',[$itemId,$user])->fetch();if(!$item)throw new CampaignException('ITEM','Beitrag nicht gefunden.',404);
            $old=$byItem[$itemId]??null;if($old&&$old['status']==='published')continue;
            $reply=campaignMergeReply($base,$incoming,$old);$enabled=$incoming['enabled'];$status=$old&&$old['status']==='failed'?'failed':(!$enabled||!$platforms[$item['provider_id']]?'disabled':(trim($reply['reply_text'])!==''?'ready':'draft'));
            campaignQuery('INSERT INTO campaign_targets(campaign_id,engagement_item_id,provider_id,social_account_id,external_post_id,external_post_urn,author_display_name,enabled,reply_text,reply_is_customized,status) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),reply_text=VALUES(reply_text),reply_is_customized=VALUES(reply_is_customized),status=VALUES(status),updated_at=CURRENT_TIMESTAMP',[$id,$itemId,$item['provider_id'],$item['social_account_id'],$item['external_post_id'],$item['external_post_urn'],$item['author_display_name'],(int)$enabled,$reply['reply_text'],(int)$reply['reply_is_customized'],$status]);
        }
        foreach($byItem as $itemId=>$old)if(!isset($keep[$itemId])){
            if(in_array($old['status'],['published','publishing'],true))throw new CampaignException('PUBLISHED','Bereits veröffentlichte Ziele bleiben als Nachweis erhalten.');
            campaignQuery('DELETE FROM campaign_targets WHERE id=?',[$old['id']]);
        }
        $all=campaignQuery('SELECT * FROM campaign_targets WHERE campaign_id=?',[$id])->fetchAll();$status=campaignStatus($all,$platforms);
        campaignQuery('UPDATE campaigns SET name=?,base_reply_text=?,platform_settings_json=?,status=?,revision=revision+1,review_json=NULL,review_token_hash=NULL,review_expires_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$name,$base,json_encode($platforms),$status,$id]);
        $pdo->commit();return campaignLoad($id,$user);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function campaignStoreItem(array $data,int $user): array {
    $provider=campaignString($data,'provider_id',50);if(!providerDefinition($provider))throw new CampaignException('PROVIDER','Unbekannte Plattform.');
    $url=campaignUrl(campaignString($data,'external_post_url',2048));
    $accountId=$data['social_account_id']??null;
    if($accountId!==null&&(!is_int($accountId)||!campaignAccount($accountId,$provider)))throw new CampaignException('ACCOUNT','Das ausgewählte Konto ist nicht verfügbar.');
    $urn=campaignString($data,'external_post_urn',500);$adapter=campaignProvider($provider);
    if($urn!==''&&(!$adapter||!$adapter->validPost($urn)))throw new CampaignException('URN','Bitte nur eine vom Provider gelieferte gültige Post-URN verwenden.');
    $author=campaignString($data,'author_display_name',255)?:'Unbekannter Autor';$excerpt=campaignString($data,'post_excerpt',8000,false);
    // Manual URLs are identifiers only. No URL requests, scraping or inferred URNs.
    $key=hash('sha256',($accountId??0).'|'.($urn!==''?$urn:$url));
    campaignQuery("INSERT INTO engagement_items(created_by,provider_id,social_account_id,external_post_id,external_post_urn,external_post_url,dedupe_key,author_display_name,post_excerpt,relevance_source,relevance_reason) VALUES(?,?,?,?,?,?,?,?,?,'manual','Manuell hinzugefügt') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",[$user,$provider,$accountId,$urn?:'manual:'.$key,$urn?:null,$url,$key,$author,$excerpt]);
    $id=(int)db()->lastInsertId();return campaignQuery('SELECT * FROM engagement_items WHERE id=?',[$id])->fetch();
}
