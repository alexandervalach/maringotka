"use strict";

/** Keep a single reservation preview open in the admin calendar. */
document.addEventListener("DOMContentLoaded", () => {
  const calendar = document.querySelector(".booking-calendar-admin");

  if (!calendar) {
    return;
  }

  const previews = Array.from(calendar.querySelectorAll("details.booking-calendar-admin__preview"));

  for (const preview of previews) {
    preview.addEventListener("toggle", () => {
      if (!preview.open) {
        return;
      }

      for (const other of previews) {
        if (other !== preview) {
          other.open = false;
        }
      }
    });
  }

  calendar.addEventListener("click", (event) => {
    const button = event.target.closest(".booking-calendar-admin__popup-close");

    if (!button) {
      return;
    }

    const preview = button.closest("details.booking-calendar-admin__preview");

    if (preview) {
      preview.open = false;
      preview.querySelector("summary")?.focus();
    }
  });
});
