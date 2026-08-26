export const LINK_MEDIA_CONFLICT_MESSAGE="LinkedIn unterstützt in diesem Beitrag entweder eine Link-Vorschau oder ein Medium. Entferne bitte den Hauptlink oder das Medium.";

export function hasStructuredLinkPreview(value){
  if(typeof value!=="string"||value.trim()==="")return false;
  try{const url=new URL(value.trim());return(url.protocol==="http:"||url.protocol==="https:")&&!url.username&&!url.password&&Boolean(url.hostname);}catch{return false;}
}

export function mediaDescriptor(file){
  if(!(file instanceof File))return null;
  return{file,mediaType:file.type.startsWith("image/")?"image":file.type.startsWith("video/")?"video":"document",filename:file.name,mimeType:file.type,fileSize:file.size};
}

export function renderLocalMediaPreview(container,selection,previewUrl=null){
  container.replaceChildren();
  const info=document.createElement("p");info.textContent=`${selection.filename} · ${(selection.fileSize/1048576).toFixed(2)} MB · ${selection.mimeType||"unbekannter Typ"}`;container.append(info);
  if(previewUrl&&(selection.mediaType==="image"||selection.mediaType==="video")){const preview=document.createElement(selection.mediaType==="image"?"img":"video");preview.src=previewUrl;preview.alt=selection.filename;if(preview instanceof HTMLVideoElement)preview.controls=true;container.append(preview);}
}

export async function runWithDisabledButton(button,action){
  if(!(button instanceof HTMLButtonElement))return false;
  button.disabled=true;
  try{await action();return true;}finally{if(button.isConnected)button.disabled=false;}
}
