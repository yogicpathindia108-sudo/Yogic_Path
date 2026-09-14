<?php
/**
 * SimpleBlockParserStore class file.
 *
 * This file contains the SimpleBlockParserStore class which provides a storage
 * mechanism for managing collections of parsed SimpleBlock objects during the
 * block parsing process.
 *
 * @package ET\Builder\FrontEnd\BlockParser
 * @since   ??
 */

namespace ET\Builder\FrontEnd\BlockParser;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\FrontEnd\BlockParser\SimpleBlock;
use ET\Builder\Framework\Utility\ArrayUtility;
use Countable;
use Iterator;

/**
 * Simple Block Parser Store class.
 *
 * A storage container for managing collections of parsed SimpleBlock objects.
 * This class provides a simple interface for adding blocks to a collection
 * and retrieving the complete collection when needed.
 *
 * The store is designed to be lightweight and efficient for use during the
 * block parsing process, allowing for incremental addition of blocks as they
 * are processed.
 *
 * Implements Iterator to allow direct iteration over the stored blocks using
 * foreach loops and other iteration constructs. Implements Countable to enable
 * using the count() function directly on store instances.
 *
 * Usage Examples:
 *
 *     // Initialize with blocks.
 *     $store = new SimpleBlockParserStore( $initial_blocks );
 *
 *     // Add blocks incrementally.
 *     $store->add( $new_block );
 *
 *     // Find a specific block.
 *     $block = $store->find( function( $block ) {
 *         return 'divi/cta' === $block->blockName;
 *     } );
 *
 *     // Filter blocks by criteria.
 *     $filtered_store = $store->filter( function( $block ) {
 *         return 'divi/section' === $block->blockName;
 *     } );
 *
 *     // Iterate over blocks.
 *     foreach ( $store as $index => $block ) {
 *         // Process each block.
 *     }
 *
 *     // Get block count.
 *     $total = count( $store );
 *
 *     // Get all blocks as array.
 *     $all_blocks = $store->results();
 *
 * @since ??
 *
 * @see SimpleBlock For the block objects stored in this collection.
 * @see Iterator For iteration capabilities.
 * @see Countable For counting capabilities.
 */
class SimpleBlockParserStore implements Iterator, Countable {

	/**
	 * The collection of parsed blocks.
	 *
	 * An array containing SimpleBlock objects that have been added to this store.
	 * The array maintains insertion order and can contain any number of blocks.
	 *
	 * @since ??
	 *
	 * @var SimpleBlock[] Array of SimpleBlock objects.
	 */
	private $_results;

	/**
	 * Current position in the iteration.
	 *
	 * Tracks the current index when iterating through the blocks collection.
	 * Used by the Iterator interface methods.
	 *
	 * @since ??
	 *
	 * @var int Current iteration position.
	 */
	private $_position = 0;

	/**
	 * Constructor.
	 *
	 * Initializes the store with an optional collection of SimpleBlock objects.
	 * If no blocks are provided, the store will be initialized as empty and
	 * blocks can be added later using the add() method.
	 *
	 * @since ??
	 *
	 * @param SimpleBlock[] $results Initial collection of parsed blocks. Can be
	 *                               an empty array if starting with no blocks.
	 *
	 * @see add() For adding blocks after initialization.
	 */
	public function __construct( array $results ) {
		$this->_results = $results;
	}

	/**
	 * Add a new block to the collection.
	 *
	 * Appends a SimpleBlock object to the end of the current collection.
	 * The block will be added in the order it was received, maintaining
	 * insertion order within the store. This will increment the collection
	 * count and make the block available during iteration.
	 *
	 * @since ??
	 *
	 * @param SimpleBlock $block The block object to add to the collection.
	 *                           Must be a valid SimpleBlock instance.
	 *
	 * @return void
	 *
	 * @see results() For retrieving the complete collection including added blocks.
	 * @see count() For getting the total number of blocks after addition.
	 */
	public function add( SimpleBlock $block ) {
		$this->_results[] = $block;
	}

	/**
	 * Get the complete collection of stored blocks.
	 *
	 * Returns all SimpleBlock objects currently stored in this collection,
	 * including both blocks provided during initialization and any blocks
	 * added subsequently via the add() method.
	 *
	 * The returned array maintains the original insertion order and contains
	 * references to the actual SimpleBlock objects (not copies).
	 *
	 * This method is useful when you need the blocks as an array for operations
	 * like array_map() or array_filter(). For sequential access, you can also
	 * iterate directly over the store object using foreach.
	 *
	 * @since ??
	 *
	 * @return SimpleBlock[] The complete collection of parsed blocks.
	 *
	 * @see add() For adding blocks to the collection.
	 * @see count() For getting the number of blocks without retrieving them.
	 * @see current() For accessing blocks during iteration.
	 */
	public function results(): array {
		return $this->_results;
	}

	/**
	 * Find the first block that matches the test function.
	 *
	 * Searches through all SimpleBlock objects in the collection and returns
	 * the first block that satisfies the provided test function. If no blocks
	 * match, returns null.
	 *
	 * The test function receives three parameters:
	 * - SimpleBlock $block: The current block being tested
	 * - int $index: The current index in the collection
	 * - SimpleBlock[] $blocks: The complete array of blocks
	 *
	 * The test function should return true when a match is found.
	 *
	 * Example usage:
	 *
	 *     // Find a block by name.
	 *     $cta_block = $store->find( function( $block ) {
	 *         return 'divi/cta' === $block->blockName;
	 *     } );
	 *
	 *     // Find a block with specific attribute.
	 *     $block = $store->find( function( $block ) {
	 *         return isset( $block->attrs['custom_id'] ) && 'my-id' === $block->attrs['custom_id'];
	 *     } );
	 *
	 * @since ??
	 *
	 * @param callable $test_function Function that will be invoked to test each block.
	 *                                Must return true for a match to be found.
	 *
	 * @return SimpleBlock|null The first matching block or null if no match is found.
	 *
	 * @see results() For retrieving all blocks as an array.
	 * @see ArrayUtility::find() For the underlying implementation.
	 */
	public function find( callable $test_function ): ?SimpleBlock {
		return ArrayUtility::find( $this->_results, $test_function );
	}

	/**
	 * Filter blocks based on a test function.
	 *
	 * Creates and returns a new SimpleBlockParserStore containing only the blocks
	 * that satisfy the provided test function. This method does not modify the
	 * original store. If no blocks match the test function, an empty store is returned.
	 *
	 * The test function receives three parameters:
	 * - SimpleBlock $block: The current block being tested
	 * - int $index: The current index in the collection
	 * - SimpleBlock[] $blocks: The complete array of blocks
	 *
	 * The test function should return true to include the block in the result.
	 *
	 * This function is equivalent to PHP's array_filter() and JavaScript's
	 * Array.prototype.filter().
	 *
	 * Example usage:
	 *
	 *     // Filter blocks by name.
	 *     $cta_blocks = $store->filter( function( $block ) {
	 *         return 'divi/cta' === $block->blockName;
	 *     } );
	 *
	 *     // Filter blocks with specific attribute.
	 *     $blocks_with_id = $store->filter( function( $block ) {
	 *         return isset( $block->attrs['custom_id'] );
	 *     } );
	 *
	 *     // Filter and chain operations.
	 *     $visible_sections = $store->filter( function( $block ) {
	 *         return 'divi/section' === $block->blockName;
	 *     } );
	 *     foreach ( $visible_sections as $section ) {
	 *         // Process visible sections.
	 *     }
	 *
	 * @since ??
	 *
	 * @param callable $test_function Function that will be invoked to test each block.
	 *                                Must return true to include the block in the result.
	 *
	 * @return SimpleBlockParserStore A new store containing only matching blocks.
	 *
	 * @see find() For finding a single matching block.
	 * @see results() For retrieving all blocks as an array.
	 */
	public function filter( callable $test_function ): SimpleBlockParserStore {
		$filtered_results = array_filter(
			$this->_results,
			function ( $block, $index ) use ( $test_function ) {
				return $test_function( $block, $index, $this->_results );
			},
			ARRAY_FILTER_USE_BOTH
		);

		// Re-index the array to maintain sequential numeric keys.
		return new SimpleBlockParserStore( array_values( $filtered_results ) );
	}

	/**
	 * Get the number of blocks in the collection.
	 *
	 * Returns the total number of SimpleBlock objects currently stored in this
	 * collection, including both blocks provided during initialization and any
	 * blocks added subsequently via the add() method.
	 *
	 * This method implements the Countable interface, allowing the store to be
	 * used directly with PHP's count() function:
	 *
	 *     $total = count( $store );
	 *
	 * @since ??
	 *
	 * @return int The number of blocks in the collection.
	 *
	 * @see add() For adding blocks to the collection.
	 * @see results() For retrieving all blocks as an array.
	 */
	public function count(): int {
		return count( $this->_results );
	}

	/**
	 * Rewind the Iterator to the first element.
	 *
	 * Resets the internal position pointer to the beginning of the collection.
	 * This method is part of the Iterator interface and is automatically called
	 * at the start of a foreach loop. It should not typically be called directly.
	 *
	 * @since ??
	 *
	 * @return void
	 *
	 * @see Iterator::rewind() For interface documentation.
	 * @see current() For retrieving the current element.
	 * @see valid() For checking if the current position is valid.
	 */
	public function rewind(): void {
		$this->_position = 0;
	}

	/**
	 * Return the current element.
	 *
	 * Returns the SimpleBlock object at the current iteration position. This
	 * method is part of the Iterator interface and is automatically called during
	 * foreach iteration. It should not typically be called directly.
	 *
	 * @since ??
	 *
	 * @return SimpleBlock|null The current block or null if position is invalid.
	 *
	 * @see Iterator::current() For interface documentation.
	 * @see key() For retrieving the current position index.
	 * @see valid() For checking if the current position is valid.
	 */
	public function current(): ?SimpleBlock {
		return $this->_results[ $this->_position ] ?? null;
	}

	/**
	 * Return the key of the current element.
	 *
	 * Returns the current numeric index in the blocks collection. This method
	 * is part of the Iterator interface and is automatically called during
	 * foreach iteration to provide the iteration key. It should not typically
	 * be called directly.
	 *
	 * @since ??
	 *
	 * @return int The current position index.
	 *
	 * @see Iterator::key() For interface documentation.
	 * @see current() For retrieving the current element.
	 */
	public function key(): int {
		return $this->_position;
	}

	/**
	 * Move forward to next element.
	 *
	 * Advances the internal position pointer to the next element in the
	 * collection. This method is part of the Iterator interface and is
	 * automatically called at the end of each iteration in a foreach loop.
	 * It should not typically be called directly.
	 *
	 * @since ??
	 *
	 * @return void
	 *
	 * @see Iterator::next() For interface documentation.
	 * @see valid() For checking if the next position is valid.
	 * @see current() For retrieving the element at the new position.
	 */
	public function next(): void {
		++$this->_position;
	}

	/**
	 * Check if current position is valid.
	 *
	 * Determines whether the current position points to a valid element in the
	 * collection. Returns false when iteration has completed. This method is
	 * part of the Iterator interface and is automatically called during foreach
	 * iteration to determine if the loop should continue. It should not typically
	 * be called directly.
	 *
	 * @since ??
	 *
	 * @return bool True if the current position is valid, false otherwise.
	 *
	 * @see Iterator::valid() For interface documentation.
	 * @see current() For retrieving the current element.
	 * @see next() For advancing to the next element.
	 */
	public function valid(): bool {
		return isset( $this->_results[ $this->_position ] );
	}
}
