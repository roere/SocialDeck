<?php
declare(strict_types=1);
require_once __DIR__.'/core.php';require_once __DIR__.'/providers.php';require_once __DIR__.'/repository.php';require_once __DIR__.'/service.php';
function campaignRoutes(string $path,string $method): void {
    if(!preg_match('#^/api/(campaigns|engagement-items)(?:/([1-9][0-9]*))?(?:/(review|publish|manual-published|read))?$#',$path,$m))return;
    $user=requireAuth();$uid=(int)$user['id'];$id=isset($m[2])&&$m[2]!==''?(int)$m[2]:null;$action=$m[3]??null;$campaign=$m[1]==='campaigns';
    $allowed=$action?['POST']:($id?($campaign?['GET','PUT','DELETE']:['PUT']):['GET','POST']);
    if(!in_array($method,$allowed,true))fail('METHOD_NOT_ALLOWED','HTTP-Methode nicht erlaubt.',405,['Allow'=>implode(', ',$allowed)]);
    if($method!=='GET')requireCsrf();$data=$method==='GET'?[]:input();$lock=null;
    try {
        if($campaign&&$id&&$method!=='GET'){$lock='socialdeck:campaign:'.$id;if((int)campaignQuery('SELECT GET_LOCK(?,0)',[$lock])->fetchColumn()!==1)throw new CampaignException('BUSY','Kampagne wird gerade bearbeitet. Bitte später erneut versuchen.',409);}
        if($campaign){
            if(!$id&&$method==='GET'){
                $ids=campaignQuery('SELECT id FROM campaigns WHERE created_by=? ORDER BY updated_at DESC,id DESC',[$uid])->fetchAll();
                $result=['campaigns'=>array_map(fn($row)=>campaignLoad((int)$row['id'],$uid),$ids),'accounts'=>campaignCapabilities(),'warnings'=>campaignWarningConfig(),'providers'=>array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name']],array_values(providerDefinitions()))];
            }elseif(!$id&&$method==='POST')$result=['campaign'=>campaignSave($data,$uid,null)];
            elseif($id&&$action==='review')$result=['review'=>campaignReview($id,$uid,isset($data['retry_target_id'])?(int)$data['retry_target_id']:null)];
            elseif($id&&$action==='publish')$result=['campaign'=>campaignPublish($id,$uid,$data)];
            elseif($id&&$action==='manual-published'){
                $c=campaignLoad($id,$uid);$target=null;foreach($c['targets'] as $t)if($t['id']===($data['target_id']??null))$target=$t;
                if(($data['confirmed']??null)!==true||!$target||!campaignActive($target,$c['platforms']))throw new CampaignException('CONFIRM','Die manuelle Veröffentlichung eines aktiven Ziels muss ausdrücklich bestätigt werden.');
                campaignQuery("UPDATE campaign_targets SET status='published',published_at=COALESCE(published_at,CURRENT_TIMESTAMP),error_code=NULL,error_message=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$target['id']]);
                campaignQuery("UPDATE engagement_items SET status='commented' WHERE id=?",[$target['engagement_item_id']]);
                campaignQuery('UPDATE campaigns SET revision=revision+1,review_json=NULL,review_token_hash=NULL WHERE id=?',[$id]);campaignUpdateStatus($id,$uid);$result=['campaign'=>campaignLoad($id,$uid)];
            }elseif($id&&$action)throw new CampaignException('NOT_FOUND','Aktion nicht gefunden.',404);
            elseif($method==='GET')$result=['campaign'=>campaignLoad($id,$uid)];
            elseif($method==='PUT')$result=['campaign'=>campaignSave($data,$uid,$id)];
            else{ $c=campaignLoad($id,$uid);if(array_filter($c['targets'],fn($t)=>$t['status']==='publishing'))throw new CampaignException('IN_PROGRESS','Ungeklärten Versand zuerst prüfen.',409);campaignQuery('DELETE FROM campaigns WHERE id=? AND created_by=?',[$id,$uid]);$result=['deleted'=>true]; }
        }else{
            if(!$id&&$method==='GET')$result=['items'=>campaignQuery('SELECT * FROM engagement_items WHERE created_by=? ORDER BY discovered_at DESC,id DESC',[$uid])->fetchAll()];
            elseif(!$id&&$method==='POST')$result=['item'=>campaignStoreItem($data,$uid)];
            else{
                $item=campaignQuery('SELECT * FROM engagement_items WHERE id=? AND created_by=?',[$id,$uid])->fetch();if(!$item)throw new CampaignException('NOT_FOUND','Beitrag nicht gefunden.',404);
                if($action==='read'){
                    $adapter=campaignProvider($item['provider_id']);$account=campaignAccount($item['social_account_id']?(int)$item['social_account_id']:null,$item['provider_id']);
                    if(!$adapter||!$account)throw new CampaignException('READ_PERMISSION','Automatischer Abruf ist für dieses Konto nicht verfügbar.');
                    $post=$adapter->read($account,$item['external_post_urn']??$item['external_post_id']);
                    campaignQuery('UPDATE engagement_items SET post_excerpt=?,external_author_id=?,published_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$post['post_excerpt'],$post['external_author_id'],$post['published_at'],$id]);
                }elseif(!$action){$status=$data['status']??null;if(!in_array($status,['new','ignored'],true))throw new CampaignException('STATUS','Ungültiger Beitragsstatus.');campaignQuery('UPDATE engagement_items SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$status,$id]);}
                else throw new CampaignException('NOT_FOUND','Aktion nicht gefunden.',404);
                $result=['item'=>campaignQuery('SELECT * FROM engagement_items WHERE id=?',[$id])->fetch()];
            }
        }
    }catch(CampaignException $e){if($lock)campaignQuery('SELECT RELEASE_LOCK(?)',[$lock]);fail($e->errorCode,$e->getMessage(),$e->httpStatus);
    }catch(TextBlockResolutionException $e){if($lock)campaignQuery('SELECT RELEASE_LOCK(?)',[$lock]);fail($e->errorCode,$e->getMessage(),422);
    }finally{if($lock)campaignQuery('SELECT RELEASE_LOCK(?)',[$lock]);}
    ok($result);
}
