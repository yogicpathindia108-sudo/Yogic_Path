<?php
/**
 * D4 to D5 text domain bridge.
 *
 * Bridges legacy D4 `et_builder` translation lookups to `et_builder_5`
 * for D5 runtime flows while keeping safe fallback behavior.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\I18n;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * D4DomainBridge class.
 *
 * @since ??
 */
class D4DomainBridge {
	/**
	 * Legacy D4 text domain used by some code paths still consumed by D5.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const D4_DOMAIN = 'et_builder';

	/**
	 * Register runtime bridge filters.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'gettext', [ self::class, 'filter_gettext' ], 20, 3 );
		add_filter( 'gettext_with_context', [ self::class, 'filter_gettext_with_context' ], 20, 4 );
		add_filter( 'ngettext', [ self::class, 'filter_ngettext' ], 20, 5 );
		add_filter( 'ngettext_with_context', [ self::class, 'filter_ngettext_with_context' ], 20, 6 );
	}

	/**
	 * Bridge simple gettext calls.
	 *
	 * @since ??
	 *
	 * @param string|null $translation Translated text from the original domain.
	 * @param string|null $text        Original text.
	 * @param string|null $domain      Text domain.
	 *
	 * @return string
	 */
	public static function filter_gettext( ?string $translation, ?string $text, ?string $domain ): string {
		$safe_translation = $translation ?? '';

		if ( null === $text || null === $domain || self::D4_DOMAIN !== $domain ) {
			return $safe_translation;
		}

		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Runtime bridge translates dynamic legacy strings.
		$bridged = translate( $text, 'et_builder_5' );

		return $bridged !== $text ? $bridged : $safe_translation;
	}

	/**
	 * Bridge gettext-with-context calls.
	 *
	 * @since ??
	 *
	 * @param string      $translation Translated text from the original domain.
	 * @param string      $text        Original text.
	 * @param string|null $context     Context string.
	 * @param string      $domain      Text domain.
	 *
	 * @return string
	 */
	public static function filter_gettext_with_context( string $translation, string $text, ?string $context, string $domain ): string {
		if ( null === $context || self::D4_DOMAIN !== $domain ) {
			return $translation;
		}

		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText,WordPress.WP.I18n.NonSingularStringLiteralContext -- Runtime bridge translates dynamic legacy strings.
		$bridged = translate_with_gettext_context( $text, $context, 'et_builder_5' );

		return $bridged !== $text ? $bridged : $translation;
	}

	/**
	 * Bridge plural gettext calls.
	 *
	 * @since ??
	 *
	 * @param string $translation Translated text from the original domain.
	 * @param string $single      Singular form.
	 * @param string $plural      Plural form.
	 * @param int    $number      Number used to resolve plural index.
	 * @param string $domain      Text domain.
	 *
	 * @return string
	 */
	public static function filter_ngettext( string $translation, string $single, string $plural, int $number, string $domain ): string {
		if ( self::D4_DOMAIN !== $domain ) {
			return $translation;
		}

		$source_text = 1 === $number ? $single : $plural;

		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingle,WordPress.WP.I18n.NonSingularStringLiteralPlural -- Runtime bridge translates dynamic legacy plural strings.
		$bridged = _n( $single, $plural, $number, 'et_builder_5' );

		return $bridged !== $source_text ? $bridged : $translation;
	}

	/**
	 * Bridge plural gettext-with-context calls.
	 *
	 * @since ??
	 *
	 * @param string      $translation Translated text from the original domain.
	 * @param string      $single      Singular form.
	 * @param string      $plural      Plural form.
	 * @param int         $number      Number used to resolve plural index.
	 * @param string|null $context     Context string.
	 * @param string      $domain      Text domain.
	 *
	 * @return string
	 */
	public static function filter_ngettext_with_context( string $translation, string $single, string $plural, int $number, ?string $context, string $domain ): string {
		if ( null === $context || self::D4_DOMAIN !== $domain ) {
			return $translation;
		}

		$source_text = 1 === $number ? $single : $plural;

		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingle,WordPress.WP.I18n.NonSingularStringLiteralPlural,WordPress.WP.I18n.NonSingularStringLiteralContext -- Runtime bridge translates dynamic legacy plural strings.
		$bridged = _nx( $single, $plural, $number, $context, 'et_builder_5' );

		return $bridged !== $source_text ? $bridged : $translation;
	}
}

D4DomainBridge::init();
