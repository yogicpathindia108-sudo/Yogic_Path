<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender;

use CloudLinux\Imunify\App\DataStore;

/**
 * Manages disabled rules for the WordPress site.
 *
 * Handles local caching of disabled rules and synchronization with agent-managed files.
 *
 * @since 3.0.0
 */
class DisabledRulesManager extends CachedFileProvider {

	/**
	 * Disabled rules file name.
	 */
	const DISABLED_RULES_FILE_NAME = 'disabled-rules.php';

	/**
	 * Transient name for caching disabled rules.
	 */
	const TRANSIENT_KEY = 'imunify_disabled_rules';

	/**
	 * Changelog writer instance.
	 *
	 * @var ChangelogWriter
	 */
	private $changelogWriter;

	/**
	 * In-memory cache of disabled rules for the current request.
	 *
	 * @var array|null
	 */
	private $disabledRulesCache = null;

	/**
	 * Constructor.
	 *
	 * @param DataStore       $dataStore       Data store instance.
	 * @param ChangelogWriter $changelogWriter Changelog writer instance.
	 */
	public function __construct( DataStore $dataStore, ChangelogWriter $changelogWriter ) {
		$this->dataStore       = $dataStore;
		$this->changelogWriter = $changelogWriter;
	}

	/**
	 * Get the filename for this provider.
	 *
	 * @return string The filename.
	 */
	protected function getFileName() {
		return self::DISABLED_RULES_FILE_NAME;
	}

	/**
	 * Get the list of disabled rules.
	 *
	 * Uses a multi-level caching strategy:
	 * 1. In-memory cache for the current PHP request
	 * 2. WordPress transient with file stat to detect changes
	 *
	 * @return array List of disabled rule IDs.
	 */
	public function getDisabledRules() {
		// Return in-memory cache if already loaded this request.
		if ( null !== $this->disabledRulesCache ) {
			return $this->disabledRulesCache;
		}

		$cached      = get_transient( self::TRANSIENT_KEY );
		$currentStat = $this->getFileStat();

		// Check if file changed since we last synced.
		$fileChanged = $this->hasFileChanged( $cached, $currentStat );

		if ( $fileChanged && is_array( $currentStat ) ) {
			// File changed - reload and update cache.
			$rules = $this->loadFromFile();
			$this->saveToCache( $rules, $currentStat['mtime'], $currentStat['size'] );
			$this->disabledRulesCache = $rules;
			return $rules;
		}

		$this->disabledRulesCache = is_array( $cached ) && isset( $cached['rules'] ) ? $cached['rules'] : array();
		return $this->disabledRulesCache;
	}

	/**
	 * Check if a specific rule is disabled.
	 *
	 * @param string $ruleId The rule ID to check.
	 *
	 * @return bool True if the rule is disabled, false otherwise.
	 */
	public function isRuleDisabled( $ruleId ) {
		$disabledRules = $this->getDisabledRules();
		return in_array( $ruleId, $disabledRules, true );
	}

	/**
	 * Disable a rule.
	 *
	 * @param string $ruleId The rule ID to disable.
	 * @param int    $userId The WordPress user ID performing the action.
	 *
	 * @return bool True if the rule was disabled, false if it was already disabled.
	 */
	public function disableRule( $ruleId, $userId ) {
		$cached = get_transient( self::TRANSIENT_KEY );
		$rules  = is_array( $cached ) && isset( $cached['rules'] ) ? $cached['rules'] : array();

		if ( in_array( $ruleId, $rules, true ) ) {
			// Rule is already disabled.
			return false;
		}

		$rules[] = $ruleId;

		// Update rules but KEEP the old file_mtime/size.
		// When agent updates the file, mtime will change and trigger reload.
		$fileMtime = is_array( $cached ) && isset( $cached['file_mtime'] ) ? $cached['file_mtime'] : 0;
		$fileSize  = is_array( $cached ) && isset( $cached['file_size'] ) ? $cached['file_size'] : 0;

		$this->saveToCache( $rules, $fileMtime, $fileSize );
		$this->changelogWriter->writeAction( 'disable', $ruleId, $userId );
		$this->disabledRulesCache = $rules;

		return true;
	}

	/**
	 * Enable a previously disabled rule.
	 *
	 * @param string $ruleId The rule ID to enable.
	 * @param int    $userId The WordPress user ID performing the action.
	 *
	 * @return bool True if the rule was enabled, false if it was not disabled.
	 */
	public function enableRule( $ruleId, $userId ) {
		$cached = get_transient( self::TRANSIENT_KEY );
		$rules  = is_array( $cached ) && isset( $cached['rules'] ) ? $cached['rules'] : array();

		$key = array_search( $ruleId, $rules, true );
		if ( false === $key ) {
			// Rule is not disabled.
			return false;
		}

		array_splice( $rules, $key, 1 );

		// Update rules but KEEP the old file_mtime/size.
		$fileMtime = is_array( $cached ) && isset( $cached['file_mtime'] ) ? $cached['file_mtime'] : 0;
		$fileSize  = is_array( $cached ) && isset( $cached['file_size'] ) ? $cached['file_size'] : 0;

		$this->saveToCache( $rules, $fileMtime, $fileSize );
		$this->changelogWriter->writeAction( 'enable', $ruleId, $userId );
		$this->disabledRulesCache = $rules;

		return true;
	}

	/**
	 * Get the file path to disabled-rules.php.
	 *
	 * @return string The file path.
	 */
	public function getDisabledRulesFilePath() {
		return $this->getFilePath();
	}

	/**
	 * Check if the file has changed since we last synced.
	 *
	 * @param mixed       $cached      The cached transient data.
	 * @param array|false $currentStat Current file statistics.
	 *
	 * @return bool True if file changed, false otherwise.
	 */
	private function hasFileChanged( $cached, $currentStat ) {
		// No cache - need to load.
		if ( false === $cached || ! is_array( $cached ) ) {
			return true;
		}

		// File doesn't exist - use cached data.
		if ( false === $currentStat || ! is_array( $currentStat ) ) {
			return false;
		}

		// Compare mtime and size.
		$cachedMtime = isset( $cached['file_mtime'] ) ? $cached['file_mtime'] : 0;
		$cachedSize  = isset( $cached['file_size'] ) ? $cached['file_size'] : 0;

		return $cachedMtime !== $currentStat['mtime'] || $cachedSize !== $currentStat['size'];
	}

	/**
	 * Load disabled rules from the file.
	 *
	 * @return array List of disabled rule IDs.
	 */
	private function loadFromFile() {
		$data = $this->loadDataFromFile();

		if ( ! is_array( $data ) || ! isset( $data['rules'] ) || ! is_array( $data['rules'] ) ) {
			return array();
		}

		return $data['rules'];
	}

	/**
	 * Save disabled rules to the transient cache.
	 *
	 * @param array $rules     List of disabled rule IDs.
	 * @param int   $fileMtime File modification time.
	 * @param int   $fileSize  File size.
	 *
	 * @return void
	 */
	private function saveToCache( $rules, $fileMtime, $fileSize ) {
		$cacheData = array(
			'file_mtime' => $fileMtime,
			'file_size'  => $fileSize,
			'rules'      => $rules,
		);
		set_transient( self::TRANSIENT_KEY, $cacheData, self::TRANSIENT_LIFETIME );
	}

	/**
	 * Force reload disabled rules from file.
	 *
	 * Clears both in-memory and transient caches.
	 *
	 * @return array List of disabled rule IDs.
	 */
	public function forceReload() {
		$this->disabledRulesCache = null;
		delete_transient( self::TRANSIENT_KEY );
		return $this->getDisabledRules();
	}
}
