<?php
/**
 * Migration Presets Base Class
 *
 * Abstract base class that provides a foundation for preset migration implementations.
 * This class implements both MigrationInterface and MigrationPresetsInterface to ensure
 * that all preset migration classes follow a consistent structure and provide the
 * required functionality for migrating preset data between different Divi versions.
 *
 * Concrete implementations of this class should provide specific migration logic
 * for different types of presets, such as module presets, global presets, or
 * custom preset configurations.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Migration\MigrationInterface;
use ET\Builder\Migration\MigrationPresetsInterface;

/**
 * Abstract base class for preset migration implementations.
 *
 * This class serves as a foundation for all preset migration classes, ensuring
 * they implement the required interfaces and follow consistent patterns. Concrete
 * implementations should extend this class and provide specific migration logic
 * for their respective preset types.
 *
 * The class implements both MigrationInterface and MigrationPresetsInterface,
 * requiring implementing classes to provide methods for:
 * - General migration functionality (from MigrationInterface)
 * - Preset-specific migration methods (from MigrationPresetsInterface)
 *
 * @since ??
 */
abstract class MigrationPresetsBase implements MigrationInterface, MigrationPresetsInterface {
}
