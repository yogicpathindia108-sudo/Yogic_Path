<?php
/**
 * LocaleUtility class.
 *
 * This class provides centralized methods for WordPress locale switching operations,
 * particularly for handling user profile language preferences in the Visual Builder.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

/**
 * LocaleUtility class.
 *
 * TODO feat(D5, Translation): Handle locale switching for user profile language preference [https://github.com/elegantthemes/Divi/issues/45526].
 *
 * This class provides centralized methods for WordPress locale switching operations,
 * particularly for handling user profile language preferences in the Visual Builder.
 *
 * @since ??
 */
class LocaleUtility {

	/**
	 * Switch to a specific locale type if different from current locale.
	 *
	 * This method compares the target locale with the current locale and only
	 * switches if they are different and the WordPress locale functions are available.
	 *
	 * @since ??
	 *
	 * @param string $type The locale type to switch to. Accepts 'user' or 'site'.
	 *
	 * @return bool True if locale was switched, false otherwise.
	 */
	public static function maybe_switch_locale( string $type ): bool {
		// Validate the locale type parameter.
		if ( ! in_array( $type, [ 'user', 'site' ], true ) ) {
			return false;
		}

		// Bail early if the `switch_to_locale` function does not exist.
		if ( ! function_exists( 'switch_to_locale' ) ) {
			return false;
		}

		$user_locale = get_user_locale();
		$site_locale = get_locale();

		// Handle switching logic based on type.
		if ( 'user' === $type ) {
			// Switch to user locale if it's different from site locale.
			if ( $user_locale !== $site_locale ) {
				return switch_to_locale( $user_locale );
			}
			// phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found -- Intentional else-if pattern for readability.
		} else {
			// Switch to site locale if currently switched to a different locale.
			if ( function_exists( 'is_locale_switched' ) && is_locale_switched() ) {
				return switch_to_locale( $site_locale );
			}
		}

		return false;
	}

	/**
	 * Restore the previous locale if it was switched by Divi5 utility.
	 *
	 * This method only restores the locale if it was switched by this utility class,
	 * preventing interference with locale switches made by other parts of the application.
	 *
	 * @since ??
	 *
	 * @param bool $is_switched Whether locale was switched by this utility.
	 *
	 * @return void
	 */
	public static function maybe_restore_locale( bool $is_switched ): void {
		if ( $is_switched && function_exists( 'restore_previous_locale' ) ) {
			restore_previous_locale();
		}
	}
}
