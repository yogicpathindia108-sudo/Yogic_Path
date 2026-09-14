<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
 */

namespace CloudLinux\Imunify\App\Defender;

/**
 * Writes rule enable/disable actions to changelog.php for agent processing.
 *
 * The changelog file uses the same format as incident files:
 * - PHP header with __halt_compiler() to prevent execution
 * - Each action as a base64-encoded JSON line prefixed with #
 *
 * @since 3.0.0
 */
class ChangelogWriter {

	/**
	 * Changelog file name.
	 */
	const CHANGELOG_FILE_NAME = 'changelog.php';

	/**
	 * PHP header for the changelog file.
	 */
	const PHP_HEADER = "<?php __halt_compiler();\n";

	/**
	 * Data directory path.
	 *
	 * @var string
	 */
	private $dataDirectory;

	/**
	 * Constructor.
	 *
	 * @param string $dataDirectory Path to the data directory (wp-content/imunify-security).
	 */
	public function __construct( $dataDirectory ) {
		$this->dataDirectory = $dataDirectory;
	}

	/**
	 * Write an action to the changelog file.
	 *
	 * @param string $action  The action type ('disable' or 'enable').
	 * @param string $ruleId  The rule ID.
	 * @param int    $userId  The WordPress user ID performing the action.
	 *
	 * @return bool True if the action was written successfully, false otherwise.
	 */
	public function writeAction( $action, $ruleId, $userId ) {
		$filePath = $this->getChangelogFilePath();

		// Ensure directory exists before writing.
		if ( ! $this->ensureDirectoryExists() ) {
			return false;
		}

		$actionData = $this->buildActionData( $action, $ruleId, $userId );

		// Encode the JSON data as base64 and format with PHP comment prefix.
		$jsonData    = wp_json_encode( $actionData );
		$encodedData = base64_encode( $jsonData ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$jsonLine    = '#' . $encodedData . "\n";

		// Check if file exists to determine if we need to write the PHP header.
		$fileExists = file_exists( $filePath );

		// If file doesn't exist, write the PHP header first.
		if ( ! $fileExists ) {
			$result = @file_put_contents( $filePath, self::PHP_HEADER );
			if ( false === $result ) {
				return false;
			}
		}

		// Append to the changelog file.
		$result = @file_put_contents(
			$filePath,
			$jsonLine,
			FILE_APPEND
		);

		return false !== $result;
	}

	/**
	 * Build action data array.
	 *
	 * @param string $action  The action type ('disable' or 'enable').
	 * @param string $ruleId  The rule ID.
	 * @param int    $userId  The WordPress user ID performing the action.
	 *
	 * @return array
	 */
	private function buildActionData( $action, $ruleId, $userId ) {
		return array(
			'action'  => $action,
			'rule_id' => $ruleId,
			'user_id' => (string) $userId,
			'ts'      => time(),
		);
	}

	/**
	 * Get the changelog file path.
	 *
	 * @return string
	 */
	public function getChangelogFilePath() {
		return $this->dataDirectory . DIRECTORY_SEPARATOR . self::CHANGELOG_FILE_NAME;
	}

	/**
	 * Ensure the data directory exists.
	 *
	 * @return bool True if directory exists or was created, false otherwise.
	 */
	private function ensureDirectoryExists() {
		if ( ! is_dir( $this->dataDirectory ) ) {
			$success = wp_mkdir_p( $this->dataDirectory );
			if ( ! $success ) {
				return false;
			}
		}

		return true;
	}
}
