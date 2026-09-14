<?php
/**
 * FeaturesManager class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\FeaturesManager;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
// TODO feat(D5, Feature Manager): Will be developed. Porting/refactor from `ET_Builder_Module_Features` class in D4.

/**
 * FeaturesManager class.
 *
 * This class provides methods to retrieve features and handle related operations.
 *
 * @since ??
 */
class FeaturesManager {

	use FeaturesManagerTraits\GetTrait;
}
