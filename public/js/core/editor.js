import{createPost,createVariant,ensureVariant,updateVariantFromBase}from"./post.js";
export function insertReferenceAtSelection(textarea,key){const start=textarea.selectionStart??textarea.value.length,end=textarea.selectionEnd??start,reference=`{{${key}}}`;textarea.setRangeText(reference,start,end,"end");textarea.focus();textarea.dispatchEvent(new Event("input",{bubbles:true}));return start+reference.length;}
export function activateProvider(post,providerId,resolvedBase){return{...post,selectedProviders:[...new Set([...post.selectedProviders,providerId])],variants:{...post.variants,[providerId]:ensureVariant(post.variants,providerId,resolvedBase,post.link)}};}
export function deactivateProvider(post,providerId){return{...post,selectedProviders:post.selectedProviders.filter(id=>id!==providerId)};}
export function replaceVariantFromBase(post,providerId,resolvedBase){const current=post.variants[providerId]||createVariant(providerId);return{...post,variants:{...post.variants,[providerId]:updateVariantFromBase(current,resolvedBase,post.link)}};}
export function draftFromValues(values){return createPost(values);}
