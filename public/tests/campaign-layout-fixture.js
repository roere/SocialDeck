
import {createCampaignUI} from '../js/campaign/ui.js';
window.layoutErrors=[];addEventListener('error',e=>layoutErrors.push(e.message));addEventListener('unhandledrejection',e=>layoutErrors.push(String(e.reason)));
const providers=[{id:'linkedin',name:'LinkedIn'},{id:'instagram',name:'Instagram'},{id:'facebook',name:'Facebook'}];
const items=providers.map((p,i)=>({id:i+1,provider_id:p.id,author_display_name:`Demo-Konto ${i+1}`,external_post_url:`https://example.test/post/${i+1}`,post_excerpt:'Unser nächstes Treffen steht an. Welche Themen interessieren euch für den gemeinsamen Austausch?',published_at:'2026-09-01 10:00:00',relevance_score:null,relevance_reason:'Manuell hinzugefügt',status:'new'}));
let campaign={id:1,revision:1,name:'Austausch September',base_reply_text:'Danke für den Einblick. Wir freuen uns auf den Austausch! {{einladung}}',platforms:{},status:'draft',created_at:'2026-09-01 10:00:00',targets:items.slice(0,2).map(i=>({...i,id:i.id+10,engagement_item_id:i.id,post_published_at:i.published_at,enabled:true,reply_text:'Danke für den Einblick. Wir freuen uns auf den Austausch!',reply_is_customized:false,status:'ready',publishable:false}))};
window.fetch=async(url,options={})=>{let data;
 if(url==='/api/campaigns')data={campaigns:[campaign],accounts:[],providers,warnings:{warning:5,strong:10}};
 else if(url==='/api/campaigns/1') {if(options.method==='PUT')campaign=JSON.parse(options.body);data={campaign};}
 else if(url==='/api/engagement-items')data={items};
 else if(url==='/api/text-blocks')data={textBlocks:[{id:1,title:'Einladung',key:'einladung',category:'Veranstaltungen',isActive:true}]};
 else if(url==='/api/campaigns/1/review')data={review:{campaign_id:1,name:campaign.name,targets:campaign.targets,active:2,disabled:0,warnings:['Mehrere Antworten sind identisch oder nahezu identisch.'],token:'mock'}};
 else throw new Error(`Unexpected test request ${url}`);
 return {ok:true,json:async()=>({ok:true,data})};
};
const ui=createCampaignUI({root:document.querySelector('#campaign-root'),csrf:()=> 'mock',notify:()=>{}});
await ui.load();document.querySelector('.history-item button').click();await new Promise(r=>setTimeout(r,0));window.layoutReady=true;
if(location.search==='?overlay')document.querySelector('[aria-label="Antwort vergrößern"]').click();
if(location.search==='?answers')document.querySelector('.campaign-targets').scrollIntoView();
