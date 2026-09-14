<?php
/**
 * Migration Manager Class
 *
 * This class manages the execution of multiple migrations in sequence
 * through a fluent interface. It handles different types of migrations including
 * preset migrations, and content migrations. The class
 * provides methods to register migrations, execute them in version order,
 * and migrate specific content types like shortcodes and blocks.
 *
 * The class implements performance optimizations through caching mechanisms
 * for preset and content migrations, ensuring efficient execution of
 * migration operations while maintaining proper version ordering.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Migration\MigrationInterface;
use ET\Builder\Migration\MigrationPresetsBase;
use ET\Builder\Migration\MigrationContentBase;
use ET\Builder\Migration\FlexboxMigration;
use ET\Builder\Migration\FullwidthPortfolioMigration;
use ET\Builder\Migration\GlobalColorMigration;
use ET\Builder\Migration\AttributeMigration;
use ET\Builder\Migration\AttributePresetMigration;
use ET\Builder\Migration\NestedModuleMigration;
use ET\Builder\Migration\NestedModulePresetMigration;
use ET\Builder\Migration\PresetStackMigration;
use ET\Builder\Migration\DynamicContentPostIdMigration;
use ET\Builder\Migration\EmptyArrayCorruptionMigration;
use ET\Builder\Migration\ComposibleOptionsMigration;
use ET\Builder\Migration\ComposibleOptionsPresetMigration;
use ET\Builder\Migration\ImageGroupMigration;
use ET\Builder\Migration\ImageGroupPresetMigration;
use ET\Builder\Migration\FocusFieldsMigration;
use ET\Builder\Migration\FocusFieldsPresetMigration;
use ET\Builder\Migration\FontCapitalizationMigration;
use ET\Builder\Migration\FontCapitalizationPresetMigration;
use ET\Builder\Migration\TextBodyFontWeightMigration;
use ET\Builder\Migration\ToggleTitleTextColorMigration;
use ET\Builder\Migration\ToggleTitleTextColorPresetMigration;
use ET\Builder\Migration\GridOffsetRulesMigration;
use ET\Builder\Migration\GridOffsetRulesPresetMigration;
use ET\Builder\Migration\Utils\MigrationUtils;
use ET\Builder\Framework\Utility\StringUtility;

/**
 * Migration class for handling sequential migration execution.
 *
 * @since ??
 */
class Migration {

	/**
	 * Stores the migration classes to be executed.
	 *
	 * @since ??
	 * @deprecated 5.0.0-public-alpha.24.1
	 *
	 * @var array
	 */
	private $_migrations = [];

	/**
	 * Stores the presets migration classes to be executed.
	 *
	 * @since ??
	 *
	 * @var array<MigrationPresetsBase>
	 */
	private $_presets_migrations = [];

	/**
	 * Cached presets migrations for performance optimization.
	 *
	 * @since ??
	 *
	 * @var array<MigrationPresetsBase>|null
	 */
	private $_presets_migrations_cache;

	/**
	 * Cached latest preset migration version.
	 *
	 * @since ??
	 *
	 * @var string|null
	 */
	private $_latest_presets_migration_version_cache;

	/**
	 * Stores the content migration classes to be executed.
	 *
	 * @since ??
	 *
	 * @var array<MigrationContentBase>
	 */
	private $_content_migrations = [];

	/**
	 * Cached content migrations for performance optimization.
	 *
	 * The data here is already sorted by version and applied filters.
	 *
	 * @since ??
	 *
	 * @var array<MigrationContentBase>|null
	 */
	private $_content_migrations_cache;

	/**
	 * Apply a specific migration and return $this for method chaining.
	 *
	 * @since ??
	 *
	 * @param string $migration_class The migration class name to run.
	 * @return self
	 * @deprecated 5.0.0-public-alpha.24.1
	 */
	public function apply( string $migration_class ): self {
		// Add deprecation notice.
		_deprecated_function( __METHOD__, '5.0.0-public-alpha.24.1', 'Migration::register_preset_migration|Migration::register_content_migration' );

		$this->_migrations[] = $migration_class;

		$migration_class_instance = new $migration_class();

		// Check if migration class handles presets migrations and add to presets array.
		if ( method_exists( $migration_class_instance, 'migrate_preset_item' )
			|| method_exists( $migration_class_instance, 'migrate_presets' ) ) {
			$this->_presets_migrations[] = $migration_class_instance;
			$this->_clear_presets_migrations_cache();
		}

		// Check if migration class handles content migrations and add to content array.
		if ( method_exists( $migration_class_instance, 'migrate_content_shortcode' )
			|| method_exists( $migration_class_instance, 'migrate_content_block' )
			|| method_exists( $migration_class_instance, 'migrate_content_both' ) ) {
			$this->_content_migrations[] = $migration_class_instance;
			$this->_clear_content_migrations_cache();
		}

		return $this;
	}

	/**
	 * Register a preset migration class for execution.
	 *
	 * @since ??
	 *
	 * @param MigrationPresetsBase $migration_class The preset migration class to register.
	 * @return self
	 */
	public function register_presets_migration( MigrationPresetsBase $migration_class ): self {
		$this->_presets_migrations[] = $migration_class;
		$this->_clear_presets_migrations_cache();

		return $this;
	}

	/**
	 * Register a content migration class for execution.
	 *
	 * @since ??
	 *
	 * @param MigrationContentBase $migration_class The content migration class to register.
	 * @return self
	 */
	public function register_content_migration( MigrationContentBase $migration_class ): self {
		$this->_content_migrations[] = $migration_class;
		$this->_clear_content_migrations_cache();

		return $this;
	}

	/**
	 * Get cached presets migrations with performance optimization.
	 *
	 * This method retrieves presets migrations from cache or builds the cache
	 * by merging registered presets migrations with migrations that have presets
	 * migration methods. The result is sorted by version and filtered.
	 *
	 * @since ??
	 *
	 * @return array<MigrationPresetsBase> Array of presets migration classes.
	 */
	private function _get_presets_migrations(): array {
		if ( ! is_array( $this->_presets_migrations_cache ) ) {
			/**
			 * Filters the presets migrations before they are sorted and cached.
			 *
			 * This filter allows developers to modify, add, or remove presets migrations
			 * that will be executed during the migration process. The filtered migrations
			 * are then sorted by version and cached for performance.
			 *
			 * @since ??
			 *
			 * @param array<MigrationPresetsBase> $presets_migrations Array of presets migration classes.
			 *                                                      Each migration class must extend MigrationPresetsBase
			 *                                                      and implement the required migration methods.
			 *
			 * @return array<MigrationPresetsBase> Modified array of presets migration classes.
			 */
			$filtered_migrations = apply_filters( 'divi_migration_preset_migrations', $this->_presets_migrations );

			// Set the sorted migrations to the cache.
			$this->_presets_migrations_cache = $this->sort_migrations_by_version( $filtered_migrations );
		}

		return $this->_presets_migrations_cache;
	}

	/**
	 * Get cached content migrations with performance optimization.
	 *
	 * This method retrieves content migrations from cache or builds the cache
	 * by merging registered content migrations with migrations that have content
	 * migration methods. The result is sorted by version and filtered.
	 *
	 * @since ??
	 *
	 * @return array<MigrationContentBase> Array of content migration classes.
	 */
	private function _get_content_migrations(): array {
		if ( ! is_array( $this->_content_migrations_cache ) ) {
			/**
			 * Filters the content migrations before they are sorted and cached.
			 *
			 * This filter allows developers to modify, add, or remove content migrations
			 * that will be executed during the migration process. The filtered migrations
			 * are then sorted by version and cached for performance.
			 *
			 * @since ??
			 *
			 * @param array<MigrationContentBase> $content_migrations Array of content migration classes.
			 *                                                       Each migration class must extend MigrationContentBase
			 *                                                       and implement the required migration methods.
			 *
			 * @return array<MigrationContentBase> Modified array of content migration classes.
			 */
			$filtered_migrations = apply_filters( 'divi_migration_content_migrations', $this->_content_migrations );

			// Set the sorted migrations to the cache.
			$this->_content_migrations_cache = $this->sort_migrations_by_version( $filtered_migrations );
		}

		return $this->_content_migrations_cache;
	}

	/**
	 * Clear the preset migrations cache.
	 *
	 * This method clears the cached preset migrations, forcing them to be
	 * rebuilt on the next access. Useful when new migrations are registered.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private function _clear_presets_migrations_cache(): void {
		$this->_presets_migrations_cache               = null;
		$this->_latest_presets_migration_version_cache = null;
	}

	/**
	 * Get latest registered preset migration release version.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public function get_latest_preset_migration_version(): string {
		if ( is_string( $this->_latest_presets_migration_version_cache ) ) {
			return $this->_latest_presets_migration_version_cache;
		}

		$latest_version = '0.0.0';
		$migrations     = $this->_get_presets_migrations();

		foreach ( $migrations as $migration_class ) {
			$release_version = $migration_class::get_release_version();
			if ( StringUtility::version_compare( $release_version, $latest_version, '>' ) ) {
				$latest_version = $release_version;
			}
		}

		$this->_latest_presets_migration_version_cache = $latest_version;

		return $latest_version;
	}

	/**
	 * Determine whether preset item might need migration.
	 *
	 * @since ??
	 *
	 * @param array $preset_item Preset item.
	 *
	 * @return bool
	 */
	public function preset_item_needs_migration( array $preset_item ): bool {
		$preset_version = $preset_item['version'] ?? '0.0.0';
		if ( ! is_string( $preset_version ) || '' === $preset_version ) {
			$preset_version = '0.0.0';
		}

		return StringUtility::version_compare(
			$preset_version,
			$this->get_latest_preset_migration_version(),
			'<'
		);
	}

	/**
	 * Clear the content migrations cache.
	 *
	 * This method clears the cached content migrations, forcing them to be
	 * rebuilt on the next access. Useful when new migrations are registered.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private function _clear_content_migrations_cache(): void {
		$this->_content_migrations_cache = null;
	}

	/**
	 * Sort migrations by release version.
	 *
	 * @since ??
	 *
	 * @param array<MigrationInterface> $migrations Array of migration class names.
	 *
	 * @return array<MigrationInterface> Sorted array of migration class names.
	 */
	public function sort_migrations_by_version( array $migrations ): array {
		$sorted_migrations = $migrations;

		usort(
			$sorted_migrations,
			function ( $a, $b ) {
				return StringUtility::version_compare(
					$a::get_release_version(),
					$b::get_release_version()
				);
			}
		);

		return $sorted_migrations;
	}

	/**
	 * Execute all registered migrations in sequence, sorted by version.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function execute(): void {
		$presets_migrations = $this->_get_presets_migrations();

		foreach ( $presets_migrations as $migration_class ) {
			$migration_class::load();
		}

		$content_migrations = $this->_get_content_migrations();

		foreach ( $content_migrations as $migration_class ) {
			$migration_class::load();
		}

		// Finalize any deferred shared-pipeline migration once per hook execution.
		// For frontend render filters, finalize before do_blocks() so output is rendered HTML, not raw blocks.
		add_filter( 'divi_framework_portability_import_migrated_post_content', [ __CLASS__, 'finalize_shared_pipeline_content' ], PHP_INT_MAX );
		add_filter( 'the_content', [ __CLASS__, 'finalize_shared_pipeline_content' ], 8 );
		add_filter( 'et_builder_render_layout', [ __CLASS__, 'finalize_shared_pipeline_content' ], 8 );
		add_filter( 'et_fb_load_raw_post_content', [ __CLASS__, 'finalize_shared_pipeline_content' ], PHP_INT_MAX, 2 );
	}

	/**
	 * Finalize deferred shared-pipeline content migration.
	 *
	 * @since ??
	 *
	 * @param string $content Content to finalize.
	 *
	 * @return string
	 */
	public static function finalize_shared_pipeline_content( $content ): string {
		if ( ! is_string( $content ) ) {
			return '';
		}

		return MigrationUtils::finalize_shared_pipeline( $content );
	}

	/**
	 * Execute only preset migrations from the registered migrations.
	 *
	 * This method finds all registered migrations that have a migrate_presets() method
	 * and executes them in version order. Used for contexts like D4 to D5 conversion
	 * where preset migrations need to run immediately.
	 *
	 * @since ??
	 *
	 * @return bool True if preset migrations were executed, false if none found.
	 */
	public function execute_preset_migrations(): bool {
		$migrations = $this->_get_presets_migrations();

		// Execute each preset migration.
		foreach ( $migrations as $migration_class ) {
			if ( method_exists( $migration_class, 'migrate_presets' ) ) {
				$migration_class::migrate_presets();
			}
		}

		return true;
	}

	/**
	 * Migrate individual preset data through all applicable migrations.
	 *
	 * This method applies all registered migrations that have a migrate_preset_item() method
	 * to a single preset item. Used for normalizing preset data during duplicate detection
	 * so that newly imported presets can be compared against existing migrated presets.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item The preset item to migrate.
	 * @param string $module_name The module name for this preset.
	 *
	 * @return array The migrated preset item.
	 */
	public function migrate_preset_item( array $preset_item, string $module_name ): array {
		$migrations = $this->_get_presets_migrations();

		// Apply each migration to the preset item.
		$migrated_preset = $preset_item;
		foreach ( $migrations as $migration_class ) {
			if ( method_exists( $migration_class, 'migrate_preset_item' ) ) {
				$migrated_preset = $migration_class::migrate_preset_item( $migrated_preset, $module_name );
			}
		}

		return $migrated_preset;
	}

	/**
	 * Migrate content containing shortcodes through all applicable migrations.
	 *
	 * This method applies all registered migrations that have a migrate_content_shortcode() method
	 * to content containing shortcodes. Used for migrating shortcode-based content from older
	 * Divi versions to newer formats.
	 *
	 * @since ??
	 *
	 * @param string $content The content containing shortcodes to migrate.
	 *
	 * @return string The migrated content with updated shortcodes.
	 */
	public function migrate_content_shortcode( string $content ): string {
		$migrations = $this->_get_content_migrations();

		foreach ( $migrations as $migration_class ) {
			if ( method_exists( $migration_class, 'migrate_content_shortcode' ) ) {
				$content = $migration_class::migrate_content_shortcode( $content );
			}
		}

		return $content;
	}

	/**
	 * Migrate content containing blocks through all applicable migrations.
	 *
	 * This method applies all registered migrations that have a migrate_content_block() method
	 * to content containing blocks. Used for migrating block-based content from older
	 * Divi versions to newer formats.
	 *
	 * @since ??
	 *
	 * @param string $content The content containing blocks to migrate.
	 *
	 * @return string The migrated content with updated blocks.
	 */
	public function migrate_content_block( string $content ): string {
		$migrations = $this->_get_content_migrations();
		MigrationUtils::begin_shared_pipeline( $content );

		try {
			foreach ( $migrations as $migration_class ) {
				if ( method_exists( $migration_class, 'migrate_content_block' ) ) {
					$content = $migration_class::migrate_content_block( $content );
				}
			}
		} finally {
			$content = MigrationUtils::finalize_shared_pipeline( $content );
		}

		return $content;
	}

	/**
	 * Migrate content containing both shortcodes and blocks through all applicable migrations.
	 *
	 * This method applies all registered migrations that have a migrate_content_both() method
	 * to content that may contain both shortcodes and blocks. Used for migrating mixed content
	 * from older Divi versions to newer formats.
	 *
	 * @since ??
	 *
	 * @param string $content The content containing both shortcodes and blocks to migrate.
	 *
	 * @return string The migrated content with updated shortcodes and blocks.
	 */
	public function migrate_content_both( string $content ): string {
		$migrations = $this->_get_content_migrations();
		MigrationUtils::begin_shared_pipeline( $content );

		try {
			foreach ( $migrations as $migration_class ) {
				if ( method_exists( $migration_class, 'migrate_content_both' ) ) {
					$content = $migration_class::migrate_content_both( $content );
				}
			}
		} finally {
			$content = MigrationUtils::finalize_shared_pipeline( $content );
		}

		return $content;
	}

	/**
	 * Get a global instance of the migration manager.
	 *
	 * @since ??
	 *
	 * @return Migration The global migration instance.
	 */
	public static function get_instance(): Migration {
		static $instance = null;
		if ( null === $instance ) {
			$instance = self::_create_global_instance();
		}
		return $instance;
	}

	/**
	 * Create the global migration instance with all registered migrations.
	 *
	 * @since ??
	 *
	 * @return Migration The migration instance with all migrations registered.
	 */
	private static function _create_global_instance(): Migration {
		$migration = new Migration();

		// Register migrations here - single source of truth.
		$migration->register_content_migration( new FlexboxMigration() );
		$migration->register_content_migration( new NestedModuleMigration() );
		$migration->register_content_migration( new GlobalColorMigration() );
		$migration->register_content_migration( new AttributeMigration() );
		$migration->register_content_migration( new FullwidthPortfolioMigration() );
		$migration->register_content_migration( new PresetStackMigration() );
		$migration->register_content_migration( new DynamicContentPostIdMigration() );
		$migration->register_content_migration( new EmptyArrayCorruptionMigration() );
		$migration->register_content_migration( new ComposibleOptionsMigration() );
		$migration->register_content_migration( new ImageGroupMigration() );
		$migration->register_content_migration( new FocusFieldsMigration() );
		$migration->register_content_migration( new TextBodyFontWeightMigration() );
		$migration->register_content_migration( new FontCapitalizationMigration() );
		$migration->register_content_migration( new ToggleTitleTextColorMigration() );
		$migration->register_content_migration( new GridOffsetRulesMigration() );

		// Register preset migrations here.
		$migration->register_presets_migration( new AttributePresetMigration() );
		$migration->register_presets_migration( new NestedModulePresetMigration() );
		$migration->register_presets_migration( new ComposibleOptionsPresetMigration() );
		$migration->register_presets_migration( new ImageGroupPresetMigration() );
		$migration->register_presets_migration( new FocusFieldsPresetMigration() );
		$migration->register_presets_migration( new FontCapitalizationPresetMigration() );
		$migration->register_presets_migration( new ToggleTitleTextColorPresetMigration() );
		$migration->register_presets_migration( new GridOffsetRulesPresetMigration() );

		return $migration;
	}
}

// Execute all migrations using the global instance.
Migration::get_instance()->execute();
