import { createId } from "../core/utils.js";
export function createMockProvider(metadata) {
  return { ...metadata,
    getCapabilities(){return{...metadata.capabilities};},
    validatePost(post){const text=post?.text??post?.baseText;return typeof text==="string"&&text.trim()?{valid:true,errors:[]}:{valid:false,errors:["Bitte gib zuerst einen Beitrag ein."]};},
    async publish(post){const validation=this.validatePost(post);if(!validation.valid)throw new Error(validation.errors[0]);const media=(post.media||[]).map(item=>({type:item.type,externalMediaId:`urn:li:${item.type}:mock-${createId("media")}`}));return{success:true,providerId:this.id,externalPostId:`mock-${this.id}-${createId("post")}`,media,publishedAt:new Date().toISOString(),message:`Beitrag erfolgreich für ${this.name} vorbereitet.`};},
    async connect(){return{connected:false,message:`${this.name} OAuth wird in einem späteren Schritt ergänzt.`};},async disconnect(){return{disconnected:true};},async getAccounts(){return[];}
  };
}
