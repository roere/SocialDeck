const titles={state:"OAuth-Sitzung ungültig oder abgelaufen.",access_denied:"LinkedIn-Autorisierung wurde abgebrochen.",scope:"LinkedIn-Autorisierung fehlgeschlagen: Mindestens eine angefragte Berechtigung konnte nicht verwendet werden.",token_exchange:"LinkedIn-Autorisierung: Tokenaustausch fehlgeschlagen.",userinfo:"LinkedIn-Autorisierung: Profilabruf fehlgeschlagen.",authorization:"LinkedIn-Autorisierung fehlgeschlagen."};
const tokenDetails={LINKEDIN_TOKEN_CLIENT:"LinkedIn hat die Client-Anmeldedaten abgelehnt.",LINKEDIN_TOKEN_CODE:"Der Autorisierungscode ist ungültig oder abgelaufen.",LINKEDIN_TOKEN_REDIRECT:"Die Redirect URI stimmt nicht überein.",LINKEDIN_TOKEN_RATE_LIMIT:"Zu viele Token-Anfragen. Bitte später erneut versuchen.",LINKEDIN_TOKEN_DNS:"LinkedIn konnte nicht aufgelöst werden.",LINKEDIN_TOKEN_TLS:"Die sichere Verbindung zu LinkedIn ist fehlgeschlagen.",LINKEDIN_TOKEN_TIMEOUT:"LinkedIn hat nicht rechtzeitig geantwortet.",LINKEDIN_TOKEN_CONNECTION:"Die Verbindung zu LinkedIn wurde abgelehnt.",LINKEDIN_TOKEN_TRANSPORT:"LinkedIn konnte technisch nicht erreicht werden.",LINKEDIN_TOKEN_RESPONSE:"LinkedIn hat eine ungültige Token-Antwort geliefert.",LINKEDIN_TOKEN_UPSTREAM:"LinkedIn hat den Token-Austausch abgelehnt.",LINKEDIN_TOKEN:"Die Token-Antwort konnte nicht verwendet werden."};
export function oauthOutcomeText(result){return titles[result?.oauthErrorCategory]||titles.authorization;}
export function linkedInConnectionUnverified(config){return config.id==="linkedin"&&config.oauthResult?.result==="failed"&&config.oauthResult.existingConnection;}
export function renderLinkedInOAuthResult(result){
  if(!result||result.result!=="failed")return null;
  const box=document.createElement("section");box.className="account-placeholder linkedin-oauth-result";box.setAttribute("role","status");
  const add=(tag,text)=>{const n=document.createElement(tag);n.textContent=text;box.append(n);return n;};
  add("strong",oauthOutcomeText(result));
  if(tokenDetails[result.oauthError])add("p",tokenDetails[result.oauthError]);
  if(result.scopeSnapshotAvailable){add("p","Angefragte Berechtigungen:");const list=add("ul","");for(const scope of result.requestedScopes||[]){const li=document.createElement("li");li.textContent=scope;list.append(li);}}
  else add("p","Für diesen ungültigen, abgelaufenen oder älteren OAuth-Versuch ist kein Scope-Snapshot verfügbar.");
  if(["scope","authorization"].includes(result.oauthErrorCategory)){
    add("p","Mindestens eine dieser Berechtigungen ist für diese LinkedIn-App möglicherweise nicht verfügbar oder noch nicht freigegeben. Prüfe die Scopes und Products im LinkedIn Developer Portal.");
    const a=add("a","LinkedIn Developer Portal prüfen");a.href="https://www.linkedin.com/developers/apps";a.target="_blank";a.rel="noopener noreferrer";
  }
  if(result.existingConnection){add("p","Vorhandene Verbindung – Gültigkeit nach Scope-Änderung noch nicht bestätigt.");add("p","Wenn sich der angefragte Berechtigungsumfang geändert hat, kann LinkedIn eine erneute Autorisierung erforderlich machen.");}
  return box;
}
