<?php
/**
 * Gutenberg: Layout Block Registration
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Gutenberg;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// Register core Layout Block.
LayoutBlock::register_block();
LayoutBlock::register_hooks();
