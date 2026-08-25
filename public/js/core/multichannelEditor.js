export function platformPreviewData(provider, text, post, channels = []) {
  const providerId = provider.id;
  const channel = channels.find((item) => item.providerId === providerId);
  const supportsMainLink = Boolean(provider.getCapabilities().link);
  const hasMainLink = supportsMainLink && Boolean(post.link?.trim());
  return {
    providerId,
    providerName: provider.name,
    text,
    channelName: channel?.displayName || "Kein Kanal ausgewählt",
    mainLink: hasMainLink ? {
      url: post.link.trim(),
      title: post.linkTitle?.trim() || "",
      description: post.linkDescription?.trim() || "",
    } : null,
    thumbnail: null,
    notice: supportsMainLink
      ? "Weitere URLs bleiben unverändert im Beitragstext. Ohne Bild-Asset wird kein Thumbnail dargestellt."
      : "Diese Plattform erhält derzeit nur die implementierten Text-/Medienfähigkeiten; eine Link-Vorschau wird nicht vorgetäuscht.",
  };
}
