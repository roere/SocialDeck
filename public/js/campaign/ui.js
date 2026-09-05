import {newCampaign,activeTargets,setBase,selectItem,resetReply,selectionWarning,itemStatus} from "./core.js";
import {createCampaignApi} from "./api.js";
import {api} from "../core/api.js";
import {createTextBlockReferenceButton,insertPlaceholderAtSelection} from "../core/textBlocks.js";
import {createVariantOverlayController} from "../core/variantOverlay.js";
const labels={draft:"Entwurf",ready:"Bereit",published:"Veröffentlicht",partially_published:"Teilweise veröffentlicht",failed:"Fehlgeschlagen",disabled:"Deaktiviert",publishing:"Versandstatus ungeklärt",cancelled:"Abgebrochen",new:"Neu",selected:"Ausgewählt",edited:"Bearbeitet",commented:"Kommentiert",ignored:"Ignoriert"};
const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
const label=(text,input)=>{const n=el("label",text);n.append(input);return n;};
const date=value=>value?new Date(value.replace(" ","T")+(/Z$/.test(value)?"":"Z")).toLocaleString("de-DE"):"Datum nicht verfügbar";
const link=url=>{const a=el("a","Originalbeitrag öffnen");try{const u=new URL(url);if(u.protocol!=="https:")return el("span","Kein Original-Link verfügbar");a.href=u.href;}catch{return el("span","Kein Original-Link verfügbar");}a.target="_blank";a.rel="noopener noreferrer";return a;};
export function createCampaignUI({root,csrf,notify}) {
  const client=createCampaignApi(csrf);let campaign=newCampaign(),items=[],campaigns=[],accounts=[],providers=[],blocks=[],config={warning:5,strong:10},dirty=false,loaded=false,busy=false,activeTextarea=null;
  let platformFilter="",statusFilter="",keyword="",relevance="";
  const shell=el("fieldset",undefined,"post-editor campaign-shell"),error=el("p",undefined,"form-error"),summary=el("section",undefined,"panel editor-panel"),feed=el("section",undefined,"panel editor-panel"),selection=el("section",undefined,"campaign-selection"),basePanel=el("section",undefined,"panel editor-panel"),targets=el("section",undefined,"campaign-targets"),footer=el("section",undefined,"panel publish-bar");
  error.setAttribute("role","alert");root.append(error,shell);shell.append(summary,feed,basePanel,targets,footer);
  const dialog=el("dialog",undefined,"platform-variant-dialog campaign-overlay"),dialogTitle=el("h2","Kampagnenantwort"),dialogContent=el("div",undefined,"platform-variant-dialog-content"),dialogShell=el("div",undefined,"platform-variant-dialog-shell"),dialogHeader=el("header",undefined,"platform-variant-dialog-header");dialog.setAttribute("aria-label","Kampagnenantwort vergrößert");root.append(dialog);dialog.append(dialogShell);dialogShell.append(dialogHeader,dialogContent);dialogHeader.append(dialogTitle);
  const overlay=createVariantOverlayController({dialog,content:dialogContent,title:dialogTitle});
  const reviewDialog=el("dialog",undefined,"publish-confirmation campaign-review"),reviewBody=el("div");reviewDialog.setAttribute("aria-label","Abschlussübersicht und Freigabe");reviewDialog.append(reviewBody);root.append(reviewDialog);
  const manualDialog=el("dialog",undefined,"publish-confirmation campaign-manual");manualDialog.setAttribute("aria-label","Beitrag hinzufügen");root.append(manualDialog);
  const run=async action=>{if(busy)return;busy=true;shell.disabled=true;error.textContent="";try{await action();}catch(e){error.textContent=e.message;const openDialog=root.querySelector("dialog[open]");if(openDialog){let message=openDialog.querySelector(".campaign-dialog-error");if(!message){message=el("p",undefined,"form-error campaign-dialog-error");message.setAttribute("role","alert");openDialog.prepend(message);}message.textContent=e.message;}notify(e.message,"error");}finally{busy=false;shell.disabled=false;}};
  const button=(text,fn,kind="secondary")=>{const b=el("button",text,`button button-${kind}`);b.type="button";b.addEventListener("click",()=>run(fn));return b;};
  const dialogActions=el("div",undefined,"button-row");
  for(const [text,glyph] of [["Verkleinern","↙"],["Schließen","×"]]){const b=button(text,()=>overlay.close());b.className="icon-button";b.title=text;b.setAttribute("aria-label",text);b.textContent=glyph;dialogActions.append(b);}dialogHeader.append(dialogActions);
  const heading=(text,...actions)=>{const h=el("div",undefined,"panel-heading compact");h.append(el("h2",text));if(actions.length){const row=el("div",undefined,"button-row");row.append(...actions);h.append(row);}return h;};
  function groupActions(container){const buttons=[...container.children].filter(n=>n.matches("button"));if(buttons.length){const row=el("div",undefined,"button-row");row.append(...buttons);container.append(row);}}
  const badge=text=>el("span",text,"status-badge");
  function change(){dirty=true;renderSelection();renderFooter();}
  function checkbox(text,checked,onChange,disabled=false){const input=el("input");input.type="checkbox";input.checked=checked;input.disabled=disabled;input.addEventListener("change",()=>onChange(input.checked));const l=label(text,input);l.className="email-enabled";l.prepend(input);return l;}
  function original(item){const box=el("section",undefined,"campaign-original");box.append(el("strong",`${providers.find(p=>p.id===item.provider_id)?.name||item.provider_id} · ${item.author_display_name||"Unbekannter Autor"}`),el("p",date(Object.hasOwn(item,"post_published_at")?item.post_published_at:item.published_at),"field-hint"),el("p",item.post_excerpt||"Kein Beitragsauszug vorhanden.","campaign-excerpt"),link(item.external_post_url));return box;}
  function select(options,value,onChange){const s=el("select");for(const [id,name] of options){const o=el("option",name);o.value=id;s.append(o);}s.value=value;s.addEventListener("change",()=>onChange(s.value));return s;}
  async function reloadList(){const result=await client.list();campaigns=result.campaigns;accounts=result.accounts;config=result.warnings;providers=result.providers;}
  async function save(){const result=await client.save(campaign);campaign=result.campaign;dirty=false;await reloadList();render();notify("Kampagne gespeichert.");}
  function renderSummary(){
    summary.replaceChildren(heading("Kampagnenübersicht"));
    summary.querySelector(".panel-heading").append(button("Neue Kampagne",()=>{if(dirty&&!confirm("Ungespeicherte Änderungen verwerfen?"))return;campaign=newCampaign();dirty=false;render();}));
    for(const c of campaigns){const active=activeTargets(c),published=c.targets.filter(t=>t.status==="published").length,failed=c.targets.filter(t=>t.status==="failed").length;
      const row=el("article",undefined,"history-item"),meta=el("div");meta.append(el("strong",c.name),el("p",`${date(c.created_at)} · ${c.targets.length} Ziele · ${active.length} aktiv · ${published} veröffentlicht · ${failed} fehlgeschlagen · ${c.targets.length-active.length} deaktiviert`));row.append(meta,badge(labels[c.status]),button("Kampagne laden",async()=>{if(dirty&&!confirm("Ungespeicherte Änderungen verwerfen?"))return;campaign=(await client.load(c.id)).campaign;dirty=false;render();}));summary.append(row);
    }
  }
  function renderFeed(){
    feed.replaceChildren(heading("Relevante Beiträge",button("Beitrag hinzufügen",showManual)));const access=el("details",undefined,"campaign-access");access.append(el("summary","Datenquellen und Berechtigungen"));feed.append(access);
    const linked=accounts.filter(a=>a.provider_id==="linkedin");
    if(!linked.some(a=>a.capabilities.read)){access.append(el("p","LinkedIn-Beiträge können derzeit nicht automatisch eingelesen werden."),el("p","Für den automatischen Abruf ist eine zusätzliche LinkedIn-Berechtigung erforderlich."));}
    access.append(el("p","V1 sammelt manuell hinzugefügte Beiträge. Bekannte LinkedIn-Posts lassen sich mit gewährtem API-Zugriff einzeln aktualisieren. Ein allgemeiner Netzwerkfeed ist nicht verfügbar.","field-hint"));
    for(const p of providers){const connected=accounts.filter(a=>a.provider_id===p.id);access.append(el("p",`${p.name}: ${connected.length?connected.map(a=>`${a.display_name||p.name} – Beiträge lesen: ${a.capabilities.postRead?"Ja (bekannte Posts)":"Berechtigung fehlt / nicht verfügbar"}; Kommentare schreiben: ${a.capabilities.write?"Ja":"manuell"}`).join(" · "):"Kein verbundenes Konto; manueller Fallback verfügbar."}`,"field-hint"));}
    const filters=el("div",undefined,"campaign-filters");
    filters.append(label("Plattform",select([["","Alle"],...providers.map(p=>[p.id,p.name])],platformFilter,v=>{platformFilter=v;renderFeed();})),label("Status",select([["","Alle"],...["new","selected","edited","commented","ignored"].map(s=>[s,labels[s]])],statusFilter,v=>{statusFilter=v;renderFeed();})),label("Relevanz",select([["","Alle"],["high","Hoch"],["medium","Mittel"],["low","Niedrig"]],relevance,v=>{relevance=v;renderFeed();})));
    const search=el("input");search.type="search";search.value=keyword;search.placeholder="Auszug / Autor durchsuchen";search.addEventListener("input",()=>{keyword=search.value;renderItems(list);});filters.append(label("Suchwort",search));feed.append(filters,selection);const list=el("div",undefined,"posts-list campaign-feed-list");feed.append(list);renderItems(list);
  }
  function renderItems(list){
    const found=items.filter(i=>(!platformFilter||i.provider_id===platformFilter)&&(!statusFilter||itemStatus(campaign,i)===statusFilter)&&(!keyword||`${i.post_excerpt} ${i.author_display_name}`.toLocaleLowerCase("de").includes(keyword.toLocaleLowerCase("de")))&&(!relevance||(i.relevance_score!==null&&(relevance==="high"?i.relevance_score>=70:relevance==="medium"?i.relevance_score>=40&&i.relevance_score<70:i.relevance_score<40))));
    list.replaceChildren();if(!found.length)list.append(el("p","Keine passenden Beiträge. Füge einen Beitrag manuell hinzu."));
    for(const item of found){const card=el("article",undefined,"post-list-item campaign-feed-card"),t=campaign.targets.find(t=>Number(t.engagement_item_id)===Number(item.id));
      card.append(checkbox("Beitrag auswählen",Boolean(t),checked=>{selectItem(campaign,item,checked);change();renderTargets();renderItems(list);},t&&["published","publishing"].includes(t.status)),original(item),el("p",`Relevanz: ${item.relevance_score===null?"Noch nicht bewertet":item.relevance_score+" / 100"} · ${item.relevance_reason||""}`,"field-hint"),badge(labels[itemStatus(campaign,item)]));
      card.append(button(item.status==="ignored"?"Wieder berücksichtigen":"Ignorieren",async()=>{await client.ignore(item.id,item.status==="ignored"?"new":"ignored");items=(await client.items()).items;renderFeed();}));
      const account=accounts.find(a=>a.id===Number(item.social_account_id));
      if(account?.capabilities.postRead&&item.external_post_urn)card.append(button("Beitrag über API aktualisieren",async()=>{await client.read(item.id);items=(await client.items()).items;renderFeed();}));
      const meta=el("div",undefined,"campaign-feed-meta");meta.append(card.querySelector(":scope > .field-hint"),card.querySelector(":scope > .status-badge"));card.append(meta);groupActions(card);list.append(card);
    }
  }
  function renderSelection(){
    selection.replaceChildren(el("strong",`${campaign.targets.length} Beiträge ausgewählt · ${activeTargets(campaign).length} aktiv`,"count-badge"));
    const warning=selectionWarning(campaign.targets.length,config);if(warning){const w=el("p",warning,"campaign-warning");w.setAttribute("role","alert");selection.append(w,el("small",`SocialDeck-UX-Warnwerte: ${config.warning} / ${config.strong} Ziele. Keine Plattformlimits.`));}
  }
  function renderBase(){
    basePanel.replaceChildren(heading("Kampagnenantwort"));const name=el("input");name.value=campaign.name;name.maxLength=190;name.addEventListener("input",()=>{campaign.name=name.value;change();});
    const base=el("textarea");base.rows=8;base.value=campaign.base_reply_text;base.setAttribute("aria-label","Basis-Antworttext");base.addEventListener("focus",()=>activeTextarea=base);base.addEventListener("input",()=>{setBase(campaign,base.value);change();renderTargets();});activeTextarea=base;
    const refs=el("div",undefined,"post-text-block-list");for(const block of blocks)refs.append(createTextBlockReferenceButton(block,key=>{const text=activeTextarea?.isConnected&&!activeTextarea.disabled?activeTextarea:base;insertPlaceholderAtSelection(text,key);text.dispatchEvent(new Event("input",{bubbles:true}));},"post"));
    const snippets=el("section",undefined,"post-text-blocks");snippets.append(el("h3","Textbaustein einfügen"),refs);
    basePanel.append(label("Kampagnenname",name),label("Basis-Antworttext",base),snippets,el("p","Basisänderungen aktualisieren nur nicht individualisierte Antworten. Textbausteine werden für Vorschau und Freigabe aufgelöst.","field-hint"));
  }
  async function copy(t){const result=await api.resolveText(t.reply_text);await navigator.clipboard.writeText(result.text);notify("Kommentar kopiert. Veröffentlichung erfolgt im Originalbeitrag.");}
  function renderTargets(){
    overlay.close(false);targets.replaceChildren(heading("Konkrete Antworten"));
    const platforms=el("div",undefined,"provider-list campaign-platforms");for(const p of providers){const option=checkbox(p.name,campaign.platforms[p.id]!==false,checked=>{campaign.platforms[p.id]=checked;change();renderTargets();});option.className="provider-option";platforms.append(option);}targets.append(platforms);
    if(!campaign.targets.length)targets.append(el("p","Wähle oben einen oder mehrere Beiträge aus."));
    for(const t of campaign.targets){
      const card=el("article",undefined,"panel platform-variant campaign-target"),controls=el("section",undefined,"campaign-reply"),locked=["published","publishing"].includes(t.status),active=t.enabled&&campaign.platforms[t.provider_id]!==false;
      card.classList.toggle("campaign-disabled",!active);const expand=button("↗ Antwort vergrößern",()=>overlay.open(card,expand,`Antwort an ${t.author_display_name}`));
      expand.className="icon-button";expand.title="Antwort vergrößern";expand.setAttribute("aria-label","Antwort vergrößern");expand.textContent="↗";
      const text=el("textarea");text.rows=7;text.value=t.reply_text;text.disabled=locked;text.setAttribute("aria-label",`Antwort an ${t.author_display_name}`);text.addEventListener("focus",()=>activeTextarea=text);const customized=el("small",t.reply_is_customized?"Individuell angepasst":"Aus Basisantwort");
      text.addEventListener("input",()=>{t.reply_text=text.value;t.reply_is_customized=true;customized.textContent="Individuell angepasst";change();});
      const reset=button("Aus Basisantwort aktualisieren",()=>{if(t.reply_is_customized&&!confirm("Individuelle Antwort durch die Basisantwort ersetzen?"))return;resetReply(campaign,t);change();renderTargets();});reset.disabled=locked;
      controls.append(checkbox("Ziel aktiviert",t.enabled,checked=>{t.enabled=checked;change();renderTargets();},locked),el("p",`${labels[t.status]} · ${active?"Aktiv":"Deaktiviert"}`),customized,expand,label("Antworttext",text),reset,button("Kommentar kopieren",()=>copy(t)),button("Aufgelöste Vorschau",async()=>{const result=await api.resolveText(t.reply_text);preview.textContent=result.text;}));
      const preview=el("pre",undefined,"campaign-excerpt");controls.append(preview);
      if(t.status!=="published")controls.append(el("p",t.publishable===undefined?"Veröffentlichbarkeit wird beim Speichern geprüft.":t.publishable?"Direkte Veröffentlichung nach Abschlussfreigabe möglich.":"Manuelle Veröffentlichung erforderlich","field-hint"));
      if(t.error_message)controls.append(el("p",t.error_message,"form-error"));
      if(t.status==="failed"&&t.error_code!=="DELIVERY_UNKNOWN")controls.append(button("Erneut versuchen",()=>review(t.engagement_item_id)));
      if(t.status!=="published")controls.append(button("Manuelle Veröffentlichung bestätigen",async()=>{if(!active)throw new Error("Ziel zuerst aktivieren.");if(!confirm("Hast du diesen Kommentar bereits im Originalbeitrag veröffentlicht und dort überprüft?"))return;const itemId=t.engagement_item_id;if(dirty||!campaign.id)await save();const current=campaign.targets.find(x=>x.engagement_item_id===itemId);campaign=(await client.manual(campaign.id,current.id)).campaign;await reloadList();render();}));
      const head=el("div",undefined,"panel-heading compact"),identity=el("div"),actions=el("div",undefined,"variant-head-actions");identity.append(el("p",providers.find(p=>p.id===t.provider_id)?.name||t.provider_id,"section-kicker"),el("h3",t.author_display_name||"Unbekannter Autor"));const toggle=controls.querySelector("label");actions.append(badge(labels[t.status]),expand);head.append(toggle,identity,actions);const statusLine=controls.querySelector("p");statusLine.className="field-hint";groupActions(controls);card.append(head,original(t),controls);targets.append(card);
    }
  }
  function renderFooter(){footer.replaceChildren(heading("Veröffentlichung"),badge(labels[campaign.status]),el("p",dirty?"Ungespeicherte Änderungen":campaign.id?"Kampagne gespeichert":"Noch nicht gespeichert","field-hint"),button("Kampagne speichern",save),button("Abschlussübersicht prüfen",()=>review(),"primary"));
    if(campaign.id)footer.append(button("Kampagne löschen",async()=>{if(!confirm("Kampagne mit gespeicherten Antworten löschen? Bereits veröffentlichte Kommentare bleiben im Netzwerk bestehen."))return;await client.remove(campaign.id);campaign=newCampaign();dirty=false;await reloadList();render();},"quiet"));
    groupActions(footer);const row=footer.querySelector(".button-row:last-child"),primary=row.querySelector(".button-primary");if(primary)row.append(primary);
  }
  async function review(retryItem){
    if(dirty||!campaign.id)await save();const retry=retryItem?campaign.targets.find(t=>t.engagement_item_id===retryItem)?.id:null;
    const {review:r}=await client.review(campaign.id,retry);reviewBody.replaceChildren(el("h2",`Abschlussübersicht: ${r.name}`),el("p",`${r.active} aktive Ziele · ${r.disabled} deaktiviert · ${r.targets.length} Antworten in dieser Freigabe`));
    for(const p of providers)reviewBody.append(el("p",`${p.name}: ${r.targets.filter(t=>t.provider_id===p.id).length}`));
    for(const warning of r.warnings)reviewBody.append(el("p",warning,"campaign-warning"));
    reviewBody.append(el("small","SocialDeck-Warnwerte sind keine Plattformlimits. Die Freigabe gilt zehn Minuten."));
    for(const t of r.targets){const details=el("details");details.append(el("summary",`${t.provider_id} · ${t.author_display_name} · ${t.publishable?"API-Veröffentlichung":"Manuelle Veröffentlichung erforderlich"}`),original(t),el("pre",t.reply_text,"campaign-excerpt"));reviewBody.append(details);}
    const count=r.targets.filter(t=>t.publishable).length;const approval=el("input");approval.type="checkbox";const send=button(`${count} Antworten jetzt ausdrücklich freigeben und veröffentlichen`,async()=>{send.disabled=true;try{campaign=(await client.publish(r.campaign_id,r.token)).campaign;dirty=false;reviewDialog.close();await reloadList();render();notify("Verarbeitung abgeschlossen. Ergebnisse stehen bei den einzelnen Zielen.");}catch(e){reviewDialog.close();throw e;}},"primary");send.disabled=true;approval.addEventListener("change",()=>send.disabled=!approval.checked||count===0);
    if(count)reviewBody.append(label("Ich habe die konkreten Antworten geprüft und gebe deren Veröffentlichung frei.",approval),send);
    else reviewBody.append(el("p","Keine direkte API-Veröffentlichung verfügbar. Antworten kopieren und im jeweiligen Originalbeitrag veröffentlichen."));
    reviewBody.append(button("Zurück zu den Antworten",()=>reviewDialog.close()));groupActions(reviewBody);reviewDialog.showModal();
  }
  function showManual(){
    manualDialog.replaceChildren(el("h2","Beitrag hinzufügen"));const form=el("form"),provider=select(providers.map(p=>[p.id,p.name]),providers[0]?.id,()=>refreshAccounts()),account=el("select"),url=el("input"),author=el("input"),excerpt=el("textarea"),urn=el("input");url.type="url";url.required=true;url.placeholder="https://…";url.maxLength=2048;author.maxLength=255;excerpt.rows=4;excerpt.maxLength=2000;urn.maxLength=500;
    function refreshAccounts(){account.replaceChildren();const empty=el("option","Ohne Konto – manuell veröffentlichen");empty.value="";account.append(empty);for(const a of accounts.filter(a=>a.provider_id===provider.value&&a.enabled)){const o=el("option",a.display_name||a.provider_id);o.value=String(a.id);account.append(o);}}
    refreshAccounts();form.append(label("Plattform",provider),label("Verbundenes Konto (optional)",account),label("Post-URL",url),label("Autor (optional)",author),label("Kurzer Beitragsauszug / Notiz (optional)",excerpt));
    const advanced=el("details");advanced.append(el("summary","Externe Post-URN (optional)"),label("Nur eine vom Provider gelieferte URN",urn),el("p","Keine URN aus einer URL ableiten. Ohne gültige externe ID bleibt die Veröffentlichung manuell."));form.append(advanced,el("p","Die URL wird gespeichert, nicht ausgelesen. Kein Scraping."));const submit=el("button","Beitrag hinzufügen","button button-primary");submit.type="submit";form.append(submit,button("Abbrechen",()=>manualDialog.close()));
    form.addEventListener("submit",event=>{event.preventDefault();run(async()=>{submit.disabled=true;try{await client.addItem({provider_id:provider.value,social_account_id:account.value?Number(account.value):null,external_post_url:url.value,author_display_name:author.value,post_excerpt:excerpt.value,external_post_urn:urn.value});items=(await client.items()).items;manualDialog.close();renderFeed();}finally{submit.disabled=false;}});});groupActions(form);manualDialog.append(form);manualDialog.showModal();
  }
  function render(){renderSummary();renderFeed();renderSelection();renderBase();renderTargets();renderFooter();}
  return {async load(){if(loaded)return;await run(async()=>{await reloadList();const [feedData,textData]=await Promise.all([client.items(),api.getActiveTextBlocks()]);items=feedData.items;blocks=textData.textBlocks;loaded=true;render();});},reset(){overlay.close(false);for(const d of [reviewDialog,manualDialog])if(d.open)d.close();campaign=newCampaign();items=campaigns=accounts=blocks=[];loaded=false;dirty=false;shell.replaceChildren(summary,feed,basePanel,targets,footer);for(const n of [summary,feed,selection,basePanel,targets,footer])n.replaceChildren();},refreshBlocks(value){blocks=value;if(loaded)renderBase();}};
}
