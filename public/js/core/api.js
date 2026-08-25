async function request(path, options = {}) {
  const response = await fetch(`/api${path}`, { credentials: "same-origin", headers: { "Content-Type": "application/json", ...(options.headers || {}) }, ...options });
  const body = await response.json().catch(() => ({ ok: false, error: { message: "Ungültige Serverantwort." } }));
  if (!response.ok || !body.ok) { const error = new Error(body.error?.message || "Anfrage fehlgeschlagen."); error.status = response.status; throw error; }
  return body.data;
}

export const api = {
  csrf() { return request("/auth/csrf"); },
  login(login, password, csrfToken) { return request("/auth/login", { method: "POST", headers: { "X-CSRF-Token": csrfToken }, body: JSON.stringify({ login, password }) }); },
  logout(csrfToken) { return request("/auth/logout", { method: "POST", headers: { "X-CSRF-Token": csrfToken } }); },
  me() { return request("/auth/me"); },
  getProviders(csrfToken) { return request("/admin/providers", { headers: { "X-CSRF-Token": csrfToken } }); },
  saveProvider(providerId, config, csrfToken) { return request(`/admin/providers/${providerId}`, { method: "PUT", headers: { "X-CSRF-Token": csrfToken }, body: JSON.stringify(config) }); },
  getLegal(csrfToken) { return request("/admin/legal", { headers: { "X-CSRF-Token": csrfToken } }); },
  getPublicLegal() { return request("/legal"); },
  getActiveTextBlocks() { return request("/text-blocks"); },
  resolveText(text) { return request("/text-blocks/resolve", { method: "POST", body: JSON.stringify({ text }) }); },
  getTextBlocks() { return request("/admin/text-blocks"); },
  getEmailSettings() { return request("/admin/email-settings"); },
  saveEmailSettings(values, csrfToken) { return request("/admin/email-settings", { method: "PUT", headers: { "X-CSRF-Token": csrfToken }, body: JSON.stringify(values) }); },
  createTextBlock(values, csrfToken) { return request("/admin/text-blocks", { method: "POST", headers: { "X-CSRF-Token": csrfToken }, body: JSON.stringify(values) }); },
  updateTextBlock(id, values, csrfToken) { return request(`/admin/text-blocks/${id}`, { method: "PUT", headers: { "X-CSRF-Token": csrfToken }, body: JSON.stringify(values) }); },
  deleteTextBlock(id, csrfToken) { return request(`/admin/text-blocks/${id}`, { method: "DELETE", headers: { "X-CSRF-Token": csrfToken } }); },
  saveLegal(settings, csrfToken) { return request("/admin/legal", { method: "PUT", headers: { "X-CSRF-Token": csrfToken }, body: JSON.stringify(settings) }); },
  changePassword(values, csrfToken) { return request("/admin/password", { method: "PUT", headers: { "X-CSRF-Token": csrfToken }, body: JSON.stringify(values) }); },
  startLinkedIn(csrfToken) { return request("/oauth/linkedin/start", { headers: { Accept: "application/json", "X-CSRF-Token": csrfToken } }); },
  syncLinkedInChannels(csrfToken) { return request("/admin/providers/linkedin/channels/sync", { method: "POST", headers: { "X-CSRF-Token": csrfToken } }); },
  disconnectLinkedIn(accountId, csrfToken) { return request("/oauth/linkedin/disconnect", { method: "POST", headers: { "X-CSRF-Token": csrfToken }, body: JSON.stringify({ accountId }) }); },
  publishLinkedInTestPost(values, csrfToken) { return request("/admin/linkedin/test-post", { method: "POST", headers: { "X-CSRF-Token": csrfToken }, body: JSON.stringify(values) }); }
};
