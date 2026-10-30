# TALA public website design

The current homepage in `index.php` is the visual reference. Public pages share `navigation.php`, `public_footer.php`, and `public-design.css`.

- Use Plus Jakarta Sans, strong section headings, short supporting copy, and generous spacing.
- Light mode uses a white page with slate-50 cards. Dark mode uses zinc-950 pages, zinc-900 cards, and zinc-800 fields.
- Use the green-to-yellow gradient for primary actions and main headings. Maintain readable contrast in both themes.
- Prefer rounded 2rem cards, subtle shadows, and minimal borders. Keep forms readable and tables horizontally scrollable on narrow screens.
- Public page containers use the homepage's responsive horizontal padding and a maximum width appropriate to their content. Keep simple forms narrower than directories and dashboards.
- Use `tala-theme.js` and `tala-theme.css` for the shared sun/moon switch. Theme colors update together; do not add per-element delays to theme changes.
- Preserve form submission behavior. Theme controls must be buttons with `type="button"`.
- Generated center imagery lives in `assets/centers/`. Prompts and original images are retained there; the website consumes optimized WebP files.
