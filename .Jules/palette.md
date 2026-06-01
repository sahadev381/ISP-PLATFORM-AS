## 2026-06-01 - [Accessibility: Semantic Buttons for Interactive Elements]
**Learning:** Using `<i>` or `<span>` tags for interactive elements (like password toggles) with `role="button"` is insufficient for keyboard accessibility. These elements are not focusable by default and do not respond to the "Enter" or "Space" keys without additional effort.
**Action:** Always use a semantic `<button type="button">` for interactive icons. This ensures they are in the tab order, are focusable, and respond to standard keyboard interactions out of the box.

## 2026-06-01 - [UX: Dynamic ARIA Labels for State Toggles]
**Learning:** Screen reader users need to know the *result* of an action. When toggling a password's visibility, simply changing the icon is not enough.
**Action:** Dynamically update `aria-label` and `title` attributes (e.g., from "Show password" to "Hide password") in the toggle's click handler to provide immediate feedback to assistive technologies.
