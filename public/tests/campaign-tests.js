import {newCampaign,activeTargets,setBase,selectItem,resetReply,selectionWarning,itemStatus} from "../js/campaign/core.js";
import {createCampaignUI} from "../js/campaign/ui.js";
const assert=(condition,message)=>{if(!condition)throw new Error(message);};
export const campaignTests=[
 ["Kampagne Mehrfachauswahl und Basis",()=>{const c=newCampaign();setBase(c,"Basis {{einladung}}");selectItem(c,{id:1,provider_id:"linkedin"},true);selectItem(c,{id:2,provider_id:"facebook"},true);selectItem(c,{id:1,provider_id:"linkedin"},true);assert(c.targets.length===2&&c.targets[0].reply_text==="Basis {{einladung}}","Auswahl/Basis");selectItem(c,{id:1},false);assert(c.targets.length===1,"Abwahl");}],
 ["Kampagne Custom bleibt bei Basisänderung",()=>{const c=newCampaign();selectItem(c,{id:1,provider_id:"linkedin"},true);const t=c.targets[0];t.reply_text="Custom";t.reply_is_customized=true;setBase(c,"Neu");assert(t.reply_text==="Custom","Custom überschrieben");resetReply(c,t);assert(t.reply_text==="Neu"&&!t.reply_is_customized,"Reset");}],
 ["Kampagne Plattform und Ziel deaktivieren ohne Verlust",()=>{const c=newCampaign();selectItem(c,{id:1,provider_id:"linkedin"},true);selectItem(c,{id:2,provider_id:"facebook"},true);c.platforms.linkedin=false;c.targets[1].enabled=false;assert(activeTargets(c).length===0&&c.targets.length===2,"Deaktivierung");c.platforms.linkedin=true;assert(activeTargets(c).length===1,"Reaktivierung");}],
 ["Kampagne Warnschwellen und Statusfilter",()=>{assert(selectionWarning(4,{warning:5,strong:10})==="","zu früh");assert(selectionWarning(5,{warning:5,strong:10}).includes("ähnliche"),"Warnung");assert(selectionWarning(10,{warning:5,strong:10}).startsWith("Achtung"),"starke Warnung");const c=newCampaign(),i={id:1,status:"new"};selectItem(c,i,true);assert(itemStatus(c,i)==="selected","Auswahlstatus");c.targets[0].reply_is_customized=true;assert(itemStatus(c,i)==="edited","Bearbeitungsstatus");}],
 ["Kampagne UI Auswahl, Baustein, Overlay, Freigabe, XSS",async()=>{
   const root=document.createElement("div");document.body.append(root);const originalFetch=window.fetch;const requests=[];
   const item={id:1,provider_id:"linkedin",author_display_name:"<img src=x>",external_post_url:"https://example.test/post",post_excerpt:"<script>alert(1)</script>",published_at:null,relevance_score:null,status:"new"};
   let saved=null;
   window.fetch=async(url,options={})=>{requests.push([url,options]);let data={};
     if(url==="/api/campaigns"&&(!options.method||options.method==="GET"))data={campaigns:[],accounts:[],warnings:{warning:5,strong:10},providers:[{id:"linkedin",name:"LinkedIn"},{id:"facebook",name:"Facebook"}]};
     else if(url==="/api/engagement-items")data={items:[item]};
     else if(url==="/api/text-blocks")data={textBlocks:[{id:1,title:"Einladung",key:"einladung",isActive:true}]};
     else if(url==="/api/campaigns"&&options.method==="POST"){saved={...JSON.parse(options.body),id:1,revision:1};saved.targets=saved.targets.map(t=>({...t,id:2,publishable:false}));data={campaign:saved};}
     else if(url==="/api/campaigns/1/review")data={review:{campaign_id:1,name:saved.name,targets:saved.targets,active:1,disabled:0,warnings:[],token:"token"}};
     else throw new Error(`Unexpected request ${url}`);
     return {ok:true,json:async()=>({ok:true,data})};
   };
   const ui=createCampaignUI({root,csrf:()=>"csrf",notify:()=>{}});
   const tick=()=>new Promise(resolve=>setTimeout(resolve,0));
   try{
     await ui.load();assert(root.textContent.includes("Relevante Beiträge"),"Feed fehlt");assert(root.textContent.includes("LinkedIn-Beiträge können derzeit nicht automatisch eingelesen werden"),"Scope-Hinweis fehlt");
     root.querySelector(".campaign-feed-card input").click();assert(root.textContent.includes("1 Beiträge ausgewählt"),"Auswahl");
     const base=root.querySelector('[aria-label="Basis-Antworttext"]');base.value="Hallo ";base.setSelectionRange(6,6);base.dispatchEvent(new Event("input"));base.focus();root.querySelector(".post-text-block").click();assert(base.value==="Hallo {{einladung}}","Baustein");
     const answer=root.querySelector(".campaign-reply textarea");answer.value="Individuell";answer.dispatchEvent(new Event("input"));base.value="Neue Basis";base.dispatchEvent(new Event("input"));assert(root.querySelector(".campaign-reply textarea").value==="Individuell","Custom überschrieben");
     const expand=[...root.querySelectorAll("button")].find(b=>b.getAttribute("aria-label")==="Antwort vergrößern");expand.click();await tick();const enlarged=root.querySelector("dialog[open] textarea");enlarged.value="Overlay-Änderung";enlarged.dispatchEvent(new Event("input"));[...root.querySelectorAll("dialog[open] button")].find(b=>b.getAttribute("aria-label")==="Schließen").click();await tick();assert(root.querySelector(".campaign-reply textarea").value==="Overlay-Änderung","Overlay State");
     assert(!root.querySelector("img,script"),"Fremdinhalte als HTML ausgeführt");assert(!requests.some(([u])=>u.endsWith("/publish")),"Ungefragter Versand");
     [...root.querySelectorAll("button")].find(b=>b.textContent==="Abschlussübersicht prüfen").click();await tick();await tick();assert(root.querySelector(".campaign-review").open,"Review nicht geöffnet");assert(root.querySelector(".campaign-review").textContent.includes("Manuelle Veröffentlichung erforderlich"),"Fallback fehlt");
     const write=requests.find(([u,o])=>u==="/api/campaigns"&&o.method==="POST");assert(write[1].headers["X-CSRF-Token"]==="csrf","CSRF fehlt");
   }finally{ui.reset();root.remove();window.fetch=originalFetch;}
 }]
];
