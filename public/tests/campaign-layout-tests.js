const assert=(v,m)=>{if(!v)throw new Error(m);};
async function fixture(width,check){
 const frame=document.createElement('iframe');frame.style.cssText=`width:${width}px;height:900px;border:0;position:absolute;left:0;top:0`;frame.srcdoc=await (await fetch('./campaign-layout.html')).text();document.body.append(frame);
 try{await new Promise((resolve,reject)=>{frame.onload=resolve;frame.onerror=reject;});for(let i=0;i<50&&!frame.contentWindow.layoutReady;i++)await new Promise(r=>setTimeout(r,10));assert(frame.contentWindow.layoutReady,'Fixture lädt nicht');await check(frame.contentDocument,frame.contentWindow);}finally{frame.remove();}
}
export const campaignLayoutTests=[
 ['Kampagne Desktop: gemeinsame Breite, Typografie, Filter und Listen',()=>fixture(1280,async(d,w)=>{
   const main=d.querySelector('.app-shell'),root=d.querySelector('#campaign-root');assert(main.clientWidth===1160&&root.clientWidth===main.clientWidth,'Abweichende Hauptbreite');
   assert([...d.querySelectorAll('.campaign-shell h2')].map(n=>n.textContent).join('|')==='Kampagnenübersicht|Relevante Beiträge|Kampagnenantwort|Konkrete Antworten|Veröffentlichung','Überschriften');
   const controls=[...d.querySelectorAll('.campaign-filters select,.campaign-filters input')];assert(new Set(controls.map(n=>Math.round(n.getBoundingClientRect().top))).size===1,'Filter nicht in einer Zeile');
   assert(controls.every(n=>w.getComputedStyle(n).borderRadius==='5px'),'Formelemente');
   const rows=[...d.querySelectorAll('.campaign-feed-card')];assert(rows.every(n=>n.classList.contains('post-list-item'))&&rows[1].offsetTop>rows[0].offsetTop,'Keine kompakte Liste');assert(rows[0].clientHeight<260,'Treffer zu hoch');
   assert(d.querySelector('.campaign-target.platform-variant > .panel-heading .status-badge'),'Antwortkarte/Status');
   assert(d.querySelector('.campaign-platforms .provider-option'),'Provider-Muster');assert(d.querySelector('.post-text-block strong'),'Editor-Textbaustein');
   assert(d.querySelector('.publish-bar .button-primary')&&d.querySelector('.publish-bar .status-badge'),'Publishing-Hierarchie');assert(!d.querySelector('.panel .panel'),'Verschachtelte Karten');assert(!w.layoutErrors.length,'Browserfehler');
 })],
 ...[900,390,320].map(width=>[`Kampagne ${width}px: kein Overflow und bedienbares Overlay`,()=>fixture(width,async(d,w)=>{
   assert(d.documentElement.scrollWidth<=width,'Horizontaler Seitenoverflow');const button=d.querySelector('[aria-label="Antwort vergrößern"]');button.click();await new Promise(r=>setTimeout(r,0));
   const dialog=d.querySelector('dialog[open]');assert(dialog.classList.contains('platform-variant-dialog')&&dialog.querySelector('.platform-variant-dialog-shell > .platform-variant-dialog-header'),'Abweichendes Modal');assert(d.body.classList.contains('variant-overlay-open'),'Hintergrundscroll');assert(dialog.scrollWidth<=dialog.clientWidth,'Dialogoverflow');
   const textarea=dialog.querySelector('textarea');assert(d.activeElement===textarea,'Editor-Fokus fehlt');textarea.value='Unveränderter gemeinsamer State';textarea.dispatchEvent(new Event('input'));
   dialog.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));assert(!dialog.open&&!d.body.classList.contains('variant-overlay-open'),'ESC/Scroll');assert(d.querySelector('.campaign-reply textarea').value===textarea.value,'State verloren');assert(d.activeElement===button,'Fokus nicht wiederhergestellt');
   d.querySelector('.campaign-platforms input').click();assert(d.querySelector('.campaign-target').classList.contains('campaign-disabled'),'Plattformschalter');assert(d.documentElement.scrollWidth<=width,'Overflow nach Deaktivierung');assert(!w.layoutErrors.length,'Browserfehler');
 })])
];
