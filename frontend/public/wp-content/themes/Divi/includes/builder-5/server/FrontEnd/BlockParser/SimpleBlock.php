<?php
/**
 * Simple Block Parser for Divi 5 Front End.
 *
 * This file contains the SimpleBlock class which is responsible for parsing
 * and managing individual block data within the Divi 5 block parser system.
 *
 * @package    ET\Builder\FrontEnd\BlockParser
 * @subpackage SimpleBlock
 * @since      5.0.0
 * @author     Elegant Themes
 */

namespace ET\Builder\FrontEnd\BlockParser;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Simple Block class for parsing and managing individual block data.
 *
 * This class represents a single parsed block within the Divi 5 block parser system.
 * It encapsulates block data including raw content, name, attributes, JSON representation,
 * and error status, providing a consistent interface for accessing block information.
 *
 * @since 5.0.0
 */
class SimpleBlock {

	/**
	 * Original block string being parsed.
	 *
	 * Contains the raw, unparsed block content as it appears in the post content
	 * before any processing or transformation.
	 *
	 * @since 5.0.0
	 *
	 * @var string
	 */
	private $_raw;

	/**
	 * Name of block.
	 *
	 * The block type identifier used to determine which module or component
	 * this block represents in the Divi system.
	 *
	 * @since 5.0.0
	 *
	 * @example "divi/section"
	 *
	 * @var string
	 */
	private $_name;

	/**
	 * Attributes of block.
	 *
	 * An associative array containing all the configuration data and settings
	 * for this specific block instance.
	 *
	 * @since 5.0.0
	 *
	 * @var array
	 */
	private $_attrs;

	/**
	 * JSON representation of block.
	 *
	 * The JSON-encoded string representation of the block data, typically used
	 * for serialization and data transfer.
	 *
	 * @since 5.0.0
	 *
	 * @var string
	 */
	private $_json;

	/**
	 * Error status of block parsing.
	 *
	 * Indicates whether an error occurred during the parsing process of this block.
	 * True if there was an error, false if parsing was successful.
	 *
	 * @since 5.0.0
	 *
	 * @var bool
	 */
	private $_error;

	/**
	 * Constructor for SimpleBlock.
	 *
	 * Initializes a new SimpleBlock instance with the provided block data.
	 * All properties are set from the data array passed to the constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param array $data {
	 *     Block data array containing parsed block information.
	 *
	 *     @type string $raw   Original raw block string.
	 *     @type string $name  Block type name/identifier.
	 *     @type array  $attrs Block attributes and configuration.
	 *     @type string $json  JSON representation of block data.
	 *     @type bool   $error Whether parsing encountered an error.
	 * }
	 */
	public function __construct( array $data ) {
		$this->_raw   = $data['raw'];
		$this->_name  = $data['name'];
		$this->_attrs = $data['attrs'];
		$this->_json  = $data['json'];
		$this->_error = $data['error'];
	}

	/**
	 * Get the raw block string.
	 *
	 * Returns the original, unparsed block content as it appeared in the post content.
	 *
	 * @since 5.0.0
	 *
	 * @return string The raw block string, or empty string if not set.
	 */
	public function raw(): string {
		return $this->_raw ?? '';
	}

	/**
	 * Get the block name.
	 *
	 * Returns the block type identifier that determines which Divi module
	 * or component this block represents.
	 *
	 * @since 5.0.0
	 *
	 * @return string The block name/type, or empty string if not set.
	 */
	public function name(): string {
		return $this->_name ?? '';
	}

	/**
	 * Get the block attributes.
	 *
	 * Returns the associative array containing all configuration data
	 * and settings for this block instance.
	 *
	 * @since 5.0.0
	 *
	 * @return array The block attributes array, or empty array if not set.
	 */
	public function attrs(): array {
		return $this->_attrs ?? [];
	}

	/**
	 * Get the block JSON representation.
	 *
	 * Returns the JSON-encoded string representation of the block data,
	 * typically used for serialization and data transfer operations.
	 *
	 * @since 5.0.0
	 *
	 * @return string The block JSON string, or empty string if not set.
	 */
	public function json(): string {
		return $this->_json ?? '';
	}

	/**
	 * Get the block error status.
	 *
	 * Returns whether an error occurred during the parsing process of this block.
	 *
	 * @since 5.0.0
	 *
	 * @return bool True if there was a parsing error, false if successful.
	 */
	public function error(): bool {
		return $this->_error ?? false;
	}
}
