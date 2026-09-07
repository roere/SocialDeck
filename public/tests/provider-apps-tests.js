import {providerAppForm,providerAppsCard} from '../js/core/providerApps.js';
const assert=(v,m)=>{if(!v)throw new Error(m);};
const app=(id,name)=>({id,providerId:'linkedin',name,enabled:true,scopes:'openid profile',clientId:'fixture-'+id,hasClientSecret:true,redirectUri:'https://example.test/callback',purpose:'custom'});
export const providerAppsTests=[
 ['Provider Apps Formular erhält Secret und sendet eigene Felder',async()=>{
   let saved;const form=providerAppForm(app(1,'Posting'),async values=>saved=values,()=>{});document.body.append(form);
   try{assert(form.elements.clientSecret.value==='','Secret vorbelegt');form.elements.name.value='Community';form.elements.scopes.value='openid profile w_member_social_feed';form.dispatchEvent(new Event('submit',{cancelable:true}));await Promise.resolve();assert(saved.name==='Community'&&saved.clientSecret===''&&saved.enabled===true,'Formularwerte');assert(saved.scopes.includes('w_member_social_feed'),'Scopes verloren');}finally{form.remove();}
 }],
 ['Provider Apps Aktionen und Fehler bleiben pro App isoliert',async()=>{
   const a=app(1,'Posting'),b=app(2,'<img src=x>');b.oauthResult={result:'failed',providerAppId:2,appName:b.name,scopeSnapshotAvailable:true,requestedScopes:['openid','profile'],oauthErrorCategory:'scope',existingConnection:true};let selected;
   const card=providerAppsCard({id:'linkedin',name:'LinkedIn',apps:[a,b],accounts:[{id:11,providerAppId:1,status:'connected',displayName:'Test A',scopes:'w_member_social'},{id:22,providerAppId:2,status:'connected',displayName:'Test B',scopes:'w_member_social_feed'}],capabilityMatrix:{posting:{available:true,appName:'Posting'}}},{connect:async id=>selected=id,save:async()=>{},remove:async()=>{},disconnect:async()=>{},sync:async()=>{}});
   assert(card.querySelectorAll('.provider-app-card').length===2,'App-Karten fehlen');assert(!card.querySelector('[data-app-id="1"] .linkedin-oauth-result'),'Fehler B an A');assert(card.querySelector('[data-app-id="2"] .linkedin-oauth-result'),'App-Fehler fehlt');assert(!card.querySelector('img'),'App-Name als HTML');
   const button=[...card.querySelector('[data-app-id="2"]').querySelectorAll('button')].find(b=>b.textContent==='Neu verbinden');button.click();await Promise.resolve();assert(selected===2,'falsche App verbunden');assert(card.textContent.includes('bereitgestellt durch: Posting'),'Matrix fehlt');
 }],
 ['Provider Apps Layout bleibt bei schmaler Breite bedienbar',()=>{const card=providerAppsCard({id:'linkedin',name:'LinkedIn',apps:[app(4,'Community')],accounts:[]},{open:true,save:async()=>{},connect:async()=>{},disconnect:async()=>{},remove:async()=>{},sync:async()=>{}});document.body.append(card);try{card.style.width='280px';assert(card.scrollWidth<=card.clientWidth+1,'App-Karte läuft horizontal über');[...card.querySelectorAll('button')].find(b=>b.textContent==='Bearbeiten').click();assert(card.querySelector('form'),'Editor fehlt');assert(card.scrollWidth<=card.clientWidth+1,'App-Form läuft horizontal über');}finally{card.remove();}}],
 ['Provider Apps Abbrechen verändert keine gespeicherte App',()=>{let saved=false,cancelled=false;const form=providerAppForm(app(3,'Draft'),()=>saved=true,()=>cancelled=true);[...form.querySelectorAll('button')].find(b=>b.textContent==='Abbrechen').click();assert(cancelled&&!saved,'Abbrechen speichert');}]
];
