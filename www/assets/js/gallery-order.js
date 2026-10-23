"use strict";

(() => {
  const list = document.querySelector("#gallery-order-list");
  const input = document.querySelector('input[name="ordered_ids"]');
  if (!list || !input) return;

  const cards = () => Array.from(list.querySelectorAll("[data-gallery-id]"));
  const synchronize = () => {
    input.value = cards().map(card => card.dataset.galleryId).filter(id => /^\d+$/.test(id) && id !== "0").join(",");
  };

  let dragged = null;
  list.addEventListener("dragstart", event => {
    const card = event.target.closest("[data-gallery-id]");
    if (!card || card.getAttribute("draggable") !== "true") return;
    dragged = card;
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", card.dataset.galleryId);
  });
  list.addEventListener("dragover", event => {
    const target = event.target.closest("[data-gallery-id]");
    if (!dragged || !target || target === dragged) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = "move";
  });
  list.addEventListener("drop", event => {
    const target = event.target.closest("[data-gallery-id]");
    if (!dragged || !target || target === dragged) return;
    event.preventDefault();
    const after = target.compareDocumentPosition(dragged) & Node.DOCUMENT_POSITION_FOLLOWING;
    list.insertBefore(dragged, after ? target : target.nextSibling);
    synchronize();
  });
  list.addEventListener("dragend", () => { dragged = null; });
  list.addEventListener("click", event => {
    const button = event.target.closest(".gallery-move-up, .gallery-move-down");
    if (!button) return;
    const card = button.closest("[data-gallery-id]");
    if (button.classList.contains("gallery-move-up") && card.previousElementSibling) {
      list.insertBefore(card, card.previousElementSibling);
    } else if (button.classList.contains("gallery-move-down") && card.nextElementSibling) {
      list.insertBefore(card.nextElementSibling, card);
    }
    synchronize();
    button.focus();
  });
  synchronize();
})();
