<?php
/**
 * ModuleStyleLibrary\StyleDeclarations class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * StyleDeclarations class is a helper class with methods to work with the style library.
 *
 * This class is equivalent of JS class:
 * {@link /docs/builder-api/js/style-library/style-declarations} in
 * `@divi/style-library` package.
 *
 * ## Relationship to issue #29230 (gradient seam fix)
 *
 * The Chrome gradient-seam fix (issue #29230) required inspecting the already-accumulated
 * `background-image` value inside `StyleDeclarationTrait::style_declaration()` before the
 * declarations were serialised.  The previous flat `string` storage format did not expose
 * individual property values after they were added, so two new accessor methods were needed:
 *
 *   - `has_property( 'background-image' )` – guards the seam-fix branch so it runs only when
 *     a `background-image` has already been queued.
 *   - `get_property_value( 'background-image' )` – reads the queued value so the fix can check
 *     whether it contains `linear-gradient(` but not `url(`.
 *
 * Both accessors require per-property structured storage, so `$_declarations` was changed from
 * a flat `property => value-string` map to a `property => { value, important }` record map.
 * The private `$_compiled_declarations` field and `reset()` were added to avoid re-serialising
 * the same declarations multiple times when the caller reads `value()`.
 *
 * ## Call chain for the #29230 seam fix
 *
 * ```
 * StyleDeclarationTrait::style_declaration()        (Background/Traits/StyleDeclarationTrait.php)
 *   └─ $style_declarations->add( 'background-image', … )   // queues gradient value
 *   └─ $style_declarations->has_property( 'background-image' )      // guard check
 *   └─ $style_declarations->get_property_value( 'background-image' ) // gradient check
 *   └─ $style_declarations->add( 'background-repeat', 'no-repeat' ) // seam fix
 *   └─ $style_declarations->value()                 // serialise to CSS string
 * ```
 *
 * @since ??
 */
class StyleDeclarations {

	/**
	 * This is the type of value that the function will return. Can be either string or key_value_pair.
	 *
	 * @var string
	 */
	private $_return_type;

	/**
	 * A parameter to add !important statement.
	 *
	 * @var bool|array
	 */
	private $_important;

	/**
	 * Declarations data stored as declaration item array.
	 *
	 * @var array
	 */
	private $_declarations = [];

	/**
	 * Cached compiled declarations.
	 *
	 * @var array|string|null
	 */
	private $_compiled_declarations = null;

	/**
	 * Create an instance of StyleDeclarations class.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type bool|array $important  Optional. Whether to add `!important` tag. Default `false`.
	 *     @type string     $returnType Optional.
	 *                                  This is the type of value that the function will return.
	 *                                  Can be either `string` or `key_value_pair`. Default `string`.
	 * }
	 */
	public function __construct( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'important'  => false,
				'returnType' => 'string',
			]
		);

		$this->_important   = $args['important'];
		$this->_return_type = $args['returnType'];
		$this->reset();
	}

	/**
	 * Add declaration's property and value.
	 *
	 * @since ??
	 *
	 * @param string $property The CSS property to add.
	 * @param string $value    The value of the property.
	 *
	 * @return void
	 */
	public function add( string $property, string $value ): void {
		$processed_value = Utils::resolve_dynamic_variable( $value );

		$is_important = is_array( $this->_important )
			? ( isset( $this->_important[ $property ] ) ? $this->_important[ $property ] : false )
			: $this->_important;

		$this->_declarations[] = [
			'property'  => $property,
			'value'     => $processed_value,
			'important' => (bool) $is_important,
		];

		$this->reset();
	}

	/**
	 * Reset cached compiled declarations.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->_compiled_declarations = null;
	}

	/**
	 * Check if a property is exists.
	 *
	 * Used by the #29230 gradient-seam fix to guard the `background-repeat: no-repeat`
	 * injection so it runs only when a `background-image` has already been queued.
	 *
	 * @since ??
	 *
	 * @param string $property The property to check.
	 * @param string $value    Optional. The value to check.
	 *
	 * @return bool True when the property exists, false otherwise.
	 */
	public function has_property( string $property, ?string $value = null ): bool {
		if ( null === $value ) {
			foreach ( $this->_declarations as $declaration ) {
				if ( $property === $declaration['property'] ) {
					return true;
				}
			}

			return false;
		}

		$processed_value = Utils::resolve_dynamic_variable( $value );

		foreach ( $this->_declarations as $declaration ) {
			if ( $property === $declaration['property'] && $processed_value === $declaration['value'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get a property value if available.
	 *
	 * Used by the #29230 gradient-seam fix to read the already-queued `background-image`
	 * value so the caller can verify it contains `linear-gradient(` but not `url(` before
	 * injecting `background-repeat: no-repeat`.
	 *
	 * @since ??
	 *
	 * @param string $property The property to get the value of.
	 *
	 * @return array The property values. Empty array when property is missing.
	 */
	public function get_property_value( string $property ): array {
		$values = [];

		foreach ( $this->_declarations as $declaration ) {
			if ( $property === $declaration['property'] ) {
				$values[] = $declaration['value'];
			}
		}

		return $values;
	}

	/**
	 * Get style declaration.
	 *
	 * Returns either array of declarations or string of declarations based on the specified return type.
	 *
	 * @since ??
	 *
	 * @return array|string|null Returns either array of declarations or string of declarations based on the specified return type.
	 */
	public function value() {
		if ( null !== $this->_compiled_declarations ) {
			return $this->_compiled_declarations;
		}

		$this->_compiled_declarations = 'key_value_pair' === $this->_return_type
			? self::compile_declarations_to_key_value_pair( $this->_declarations )
			: self::compile_declarations_to_string( $this->_declarations );

		return $this->_compiled_declarations;
	}

	/**
	 * Compile declaration items into key/value style pairs.
	 *
	 * Mirrors compileDeclarationsToKeyValuePair from the visual builder style library.
	 *
	 * @since ??
	 *
	 * @param array $declarations Array of declaration items.
	 *
	 * @return array Compiled declaration object keyed by the requested format.
	 */
	public static function compile_declarations_to_key_value_pair( array $declarations ): array {
		$compiled_style_object = [];

		foreach ( $declarations as $declaration ) {
			$property_name = isset( $declaration['property'] ) ? (string) $declaration['property'] : '';

			$declaration_value_with_priority = isset( $declaration['value'] ) ? (string) $declaration['value'] : '';

			if ( ! empty( $declaration['important'] ) ) {
				$declaration_value_with_priority .= ' !important';
			}

			$compiled_style_object[ $property_name ] = $declaration_value_with_priority;
		}

		return $compiled_style_object;
	}

	/**
	 * Compile declaration items into a CSS declaration string.
	 *
	 * Mirrors compileDeclarationsToString from the visual builder style library.
	 *
	 * @since ??
	 *
	 * @param array $declarations Array of declaration items.
	 *
	 * @return string Compiled declaration string.
	 */
	public static function compile_declarations_to_string( array $declarations ): string {
		$compiled_declarations = [];

		foreach ( $declarations as $declaration ) {
			$property_name                  = isset( $declaration['property'] ) ? (string) $declaration['property'] : '';
			$declaration_value_with_priority = isset( $declaration['value'] ) ? (string) $declaration['value'] : '';

			if ( ! empty( $declaration['important'] ) ) {
				$declaration_value_with_priority .= ' !important';
			}

			$compiled_declarations[] = $property_name . ': ' . $declaration_value_with_priority;
		}

		return Utils::join_declarations( $compiled_declarations );
	}
}
