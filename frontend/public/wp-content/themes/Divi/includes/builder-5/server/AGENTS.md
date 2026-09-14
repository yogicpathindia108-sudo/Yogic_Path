# Server (PHP)

House rules for `server/`. REST controllers live under `VisualBuilder/REST/` and `Packages/ModuleLibrary/*/`.

## REST args are the trust boundary

Sanitize and validate on `*_args()` (`sanitize_callback`, `validate_callback`, controller `sanitize_*`). Handlers and `index()` after that must be able to trust the params.

- Use a real sanitizer: `sanitize_text_field`, `absint`, `sanitize_key`, `esc_url_raw`, `wp_kses_post`, `rest_sanitize_boolean`.
- Not sanitization: `is_array` / `is_string` only, or `trim( wp_unslash() )` with no WP sanitizer.
- Nested arrays/objects: sanitize each field; drop or reject unknown keys. Do not copy nested values through a whitelist map.
- Auth: `permission_callback` uses a real capability (`UserRole::can_current_user_use_visual_builder()`, `current_user_can( …, $id )`), not `__return_true` or `is_user_logged_in()` alone.

## Dual render

`StyleLibrary`, `ModuleLibrary`, `ModuleUtils`, `GlobalData`, and `Conversion` have a TypeScript twin under `visual-builder/packages/`. Signature and behavior must match.
