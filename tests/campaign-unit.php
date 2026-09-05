<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/bootstrap.php';
if(envValue('APP_ENV')!=='test'||!str_starts_with(linkedInEndpoint('api'),'http://127.0.0.1:19090'))throw new RuntimeException('Only the isolated mock environment is allowed.');
function checkCampaign(bool $ok,string $name): void {if(!$ok)throw new RuntimeException($name);echo "PASS Campaign $name\n";}
function rejectsCampaign(callable $fn,string $code): void {try{$fn();}catch(CampaignException $e){checkCampaign($e->errorCode===$code,"reject $code");return;}throw new RuntimeException("Expected $code");}
$account=['status'=>'connected','token_expires_at'=>date('Y-m-d H:i:s',time()+3600),'scopes'=>'r_member_social_feed r_member_social w_member_social_feed','external_account_id'=>'campaign-test','access_token_encrypted'=>'mock'];$calls=[];
$adapter=new LinkedInCampaignProvider(function($method,$path,$payload)use(&$calls){$calls[]=[$method,$path,$payload];return ['status'=>201,'id'=>'123'];});
checkCampaign($adapter->capabilities($account)['read']&&$adapter->capabilities($account)['write'],'granted capabilities');
checkCampaign(!$adapter->capabilities(array_replace($account,['scopes'=>'w_member_social']))['write'],'post scope is not comment scope');
checkCampaign(!$adapter->capabilities(array_replace($account,['token_expires_at'=>null]))['write'],'unknown expiry fails closed');
rejectsCampaign(fn()=>$adapter->comment(array_replace($account,['scopes'=>'']),'urn:li:share:1','Text'),'WRITE_PERMISSION');
checkCampaign(!$calls,'missing scope makes zero calls');
rejectsCampaign(fn()=>$adapter->comment($account,'https://linkedin.com/post/1','Text'),'POST_ID');
rejectsCampaign(fn()=>$adapter->comment($account,'urn:li:share:1',' '),'EMPTY_REPLY');
checkCampaign($adapter->comment($account,'urn:li:share:1','Hallo')==='123'&&$calls[0][2]['message']['text']==='Hallo','official comment payload');
foreach([401,403,429] as $code){$bad=new LinkedInCampaignProvider(fn()=>['status'=>$code]);rejectsCampaign(fn()=>$bad->read($account,'urn:li:share:1'),'PROVIDER_'.$code);rejectsCampaign(fn()=>$bad->comment($account,'urn:li:share:1','Text'),'PROVIDER_'.$code);}
$reader=new LinkedInCampaignProvider(fn()=>['status'=>200,'body'=>['id'=>'urn:li:share:1','commentary'=>'API excerpt','publishedAt'=>1750000000000,'author'=>'urn:li:person:other']]);
checkCampaign($reader->read($account,'urn:li:share:1')['post_excerpt']==='API excerpt','read known post');
rejectsCampaign(fn()=>$reader->read(array_replace($account,['scopes'=>'']),'urn:li:share:1'),'READ_PERMISSION');
$empty=new LinkedInCampaignProvider(fn()=>['status'=>200,'body'=>[]]);rejectsCampaign(fn()=>$empty->read($account,'urn:li:share:1'),'INVALID_RESPONSE');
rejectsCampaign(fn()=>campaignUrl('javascript:alert(1)'),'INVALID_URL');
$target=['enabled'=>true,'provider_id'=>'linkedin','reply_text'=>'Hallo','status'=>'ready'];
checkCampaign(count(campaignWarnings(array_fill(0,5,$target),['warning'=>5,'strong'=>10]))===3,'warning threshold and identical replies');
checkCampaign(str_contains(campaignWarnings(array_fill(0,10,$target),['warning'=>5,'strong'=>10])[0],'Achtung'),'strong warning');
checkCampaign(!campaignActive($target,['linkedin'=>false]),'platform disabling');
checkCampaign(campaignStatus([array_replace($target,['status'=>'published']),array_replace($target,['status'=>'failed'])],[])==='partially_published','partial status');
$custom=['reply_text'=>'Individuell','reply_is_customized'=>true];checkCampaign(campaignMergeReply('Neue Basis',$custom,null)['reply_text']==='Individuell','custom reply preserved');
checkCampaign(campaignMergeReply('Neue Basis',['reply_is_customized'=>false],null)['reply_text']==='Neue Basis','explicit reset');

$pdo=db();$uid=null;$aid=null;
try{
 campaignQuery("INSERT INTO users(username,email,password_hash,role,created_at,updated_at) VALUES('campaign-tests','campaign-tests@example.test','unused','user',NOW(),NOW())");$uid=(int)$pdo->lastInsertId();
 campaignQuery("INSERT INTO social_accounts(provider_id,external_account_id,display_name,access_token_encrypted,token_expires_at,scopes,status,created_at,updated_at) VALUES('linkedin','campaign-test','Campaign Test',?,?,?,'connected',NOW(),NOW())",[encryptSecret('mock-access-token'),date('Y-m-d H:i:s',time()+3600),$account['scopes']]);$aid=(int)$pdo->lastInsertId();
 campaignQuery("INSERT INTO text_blocks(block_key,title,content,created_at,updated_at) VALUES('campaign-test-snippet','Campaign Test','https://example.test/one',NOW(),NOW())");
 $manual=campaignStoreItem(['provider_id'=>'facebook','external_post_url'=>'https://example.test/post','post_excerpt'=>'<img src=x onerror=alert(1)>'],$uid);
 $again=campaignStoreItem(['provider_id'=>'facebook','external_post_url'=>'https://example.test/post'],$uid);checkCampaign($manual['id']===$again['id'],'dedupe manual input');checkCampaign($manual['external_post_urn']===null,'no inferred URN');
 $first=campaignStoreItem(['provider_id'=>'linkedin','external_post_url'=>'https://example.test/1','external_post_urn'=>'urn:li:share:1','social_account_id'=>$aid],$uid);
 $second=campaignStoreItem(['provider_id'=>'linkedin','external_post_url'=>'https://example.test/2','external_post_urn'=>'urn:li:share:2','social_account_id'=>$aid],$uid);
 $c=campaignSave(['name'=>'Test','base_reply_text'=>'Basis','targets'=>[]],$uid,null);$id=$c['id'];checkCampaign($c['status']==='draft','create empty campaign');
 $c['targets']=[['engagement_item_id'=>(int)$first['id'],'enabled'=>true,'reply_is_customized'=>false]];$c=campaignSave($c,$uid,$id);checkCampaign(count($c['targets'])===1&&$c['targets'][0]['reply_text']==='Basis','select one target');
 $c['targets'][]=['engagement_item_id'=>(int)$second['id'],'enabled'=>true,'reply_is_customized'=>true,'reply_text'=>'Individuell'];$c['targets'][]=['engagement_item_id'=>(int)$manual['id'],'enabled'=>false,'reply_is_customized'=>false];$c=campaignSave($c,$uid,$id);checkCampaign(count($c['targets'])===3,'multiple and disabled targets persisted');
 $stale=$c;$c['base_reply_text']='Neue Basis {{campaign-test-snippet}}';$c=campaignSave($c,$uid,$id);checkCampaign($c['targets'][0]['reply_text']==='Neue Basis {{campaign-test-snippet}}'&&$c['targets'][1]['reply_text']==='Individuell','save protects custom replies');
 rejectsCampaign(fn()=>campaignSave($stale,$uid,$id),'STALE');rejectsCampaign(fn()=>campaignLoad($id,$uid+100000),'NOT_FOUND');
 $c['platforms']['linkedin']=false;$c=campaignSave($c,$uid,$id);rejectsCampaign(fn()=>campaignReview($id,$uid),'NO_TARGETS');checkCampaign(count($c['targets'])===3,'disabled platform keeps targets');
 $c['platforms']['linkedin']=true;$c=campaignSave($c,$uid,$id);
 $review=campaignReview($id,$uid);checkCampaign(count($review['targets'])===2&&$review['disabled']===1,'review excludes disabled');
 rejectsCampaign(fn()=>campaignPublish($id,$uid,['token'=>$review['token']]),'APPROVAL');
 $c['name']='Changed';$c=campaignSave($c,$uid,$id);rejectsCampaign(fn()=>campaignPublish($id,$uid,['token'=>$review['token'],'confirmed'=>true]),'APPROVAL');
 $review=campaignReview($id,$uid);checkCampaign($review['targets'][0]['reply_text']==='Neue Basis https://example.test/one','shared recursive text-block resolution in review');
 campaignQuery("UPDATE text_blocks SET content='https://example.test/two' WHERE block_key='campaign-test-snippet'");
 $c=campaignPublish($id,$uid,['token'=>$review['token'],'confirmed'=>true]);
 checkCampaign($c['targets'][0]['reply_text']==='Neue Basis https://example.test/one','approval freezes resolved final text');
 checkCampaign($c['targets'][0]['status']==='published'&&$c['targets'][1]['status']==='failed'&&$c['targets'][2]['status']==='disabled','sequential per-target results');checkCampaign($c['status']==='partially_published','persist partial campaign');
 rejectsCampaign(fn()=>campaignPublish($id,$uid,['token'=>$review['token'],'confirmed'=>true]),'APPROVAL');
 rejectsCampaign(fn()=>campaignReview($id,$uid),'NO_TARGETS');
 $retry=campaignReview($id,$uid,$c['targets'][1]['id']);checkCampaign(count($retry['targets'])===1&&$retry['targets'][0]['id']===$c['targets'][1]['id'],'retry only explicit failed target');
 campaignQuery('DELETE FROM campaigns WHERE id=?',[$id]);checkCampaign((int)campaignQuery('SELECT COUNT(*) FROM campaign_targets WHERE campaign_id=?',[$id])->fetchColumn()===0,'delete cascades targets');
 rejectsCampaign(fn()=>campaignLoad($id,$uid),'NOT_FOUND');
}finally{campaignQuery("DELETE FROM text_blocks WHERE block_key='campaign-test-snippet'");if($uid){campaignQuery('DELETE FROM campaigns WHERE created_by=?',[$uid]);campaignQuery('DELETE FROM engagement_items WHERE created_by=?',[$uid]);campaignQuery('DELETE FROM users WHERE id=?',[$uid]);}if($aid)campaignQuery('DELETE FROM social_accounts WHERE id=?',[$aid]);}
