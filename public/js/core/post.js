import { createId } from "./utils.js";

export function createPost(values = {}) {
  const now = new Date().toISOString();
  const baseText=values.baseText??values.text??"",variants=values.variants&&typeof values.variants==="object"?values.variants:{};
  return { id: values.id || createId("post"), baseText, link: values.link || "", media: Array.isArray(values.media)?values.media:[], selectedProviders:Array.isArray(values.selectedProviders)?values.selectedProviders:[], variants, createdAt: values.createdAt || now, updatedAt: values.updatedAt||now, status: values.status || "draft" };
}

export function validatePost(post) {
  if (!post || typeof post !== "object") return { valid: false, errors: ["Beitrag konnte nicht erstellt werden."] };
  if (!(typeof post.baseText === "string" && post.baseText.trim()) && !(typeof post.link === "string" && post.link.trim()) && !post.media?.length) return { valid: false, errors: ["Bitte gib zuerst einen Beitrag ein."] };
  return { valid: true, errors: [] };
}

export function createVariant(providerId,text="",link="",values={}){return{providerId,text,link,status:values.status||"draft",updatedAt:values.updatedAt||new Date().toISOString()};}
export function ensureVariant(variants,providerId,baseText,link=""){return variants[providerId]||createVariant(providerId,baseText,link);}
export function updateVariantFromBase(variant,baseText,link=""){return createVariant(variant.providerId,baseText,link,{status:"draft"});}
