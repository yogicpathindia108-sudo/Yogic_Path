# The Issue

### Issue Reference
Fixes: https://github.com/elegantthemes/Divi/issues/48136

### Root Cause
WordPress hook execution order in the admin context causes a dependency registration race condition. The `wc-currency` script (from WooCommerce) is registered during the `admin_enqueue_scripts` hook with a dependency on `wc-settings`. However, `wc-settings` is registered by WooCommerce Blocks plugin (if active) on the `init` hook, which fires earlier. In some cases, WooCommerce core's `register_scripts()` method executes before WooCommerce Blocks has registered `wc-settings`, or WooCommerce Blocks may not be active at all. Since `admin_enqueue_scripts` executes after `init`, WordPress 6.9.1+ detects the unregistered dependency and logs a PHP notice.

### Historical Context
The dependency relationship between `wc-currency` and `wc-settings` was established in WooCommerce's asset file (`currency/index.asset.php`). The issue became visible with WordPress 6.9.1's stricter dependency validation, which now warns about scripts registered with unregistered dependencies. Since we cannot modify WooCommerce plugin code directly (it would be overwritten on updates), we fix this in Divi by ensuring the dependency exists early.

---

# The Pull Request

### Solution Approach
Proactively ensure `wc-settings` is registered before WooCommerce tries to register `wc-currency` with it as a dependency. Hook into `admin_enqueue_scripts` with priority 5 (before WooCommerce's priority 10) and register a placeholder `wc-settings` script if it's not already registered. WooCommerce Blocks or WooCommerce core will override this placeholder with the actual script later, ensuring the dependency exists when `wc-currency` is registered. This ensures the dependency exists without modifying WooCommerce plugin code, maintaining backward compatibility while fixing the WordPress 6.9.1+ notice.

### Screencast Verification
*[User to provide] Screencast showing reproduction steps and fix verification.*

### Testing & Verification
- Open any admin page and verify no PHP notice appears in debug.log
- Verify `wc-currency` script loads correctly with its dependency chain intact
- Verify `wc-settings` script loads correctly (from WooCommerce Blocks/core)
- Test with WordPress 6.9.1+ where the notice was introduced
- Test with earlier WordPress versions to ensure no regression
- Test with WooCommerce Blocks active and inactive
- Verify no duplicate script registrations occur
- Verify WooCommerce admin functionality still works correctly

### Alternative Solutions (if any)
No alternatives were tried. The solution directly addresses the root cause (hook execution order) by ensuring the dependency is available when needed, without modifying WooCommerce plugin code (which would be overwritten on updates).

### Changelog
Fixed PHP notice about unregistered script dependency (`wc-settings`) when WooCommerce is active in WordPress 6.9.1+.
