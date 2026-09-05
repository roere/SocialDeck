<?php
declare(strict_types=1);
function campaignReview(int $id,int $user,?int $retry=null): array {
    $c=campaignLoad($id,$user);$targets=[];
    foreach($c['targets'] as $t){
        if(!campaignActive($t,$c['platforms'])||in_array($t['status'],['published','publishing'],true))continue;
        if($retry!==null ? ($t['id']!==$retry||$t['status']!=='failed') : $t['status']==='failed')continue;
        if(($t['error_code']??'')==='DELIVERY_UNKNOWN')throw new CampaignException('DELIVERY_UNKNOWN','Versandstatus unklar: Originalbeitrag prüfen und Veröffentlichung gegebenenfalls manuell bestätigen.');
        $t['reply_text']=resolveTextWithBlocks($t['reply_text'],textBlockRowsByKey(db()));
        if(trim($t['reply_text'])==='')throw new CampaignException('EMPTY_REPLY','Alle aktiven Antworten benötigen einen Text.');
        $targets[]=$t;
    }
    if(!$targets)throw new CampaignException('NO_TARGETS','Keine freizugebenden Ziele. Fehlgeschlagene Ziele bitte einzeln erneut versuchen.');
    $review=['campaign_id'=>$id,'revision'=>$c['revision'],'name'=>$c['name'],'targets'=>$targets,'warnings'=>campaignWarnings($targets,campaignWarningConfig()),'active'=>count(array_filter($c['targets'],fn($t)=>campaignActive($t,$c['platforms']))),'disabled'=>count(array_filter($c['targets'],fn($t)=>!campaignActive($t,$c['platforms'])))];
    $token=bin2hex(random_bytes(32));
    campaignQuery('UPDATE campaigns SET review_json=?,review_token_hash=?,review_expires_at=? WHERE id=?',[json_encode($review,JSON_THROW_ON_ERROR),hash('sha256',$token),date('Y-m-d H:i:s',time()+600),$id]);
    $review['token']=$token;return $review;
}
function campaignPublish(int $id,int $user,array $data): array {
    $c=campaignLoad($id,$user);$stored=campaignQuery('SELECT review_json,review_token_hash,review_expires_at FROM campaigns WHERE id=?',[$id])->fetch();
    if(($data['confirmed']??null)!==true||!is_string($data['token']??null)||!$stored['review_token_hash']||!hash_equals($stored['review_token_hash'],hash('sha256',$data['token']))||strtotime($stored['review_expires_at'])<time())throw new CampaignException('APPROVAL','Eine aktuelle ausdrückliche Abschlussfreigabe ist erforderlich.',409);
    $review=json_decode($stored['review_json'],true);if($review['revision']!==$c['revision'])throw new CampaignException('STALE','Bitte Antworten erneut prüfen.',409);
    // Consume approval durably BEFORE any side effect. No queue, retry loop or parallel requests.
    campaignQuery('UPDATE campaigns SET review_json=NULL,review_token_hash=NULL,review_expires_at=NULL,revision=revision+1 WHERE id=?',[$id]);
    foreach($review['targets'] as $t){
        if(!campaignActive($t,$c['platforms'])||!campaignPublishable($t))continue;
        campaignQuery("UPDATE campaign_targets SET status='publishing',reply_text=?,reply_is_customized=1,error_code=NULL,error_message=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$t['reply_text'],$t['id']]);
        try{
            $account=campaignAccount((int)$t['social_account_id'],$t['provider_id']);
            $comment=campaignProvider($t['provider_id'])->comment($account??[],$t['external_post_urn']??$t['external_post_id'],$t['reply_text']);
        }catch(CampaignException $e){campaignQuery("UPDATE campaign_targets SET status='failed',error_code=?,error_message=?,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$e->errorCode,$e->getMessage(),$t['id']]);continue;
        }catch(Throwable){campaignQuery("UPDATE campaign_targets SET status='failed',error_code='DELIVERY_UNKNOWN',error_message='Versandstatus unklar. Originalbeitrag prüfen.',updated_at=CURRENT_TIMESTAMP WHERE id=?",[$t['id']]);continue;}
        // A persistence failure must leave 'publishing'; never turn an accepted comment into a retryable failure.
        campaignQuery("UPDATE campaign_targets SET status='published',external_comment_id=?,published_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$comment,$t['id']]);
        campaignQuery("UPDATE engagement_items SET status='commented',updated_at=CURRENT_TIMESTAMP WHERE id=?",[$t['engagement_item_id']]);
    }
    campaignUpdateStatus($id,$user);return campaignLoad($id,$user);
}
function campaignUpdateStatus(int $id,int $user): void {
    $c=campaignLoad($id,$user);campaignQuery('UPDATE campaigns SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[campaignStatus($c['targets'],$c['platforms']),$id]);
}
function campaignCapabilities(): array {
    $accounts=campaignQuery('SELECT a.id,a.provider_id,a.display_name,a.status,a.token_expires_at,a.scopes,p.enabled FROM social_accounts a LEFT JOIN provider_configs p ON p.provider_id=a.provider_id ORDER BY a.id')->fetchAll();
    foreach($accounts as &$a){$a['id']=(int)$a['id'];$adapter=campaignProvider($a['provider_id']);$a['capabilities']=$adapter&&$a['enabled']?$adapter->capabilities($a):['read'=>false,'write'=>false,'postRead'=>false];unset($a['scopes']);}return $accounts;
}
