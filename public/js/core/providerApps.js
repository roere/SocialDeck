import {renderLinkedInOAuthResult} from './linkedinOAuthResult.js';
const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
const labels={posting:'Persönlich posten',eventsRead:'Events lesen',eventsWrite:'Events verwalten',comments:'Kommentare schreiben',feed:'Feed lesen',postRead:'Bekannte Posts lesen',organizationDiscovery:'Unternehmensseiten finden',organizationPublishing:'Unternehmensseiten veröffentlichen'};
export function providerAppForm(app={},onSave,onCancel){
  const form=el('form',undefined,'provider-form provider-config-form');form.dataset.appForm='true';
  for(const [name,title,type,max] of [['name','Name','text',190],['clientId','Client ID','text',255],['clientSecret','Client Secret','password',4096],['redirectUri','Redirect URI','url',2048],['scopes','Konfigurierte Scopes','textarea',4000]]){
    const label=el('label',title),input=el(type==='textarea'?'textarea':'input');input.name=name;if(type!=='textarea')input.type=type;else input.rows=3;input.maxLength=max;input.value=name==='clientSecret'?'':app[name]||'';
    if(name==='name')input.required=true;if(name==='clientSecret'){input.autocomplete='new-password';input.placeholder=app.hasClientSecret?'Secret gespeichert · leer lassen zum Beibehalten':'Noch kein Secret gespeichert';}
    label.append(input);form.append(label);
  }
  const enabled=el('input');enabled.type='checkbox';enabled.name='enabled';enabled.checked=!!app.enabled;const active=el('label','Aktiviert');active.append(enabled);form.append(active);
  const purpose=el('select');purpose.name='purpose';for(const [v,t] of [['','Kein Zweck'],['posting','Posting'],['community','Community'],['events','Events'],['advertising','Advertising'],['custom','Custom']]){const o=el('option',t);o.value=v;purpose.append(o);}purpose.value=app.purpose||'';const pl=el('label','Zweck (Beschreibung)');pl.append(purpose);form.append(pl);
  const buttons=el('div',undefined,'button-row'),save=el('button','Speichern','button button-primary'),cancel=el('button','Abbrechen','button button-secondary');save.type='submit';cancel.type='button';cancel.addEventListener('click',onCancel);buttons.append(save,cancel);form.append(buttons);
  form.addEventListener('submit',async e=>{e.preventDefault();save.disabled=true;try{const values=Object.fromEntries(new FormData(form));values.enabled=enabled.checked;values.purpose=purpose.value||null;await onSave(values);}finally{save.disabled=false;}});return form;
}
export function providerAppsCard(config,{open=false,onToggle=()=>{},save,connect,disconnect,remove,sync,report=()=>{}}){
  const details=el('details',undefined,'provider-config');details.dataset.provider=config.id;details.open=open;details.addEventListener('toggle',()=>onToggle(details.open));const summary=el('summary'),heading=el('span');heading.append(el('strong',config.name),el('small','Apps'));summary.append(heading,el('span','+','accordion-mark'));details.append(summary);
  const content=el('div',undefined,'provider-form provider-config-form');details.append(content);
  const action=(text,fn)=>{const b=el('button',text,'button button-secondary');b.type='button';b.addEventListener('click',async()=>{b.disabled=true;try{await fn();}catch(e){report(e.message,'error');}finally{b.disabled=false;}});return b;};
  const matrix=el('section',undefined,'account-placeholder');matrix.append(el('strong','Verfügbare Funktionen'));
  for(const [cap,label] of Object.entries(labels)){const c=config.capabilityMatrix?.[cap];matrix.append(el('span',`${label}: ${c?.available?'JA · bereitgestellt durch: '+c.appName:'NEIN · keine verbundene App mit benötigtem Scope'}`));}
  matrix.append(el('p','Event-Rechte werden angezeigt; eine Event-Oberfläche ist noch nicht vorhanden.','field-hint'));content.append(matrix);
  if(config.oauthResult?.result==='failed'&&!config.oauthResult.providerAppId){const notice=renderLinkedInOAuthResult(config.oauthResult);if(notice)content.append(notice);}
  const editor=el('div');let editing=null;
  const edit=(app={})=>{if(editing)editing.remove();editing=providerAppForm(app,async values=>{try{await save(app.id,values);editing?.remove();}catch(e){report(e.message,'error');}},()=>{editing?.remove();editing=null;});editor.replaceChildren(editing);editing.querySelector('input').focus();};
  for(const app of config.apps||[]){
    const card=el('section',undefined,'account-placeholder provider-app-card');card.dataset.appId=String(app.id);const accounts=(config.accounts||[]).filter(a=>a.providerAppId===app.id);
    const connected=accounts.some(a=>a.status==='connected'&&(!a.tokenExpiresAt||Date.parse(a.tokenExpiresAt.replace(' ','T'))>Date.now()));
    card.append(el('strong',app.name),el('span',`Status: ${!app.enabled?'Deaktiviert':app.oauthResult?.result==='failed'&&app.oauthResult.existingConnection?'Vorhandene Verbindung – Gültigkeit noch nicht bestätigt':connected?'Verbunden':'Nicht verbunden'}`),el('span',`Zweck: ${app.purpose||'nicht angegeben'}`),el('span',`Konfigurierte Scopes: ${app.scopes||'keine'}`));
    const notice=renderLinkedInOAuthResult(app.oauthResult);if(notice)card.append(notice);
    for(const account of accounts){card.append(el('span',`${account.displayName} · ${account.status}`),el('span',`Tatsächlich gewährte Scopes: ${account.scopes||'nicht angegeben'}`));if(account.status!=='disconnected')card.append(action(`Verbindung trennen · ${account.displayName}`,()=>disconnect(app.id,account.id)));}
    for(const channel of (config.channels||[]).filter(c=>accounts.some(a=>a.id===c.socialAccountId)))card.append(el('span',`${channel.displayName} · ${channel.channelType} · ${channel.status}`));
    const row=el('div',undefined,'button-row');row.append(action('Bearbeiten',()=>edit(app)),action(connected?'Neu verbinden':'Verbinden',()=>connect(app.id)),action('Löschen',()=>remove(app.id)));
    if(accounts.length)row.append(action('LinkedIn-Kanäle aktualisieren',()=>sync(app.id)));card.append(row);content.append(card);
  }
  content.append(action(`${config.name}-App hinzufügen`,()=>edit()),editor);return details;
}
