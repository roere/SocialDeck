export function newCampaign() { return {name:"Neue Kampagne",base_reply_text:"",platforms:{},targets:[],status:"draft"}; }
export function activeTargets(campaign) { return campaign.targets.filter(t=>t.enabled&&campaign.platforms[t.provider_id]!==false); }
export function setBase(campaign,text) {
  campaign.base_reply_text=text;
  for(const t of campaign.targets)if(!t.reply_is_customized&&!["published","publishing"].includes(t.status))t.reply_text=text;
}
export function selectItem(campaign,item,selected) {
  const existing=campaign.targets.find(t=>Number(t.engagement_item_id)===Number(item.id));
  if(selected&&!existing)campaign.targets.push({...item,id:undefined,engagement_item_id:Number(item.id),post_published_at:item.published_at,enabled:true,reply_text:campaign.base_reply_text,reply_is_customized:false,status:"draft"});
  if(!selected&&existing&&!["published","publishing"].includes(existing.status))campaign.targets=campaign.targets.filter(t=>t!==existing);
}
export function resetReply(campaign,target) { if(!["published","publishing"].includes(target.status)){target.reply_text=campaign.base_reply_text;target.reply_is_customized=false;} }
export function selectionWarning(count,config) {
  if(count>=config.strong)return "Achtung: Eine große Zahl ähnlicher Interaktionen kann zu Reichweiteneinschränkungen oder Kontobeschränkungen führen. Prüfe die Antworten individuell.";
  if(count>=config.warning)return "Du hast mehrere Beiträge ausgewählt. Viele ähnliche Kommentare oder Interaktionen in kurzer Zeit können von sozialen Netzwerken als automatisiertes Verhalten bewertet werden.";
  return "";
}
export function itemStatus(campaign,item) {
  if(item.status==="commented")return "commented";
  const target=campaign.targets.find(t=>Number(t.engagement_item_id)===Number(item.id));
  return target?(target.status==="published"?"commented":target.reply_is_customized?"edited":"selected"):item.status;
}
