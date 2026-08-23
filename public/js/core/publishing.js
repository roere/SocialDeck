import { createId } from "./utils.js";
import { createPost, validatePost } from "./post.js";

export async function publishPost(values, providerIds, registry, storage, onUpdate = () => {}, resolveText = async text => text) {
  const post = createPost(values);
  const generalValidation = validatePost(post);
  if (!generalValidation.valid) throw new Error(generalValidation.errors[0]);
  if (!providerIds.length) throw new Error("Bitte wähle mindestens eine Plattform aus.");
  const jobs = [];
  for (const providerId of providerIds) {
    const provider = registry.get(providerId);
    if (!provider) continue;
    const variant=post.variants[providerId];
    const job = { id: createId("job"), postId: post.id, providerId, accountId: null, status: "publishing", createdAt: new Date().toISOString(), publishedAt: null, externalPostId: null, finalText:null, error: null };
    onUpdate({ type: "started", provider, job });
    try {
      if(!variant)throw new Error(`Keine Fassung für ${provider.name} vorhanden.`);
      const finalText=await resolveText(variant.text);
      const publishable={...post,text:finalText,link:variant.link??post.link};
      const validation=provider.validatePost(publishable);if(!validation.valid)throw new Error(validation.errors[0]);
      const result = await provider.publish(publishable);
      job.status = "success"; job.publishedAt = result.publishedAt; job.externalPostId = result.externalPostId;job.finalText=finalText;
      onUpdate({ type: "completed", provider, job, result });
    } catch (error) {
      job.status = "failed"; job.error = error.message;
      onUpdate({ type: "failed", provider, job, error });
    }
    storage.savePublishingJob(job); jobs.push(job);
  }
  return { post, jobs };
}
