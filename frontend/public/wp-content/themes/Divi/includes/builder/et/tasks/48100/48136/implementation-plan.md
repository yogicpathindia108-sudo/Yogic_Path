# Implementation Plan: #48136 - WooCommerce script dependency registration order issue

## Issue Description

**Error Notice:**
```
PHP Notice: Function WP_Scripts::add was called incorrectly. The script with the handle "wc-currency" was enqueued with dependencies that are not registered: wc-settings. Please see https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/ for more information. (This message was added in version 6.9.1.)
```

The script `wc-currency` is being registered with a dependency on `wc-settings`, but `wc-settings` has not been registered yet when the registration occurs.

## Problem Analysis

### Root Cause

The issue is a WordPress hook execution order problem in the admin context:

1. **Dependent script registration**: `wc-currency` is registered in `WCAdminAssets::register_scripts()` method with dependency `wc-settings` (from asset file)
2. **Hook for dependent script**: This method is hooked to `admin_enqueue_scripts` action with default priority (10)
3. **Dependency registration**: `wc-settings` is registered by WooCommerce Blocks plugin via `AssetsController::register_assets()` method
4. **Hook for dependency**: This registration function is hooked to `init` action (fires before `admin_enqueue_scripts`)
5. **Hook order issue**: In some contexts, WooCommerce core's `register_scripts()` may execute before WooCommerce Blocks has registered `wc-settings`, or WooCommerce Blocks may not be active

When `wp_register_script()` is called for `wc-currency` with dependency `wc-settings` during `admin_enqueue_scripts`, WordPress checks if `wc-settings` is registered. If it's not registered yet (or WooCommerce Blocks is not active), WordPress 6.9.1+ triggers a notice about unregistered dependencies.

### Reproduction

1. Use WordPress 6.9.1 or later
2. Have WooCommerce plugin active
3. Open any admin page
4. The PHP notice appears in debug.log because `wc-currency` is registered with an unregistered dependency `wc-settings`

### Impact

- **User-facing**: No visible impact (PHP notice only, not fatal error)
- **Developer experience**: Debug logs are polluted with notices
- **WordPress compliance**: Violates WordPress best practices for script dependencies
- **Future compatibility**: May cause issues if WordPress enforces stricter dependency validation

### Key Findings

- `wp-content/plugins/woocommerce/src/Internal/Admin/WCAdminAssets.php:322` - `wc-currency` registered with dependencies from asset file
- `wp-content/plugins/woocommerce/assets/client/admin/currency/index.asset.php` - Asset file declares `wc-settings` as dependency
- `wp-content/plugins/woocommerce/src/Internal/Admin/WCAdminAssets.php:49` - Hooked to `admin_enqueue_scripts` with default priority (10)
- `wc-settings` is registered by WooCommerce Blocks plugin (if active) on `init` hook
- WordPress hook order: `init` fires before `admin_enqueue_scripts`, but WooCommerce Blocks may not be active or may register later

### Evidence Anchors

- `wp-content/plugins/woocommerce/src/Internal/Admin/WCAdminAssets.php:322` → `wc-currency` in scripts array
- `wp-content/plugins/woocommerce/assets/client/admin/currency/index.asset.php` → Asset file with `wc-settings` dependency
- `wp-content/plugins/woocommerce/src/Internal/Admin/WCAdminAssets.php:49` → Hook registration: `add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) )`
- WooCommerce Blocks `AssetsController::register_assets()` → Registers `wc-settings` on `init` hook

### Confidence Level

**High** - The hook order issue is clear: WooCommerce core registers `wc-currency` with `wc-settings` dependency, but `wc-settings` may not be registered yet (or WooCommerce Blocks may not be active), causing WordPress 6.9.1+ to detect unregistered dependencies.

### Scope & Non-Goals

- **In scope**: Ensure `wc-settings` is registered before `wc-currency` attempts to use it as a dependency
- **In scope**: Maintain existing functionality and script loading behavior
- **Out of scope**: Modifying WooCommerce plugin code directly (would be overwritten on updates)
- **Out of scope**: Changing when WooCommerce registers scripts globally

## Solution Approach

Ensure `wc-settings` script is registered before WooCommerce tries to register `wc-currency` with it as a dependency. Since we can't modify WooCommerce plugin code directly, we'll register a placeholder `wc-settings` script early in Divi's `BlockEditorIntegration` class.

The solution is to hook into `admin_enqueue_scripts` with priority 5 (before WooCommerce's priority 10) and register a placeholder `wc-settings` script if it's not already registered. WooCommerce Blocks or WooCommerce core will override this placeholder with the actual script later, ensuring the dependency exists when `wc-currency` is registered.

This approach:
- Fixes the immediate issue by ensuring dependency registration order
- Maintains backward compatibility (doesn't interfere with WooCommerce's registration)
- Is minimal and focused (only registers placeholder if needed)
- Follows WordPress best practices (dependencies should be registered before dependent scripts)

## Technical Plan

### Files to Read First

- `wp-content/themes/Divi/includes/builder/feature/BlockEditorIntegration.php` - Understand the hook structure and where to add the fix
- `wp-content/plugins/woocommerce/src/Internal/Admin/WCAdminAssets.php` - Understand how WooCommerce registers scripts

### Files to Modify

- `wp-content/themes/Divi/includes/builder/feature/BlockEditorIntegration.php` - Add early `wc-settings` registration in `init_hooks()` method

### Implementation Steps

1. Add a new method `fix_woocommerce_wc_settings_dependency()` to `BlockEditorIntegration` class
2. In this method, check if `wc-settings` script is already registered using `wp_script_is( 'wc-settings', 'registered' )`
3. If not registered, register a placeholder script with empty source (WooCommerce Blocks/core will override it later)
4. Hook this method to `admin_enqueue_scripts` with priority 5 (before WooCommerce's priority 10)
5. Ensure the registration is idempotent (safe to call multiple times) and doesn't interfere with WooCommerce's registration

### Dependencies & Considerations

- **WordPress API**: Use `wp_script_is()` to check registration status and `wp_register_script()` for registration
- **Hook priority**: Must run before WooCommerce's `register_scripts()` (priority 10), so use priority 5
- **Placeholder registration**: Register with empty source string - WooCommerce Blocks/core will override with actual script
- **Idempotency**: The check ensures we don't re-register if already registered, maintaining compatibility

### Constraints & Invariants

- Must not modify WooCommerce plugin files (would be overwritten on updates)
- Must not interfere with WooCommerce Blocks registration (placeholder will be overridden)
- Must not affect other scripts or dependencies
- Must preserve existing functionality - scripts should still load correctly

### Execution Guardrails

- If `wp_script_is()` check fails or behaves unexpectedly, ensure fallback doesn't break script registration
- Placeholder registration with empty source is safe - WordPress allows this and WooCommerce will override it
- Do not remove the dependency from `wc-currency` - it needs `wc-settings` for functionality
- Do not change WooCommerce hook priorities - only add early registration in Divi

## Validation

**Test Scenarios**:
- Open any admin page and verify no PHP notice appears in debug.log
- Verify `wc-currency` script still loads correctly with its dependency
- Verify `wc-settings` script loads correctly (from WooCommerce Blocks/core)
- Test with WordPress 6.9.1+ where the notice was introduced
- Test with earlier WordPress versions to ensure no regression
- Test with WooCommerce Blocks active and inactive

**Success Criteria**:
- No PHP notice about unregistered dependency `wc-settings` when opening admin pages
- `wc-currency` script loads correctly with proper dependency chain
- No duplicate script registrations occur
- Existing functionality remains unchanged (scripts still work as before)
- Debug.log is clean of this specific notice

**Verification Methods**:
- **Automated**: Check debug.log for absence of the specific notice after opening admin pages
- **Manual**: Open admin pages, inspect page source to verify scripts are enqueued correctly
- **Manual**: Verify WooCommerce admin functionality still works correctly

## Handoff Pack (for `/execute` in fresh conversation)

- **Primary entry points**: `BlockEditorIntegration::init_hooks()` method, add hook to `admin_enqueue_scripts` with priority 5
- **Decision boundaries**: 
  - If `wc-settings` is already registered → do nothing
  - If `wc-settings` is not registered → register placeholder script
  - Use `wp_script_is( 'wc-settings', 'registered' )` to check registration status
- **Risk hotspots**: 
  - Hook priority must be before WooCommerce's priority 10
  - Placeholder registration must not interfere with WooCommerce Blocks registration
  - Must ensure idempotency to avoid duplicate registrations
- **Confidence notes**: 
  - The fix is minimal and localized to one registration point
  - WordPress `wp_register_script()` is idempotent (safe to call multiple times with same handle)
  - Placeholder registration with empty source is safe and will be overridden by WooCommerce
  - Hook priority 5 ensures we run before WooCommerce's registration

## References
- Focus Chain: See `focus-chain.md` for current todo status
