<?php
declare(strict_types=1);

final class CampaignException extends RuntimeException {
    public function __construct(public readonly string $errorCode, string $message, public readonly int $httpStatus=422) { parent::__construct($message); }
}
function campaignWarningConfig(): array {
    $warning=max(2,(int)envValue('CAMPAIGN_WARNING_TARGETS','5'));
    return ['warning'=>$warning,'strong'=>max($warning+1,(int)envValue('CAMPAIGN_STRONG_WARNING_TARGETS','10'))];
}
function campaignActive(array $target,array $platforms): bool {
    return (bool)$target['enabled'] && ($platforms[$target['provider_id']]??true);
}
function campaignStatus(array $targets,array $platforms): string {
    $published=count(array_filter($targets,fn($t)=>$t['status']==='published'));
    $pending=array_filter($targets,fn($t)=>campaignActive($t,$platforms)&&$t['status']!=='published');
    if($published)return $pending?'partially_published':'published';
    return $pending && !array_filter($pending,fn($t)=>trim($t['reply_text'])==='')?'ready':'draft';
}
function campaignWarnings(array $targets,array $config): array {
    $n=count($targets);$messages=[];
    if($n>=$config['strong'])$messages[]='Achtung: Eine große Zahl ähnlicher Interaktionen kann zu Reichweiteneinschränkungen oder Kontobeschränkungen führen. Prüfe die Antworten individuell.';
    elseif($n>=$config['warning'])$messages[]='Viele ähnliche Kommentare oder Interaktionen in kurzer Zeit können von sozialen Netzwerken als automatisiertes Verhalten bewertet werden.';
    if($n>1)$messages[]="Du veröffentlichst $n individuelle Antworten. Prüfe bitte, dass die Kommentare inhaltlich zu den jeweiligen Beiträgen passen.";
    $seen=[];foreach($targets as $target){$normalized=preg_replace('/[\p{P}\p{Z}\s]+/u','',strtolower($target['reply_text']));if($normalized!==''&&isset($seen[$normalized])){$messages[]='Mehrere Antworten sind identisch oder nahezu identisch.';break;}$seen[$normalized]=true;}
    return $messages;
}
function campaignMergeReply(string $base,array $incoming,?array $existing): array {
    if($existing && in_array($existing['status'],['published','publishing'],true))return $existing;
    $custom=($incoming['reply_is_customized']??false)===true;
    return ['reply_text'=>$custom?campaignString($incoming,'reply_text',50000,false):$base,'reply_is_customized'=>$custom];
}
function campaignString(array $data,string $key,int $limit,bool $trim=true): string {
    $value=$data[$key]??'';
    if(!is_string($value)||strlen($value)>$limit)throw new CampaignException('INVALID_INPUT',"Ungültiges Feld: $key.");
    return $trim?trim($value):$value;
}
function campaignUrl(string $url): string {
    if(!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https'||parse_url($url,PHP_URL_USER)!==null||parse_url($url,PHP_URL_PASS)!==null)throw new CampaignException('INVALID_URL','Bitte eine vollständige HTTPS-Beitragsadresse angeben.');
    return $url;
}
