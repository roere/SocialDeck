<?php
declare(strict_types=1);
// Adapters expose safe capabilities; no token or upstream response crosses this boundary.
interface CampaignProvider {
    public function capabilities(array $account): array;
    public function validPost(string $id): bool;
    public function read(array $account,string $id): array;
    public function comment(array $account,string $id,string $text): string;
}
function campaignProvider(string $id): ?CampaignProvider {
    return $id==='linkedin'?new LinkedInCampaignProvider():null;
}
final class LinkedInCampaignProvider implements CampaignProvider {
    public function __construct(private mixed $transport=null) {}
    public function capabilities(array $account): array {
        $valid=($account['status']??'')==='connected' && !empty($account['token_expires_at']) && strtotime($account['token_expires_at'])>time();
        $scopes=linkedInScopeList($account['scopes']??null);
        return ['read'=>$valid&&in_array('r_member_social_feed',$scopes,true), 'write'=>$valid&&in_array('w_member_social_feed',$scopes,true),
            'postRead'=>$valid&&in_array('r_member_social_feed',$scopes,true)&&in_array('r_member_social',$scopes,true)];
    }
    public function validPost(string $id): bool {return preg_match('/^urn:li:(share|ugcPost):[0-9]+$/D',$id)===1;}
    private function request(array $account,string $method,string $path,?array $payload=null): array {
        if($this->transport)return ($this->transport)($method,$path,$payload);
        $token=decryptSecret($account['access_token_encrypted']);
        $options=['method'=>$method,'timeout'=>15,'ignore_errors'=>true,'follow_location'=>0,'header'=>implode("\r\n",linkedInApiHeaders($token))];
        if($payload!==null)$options['content']=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $body=@file_get_contents(rtrim(linkedInEndpoint('api'),'/').$path,false,stream_context_create(['http'=>$options]));
        $headers=$http_response_header??[];$status=0;$id=null;
        if(isset($headers[0])&&preg_match('/\s(\d{3})\s/',$headers[0],$m))$status=(int)$m[1];
        foreach($headers as $header)if(stripos($header,'x-restli-id:')===0)$id=trim(substr($header,12));
        return ['status'=>$status,'id'=>$id,'body'=>is_string($body)?json_decode($body,true):null];
    }
    private function check(array $response,int $expected): void {
        $code=(int)$response['status'];if($code===$expected)return;
        $message=match($code){401=>'Die Verbindung ist abgelaufen. Bitte erneut verbinden.',403=>'Die erforderliche LinkedIn-Berechtigung fehlt oder der Beitrag ist nicht zugänglich.',429=>'LinkedIn lässt derzeit keine weitere Anfrage zu. Bitte später selbst erneut versuchen.',default=>'LinkedIn-Anfrage fehlgeschlagen. Bei unklarem Versandstatus zuerst den Originalbeitrag prüfen.'};
        throw new CampaignException(($code===0||$code>=500)?'DELIVERY_UNKNOWN':'PROVIDER_'.$code,$message);
    }
    public function read(array $account,string $id): array {
        if(!$this->capabilities($account)['postRead'])throw new CampaignException('READ_PERMISSION','Für den Beitragsabruf fehlen tatsächlich gewährte LinkedIn-Leseberechtigungen.');
        if(!$this->validPost($id))throw new CampaignException('POST_ID','Eine vom Provider gelieferte Share- oder UGC-Post-URN ist erforderlich.');
        $r=$this->request($account,'GET','/rest/posts/'.rawurlencode($id));$this->check($r,200);$post=$r['body'];
        if(!is_array($post)||($post['id']??null)!==$id||!is_string($post['commentary']??null))throw new CampaignException('INVALID_RESPONSE','LinkedIn hat keine verwendbaren Beitragsdaten geliefert.');
        preg_match('/^.{0,2000}/us',$post['commentary'],$excerpt);
        return ['external_post_id'=>$id,'external_post_urn'=>$id,'external_author_id'=>$post['author']??null,'post_excerpt'=>$excerpt[0]??'', 'published_at'=>isset($post['publishedAt'])?gmdate('Y-m-d H:i:s',(int)($post['publishedAt']/1000)):null];
    }
    public function comment(array $account,string $id,string $text): string {
        if(!$this->capabilities($account)['write']||empty($account['access_token_encrypted']))throw new CampaignException('WRITE_PERMISSION','Manuelle Veröffentlichung erforderlich: Verbindung oder Kommentarberechtigung fehlt.');
        if(!$this->validPost($id))throw new CampaignException('POST_ID','Manuelle Veröffentlichung erforderlich: gültige externe Post-URN fehlt.');
        if(trim($text)==='')throw new CampaignException('EMPTY_REPLY','Die Antwort darf nicht leer sein.');
        $actor=(string)($account['external_account_id']??'');
        if(!preg_match('/^[a-zA-Z0-9_-]+$/D',$actor))throw new CampaignException('ACTOR','Keine gültige Mitgliedsidentität vorhanden.');
        $r=$this->request($account,'POST','/rest/socialActions/'.rawurlencode($id).'/comments',['actor'=>'urn:li:person:'.$actor,'object'=>$id,'message'=>['text'=>$text]]);
        $this->check($r,201);$comment=$r['id']??$r['body']['id']??null;
        if(!is_scalar($comment)||trim((string)$comment)===''||strlen((string)$comment)>500)throw new CampaignException('DELIVERY_UNKNOWN','LinkedIn hat den Kommentar angenommen, aber keine verwendbare ID geliefert. Originalbeitrag prüfen; nicht blind erneut senden.');
        return (string)$comment;
    }
}
