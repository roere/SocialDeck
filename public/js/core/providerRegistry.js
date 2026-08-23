export function createProviderRegistry() {
  const providers = new Map();
  return {
    register(provider) { if (!provider?.id) throw new Error("Provider benötigen eine ID."); providers.set(provider.id, provider); },
    get(id) { return providers.get(id); },
    list() { return [...providers.values()]; }
  };
}