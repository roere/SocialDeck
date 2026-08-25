import { createId } from "./utils.js";
import { createPost, validatePost } from "./post.js";

export async function publishPost(values, targets, registry, storage, onUpdate = () => {}, resolveText = async text => text) {
  const post = createPost(values);
  const generalValidation = validatePost(post);
  if (!generalValidation.valid) throw new Error(generalValidation.errors[0]);
  if (!targets.length) throw new Error("Bitte wähle mindestens einen Kanal aus.");
  const jobs = [];
  for (const target of targets) {
    const channel=typeof target==="string"?null:target,providerId=typeof target==="string"?target:target.providerId;
    const provider = registry.get(providerId);
    if (!provider) continue;
    const variant=post.variants[providerId];
    const job = { id: createId("job"), postId: post.id, providerId, socialAccountId:channel?.socialAccountId??null,channelId:channel?.id??null,channelType:channel?.channelType??null,externalChannelId:channel?.externalChannelId??null, accountId: channel?.socialAccountId??null, status: "publishing", createdAt: new Date().toISOString(), publishedAt: null, externalPostId: null, finalText:null, finalLinkUrl:null, linkTitle:null, linkDescription:null, error: null };
    onUpdate({ type: "started", provider, job });
    try {
      if(!variant)throw new Error(`Keine Fassung für ${provider.name} vorhanden.`);
      const finalText=await resolveText(variant.text);
      const publishable={...post,text:finalText,link:variant.link??post.link};
      const validation=provider.validatePost(publishable);if(!validation.valid)throw new Error(validation.errors[0]);
      const result = await provider.publish(publishable);
      job.status = "success"; job.publishedAt = result.publishedAt; job.externalPostId = result.externalPostId;job.finalText=finalText;job.finalLinkUrl=publishable.link||null;job.linkTitle=publishable.link?post.linkTitle||null:null;job.linkDescription=publishable.link?post.linkDescription||null:null;
      onUpdate({ type: "completed", provider, job, result });
    } catch (error) {
      job.status = "failed"; job.error = error.message;
      onUpdate({ type: "failed", provider, job, error });
    }
    storage.savePublishingJob(job); jobs.push(job);
  }
  return { post, jobs };
}
