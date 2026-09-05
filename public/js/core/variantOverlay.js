export function createVariantOverlayController({dialog,content,title,body=document.body}){
  let card=null,placeholder=null,trigger=null,restoreFocus=true;
  function finish(){if(!card)return;if(placeholder?.isConnected)placeholder.replaceWith(card);body.classList.remove("variant-overlay-open");const focusTarget=trigger;card=placeholder=trigger=null;if(restoreFocus)focusTarget?.focus();restoreFocus=true;}
  function requestClose(shouldRestoreFocus=true){if(!card)return;restoreFocus=shouldRestoreFocus;if(dialog.open)dialog.close();finish();}
  dialog.addEventListener("close",finish);
  dialog.addEventListener("keydown",event=>{if(event.key==="Escape"&&card){event.preventDefault();requestClose();}});
  return{
    open(nextCard,nextTrigger,platformName){if(card)return;card=nextCard;trigger=nextTrigger;placeholder=document.createElement("div");placeholder.className="platform-variant-placeholder";placeholder.setAttribute("aria-hidden","true");card.replaceWith(placeholder);content.replaceChildren(card);title.textContent=platformName;body.classList.add("variant-overlay-open");dialog.showModal();card.querySelector("textarea")?.focus();},
    close(shouldRestoreFocus=true){requestClose(shouldRestoreFocus);},
    isOpen(){return Boolean(card);}
  };
}
