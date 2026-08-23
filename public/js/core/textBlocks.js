export function textBlockValues(form) {
  const data = new FormData(form);
  return {
    title: String(data.get("title") || ""),
    key: String(data.get("key") || ""),
    category: String(data.get("category") || ""),
    content: String(data.get("content") || ""),
    isActive: form.elements.isActive.checked,
    sortOrder: Number(data.get("sortOrder")),
  };
}

export function filterTextBlocks(blocks, query) {
  const needle = query.trim().toLocaleLowerCase("de");
  return blocks.filter((block) => `${block.title} ${block.key} ${block.category} ${block.content}`.toLocaleLowerCase("de").includes(needle));
}

export function populateTextBlockForm(form, block) {
  form.elements.title.value = block.title;
  form.elements.key.value = block.key;
  form.elements.category.value = block.category;
  form.elements.content.value = block.content;
  form.elements.isActive.checked = block.isActive;
  form.elements.sortOrder.value = String(block.sortOrder);
}

export function applyTextBlockProtection(form, deleteButton, block) {
  form.elements.key.readOnly = Boolean(block.isSystem);
  form.elements.isActive.disabled = Boolean(block.isSystem);
  deleteButton.disabled = Boolean(block.isSystem);
  deleteButton.title = block.isSystem ? "System-Textbausteine können nicht gelöscht werden." : "";
}

export function markSelectedTextBlock(list, selectedId) {
  list.querySelectorAll("[data-text-block-id]").forEach((option) => option.setAttribute("aria-selected", String(Number(option.dataset.textBlockId) === selectedId)));
}

export function availableTextBlockReferences(blocks, currentId = null) {
  return blocks.filter((block) => block.isActive && block.id !== currentId);
}

export function createTextBlockReferenceButton(block, onInsert) {
  const button = document.createElement("button");
  const syntax = document.createElement("span");
  const title = document.createElement("small");
  button.type = "button";
  button.className = "text-block-placeholder";
  button.dataset.placeholder = block.key;
  button.title = block.isSystem ? `${block.title} (System-Textbaustein)` : block.title;
  syntax.textContent = `{{${block.key}}}`;
  title.textContent = block.title;
  button.append(syntax, title);
  if (block.isSystem) {
    const badge = document.createElement("small");
    badge.className = "text-block-system-label";
    badge.textContent = "System";
    button.append(badge);
  }
  button.addEventListener("click", () => onInsert(block.key));
  return button;
}

export function insertPlaceholderAtSelection(textarea, key) {
  const placeholder = `{{${key}}}`;
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  textarea.setRangeText(placeholder, start, end, "end");
  textarea.focus();
  return textarea.selectionStart;
}

export function createTextBlockSubmitHandler({ create, csrfToken, onCreated, onError }) {
  return async function submitTextBlock(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const result = await create(textBlockValues(form), csrfToken());
      form.reset();
      form.elements.isActive.checked = true;
      form.elements.sortOrder.value = "0";
      await onCreated(result);
    } catch (error) {
      onError(error);
    }
  };
}
