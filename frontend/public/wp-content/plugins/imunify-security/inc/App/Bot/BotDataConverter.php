<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fopen
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fwrite
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fclose
 * phpcs:disable WordPress.WP.AlternativeFunctions.rename_rename
 * phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Converts structured bot-protection data into OPcache-friendly PHP bundle files.
 *
 * WordPress-agnostic: no wp_* calls. All write failures throw RuntimeException
 * so callers (CLI scripts and wp-cron) can choose their own error surface.
 *
 * @since 4.1.0
 */
class BotDataConverter {

	/**
	 * Absolute path to the output directory.
	 *
	 * @var string
	 */
	private $output_dir;

	/**
	 * Longest `generated_at` accepted from all.json. The value is republished in
	 * every daily bot-stats export, so an oversized one would bloat each file.
	 */
	const MAX_GENERATED_AT_LENGTH = 64;

	/**
	 * `generated_at` of the last all.json passed to convert(), '' before the
	 * first call or when the payload omitted it.
	 *
	 * @var string
	 */
	private $generated_at = '';

	/**
	 * Source name → relative PHP path under the output directory.
	 *
	 * @var array<string,string>
	 */
	private static $source_relpath_map = array(
		'cloudflare'           => 'cdn-ip-ranges/cloudflare.php',
		'fastly'               => 'cdn-ip-ranges/fastly.php',
		'cloudfront'           => 'cdn-ip-ranges/cloudfront.php',
		'akamai'               => 'cdn-ip-ranges/akamai.php',
		'sucuri'               => 'cdn-ip-ranges/sucuri.php',
		'imperva'              => 'cdn-ip-ranges/imperva.php',
		'quic-cloud'           => 'cdn-ip-ranges/quic-cloud.php',
		'aws'                  => 'datacenter-ip-ranges/aws.php',
		'gcp'                  => 'datacenter-ip-ranges/gcp.php',
		'azure'                => 'datacenter-ip-ranges/azure.php',
		'ovh'                  => 'datacenter-ip-ranges/ovh.php',
		'hetzner'              => 'datacenter-ip-ranges/hetzner.php',
		'digitalocean'         => 'datacenter-ip-ranges/digitalocean.php',
		'anthropic'            => 'bot-ip-ranges/anthropic.php',
		'openai'               => 'bot-ip-ranges/openai.php',
		'google'               => 'bot-ip-ranges/google.php',
		'bing'                 => 'bot-ip-ranges/bing.php',
		'apple'                => 'bot-ip-ranges/apple.php',
		'meta'                 => 'bot-ip-ranges/meta.php',
		'duckduckgo'           => 'bot-ip-ranges/duckduckgo.php',
		'duckassistbot'        => 'bot-ip-ranges/duckassistbot.php',
		'perplexity'           => 'bot-ip-ranges/perplexity.php',
		'ahrefs'               => 'bot-ip-ranges/ahrefs.php',
		'barkrowler'           => 'bot-ip-ranges/barkrowler.php',
		'ua-ai-crawlers'       => 'signatures/ua-ai-crawlers.php',
		'ua-search-engines'    => 'signatures/ua-search-engines.php',
		'ua-seo-crawlers'      => 'signatures/ua-seo-crawlers.php',
		'ua-malicious'         => 'signatures/ua-malicious.php',
		'ua-rdns-suffixes'     => 'signatures/ua-rdns-suffixes.php',
		'ua-ai-rdns-suffixes'  => 'signatures/ua-ai-rdns-suffixes.php',
		'ua-seo-rdns-suffixes' => 'signatures/ua-seo-rdns-suffixes.php',
	);

	/**
	 * Build a converter bound to an output directory.
	 *
	 * @param string $output_dir Absolute path to the output directory.
	 */
	public function __construct( $output_dir ) {
		$this->output_dir = rtrim( (string) $output_dir, '/' );
	}

	/**
	 * Parse all.json and write PHP bundle files to the output directory.
	 *
	 * All-or-nothing: every bundle is built and staged to a .tmp file first; only
	 * once all sources stage successfully are they committed (renamed) into place.
	 * A failure on any source leaves the existing bundle files untouched, so a
	 * site never runs on a half-applied overlay.
	 *
	 * @param string $json Raw all.json content.
	 * @return int Number of bundle files written.
	 * @throws \RuntimeException On schema mismatch or any write failure.
	 */
	public function convert( $json ) {
		AtomicFileWriter::ensureDirectoryProtection( $this->output_dir );
		$data = json_decode( $json, true );
		if ( ! is_array( $data )
			|| ! isset( $data['schema_version'] )
			|| '1' !== (string) $data['schema_version'] ) {
			throw new \RuntimeException( 'BotDataConverter: invalid or unsupported schema_version in all.json' );
		}
		if ( ! isset( $data['sources'] ) || ! is_array( $data['sources'] ) ) {
			throw new \RuntimeException( 'BotDataConverter: missing sources object in all.json' );
		}

		$this->generated_at = isset( $data['generated_at'] )
			&& is_string( $data['generated_at'] )
			&& strlen( $data['generated_at'] ) <= self::MAX_GENERATED_AT_LENGTH
				? $data['generated_at']
				: '';

		$planned = array();
		foreach ( $data['sources'] as $name => $source ) {
			if ( ! isset( self::$source_relpath_map[ $name ] ) ) {
				continue;
			}
			$relpath    = self::$source_relpath_map[ $name ];
			$source_url = isset( $source['source_url'] ) ? (string) $source['source_url'] : 'mirror';
			$fetched_at = isset( $source['fetched_at'] ) ? (string) $source['fetched_at'] : gmdate( 'c' );
			$type       = isset( $source['type'] ) ? $source['type'] : '';
			try {
				if ( 'ranges' === $type ) {
					if ( isset( $source['ranges'] ) && is_array( $source['ranges'] ) && ! empty( $source['ranges'] ) ) {
						$this->assertSafeRelpath( $relpath );
						$planned[ $relpath ] = $this->buildRangesPayload( $source_url, $fetched_at, $source['ranges'] );
					}
				} elseif ( 'signatures' === $type ) {
					if ( isset( $source['signatures'] ) && is_array( $source['signatures'] ) && ! empty( $source['signatures'] ) ) {
						$this->assertSafeRelpath( $relpath );
						$payload = $this->buildSignaturesPayload( $source_url, $fetched_at, $source['signatures'] );
						if ( ! empty( $payload['signatures'] ) ) {
							$planned[ $relpath ] = $payload;
						}
					}
				} elseif ( 'providers' === $type ) {
					if ( isset( $source['providers'] ) && is_array( $source['providers'] ) && ! empty( $source['providers'] ) ) {
						$this->assertSafeRelpath( $relpath );
						$payload = $this->buildProvidersPayload( $source_url, $fetched_at, $source['providers'] );
						if ( ! empty( $payload['providers'] ) ) {
							$planned[ $relpath ] = $payload;
						}
					}
				}
			} catch ( \Exception $e ) {
				throw new \RuntimeException( 'BotDataConverter: failed to build ' . $name . ': ' . $e->getMessage(), 0, $e );
			}
		}

		$written = $this->writeBatch( $planned );
		AtomicFileWriter::ensureDirectoryProtection( $this->output_dir );

		if ( ! empty( $planned ) ) {
			foreach ( self::$source_relpath_map as $relpath ) {
				if ( isset( $planned[ $relpath ] ) ) {
					continue;
				}
				$abs = $this->output_dir . '/' . ltrim( $relpath, '/' );
				if ( is_file( $abs ) ) {
					@unlink( $abs );
				}
			}
		}

		return $written;
	}

	/**
	 * The `generated_at` of the all.json the last convert() call parsed — the
	 * only version identifier the dataset carries, and one the per-source bundle
	 * files do not keep.
	 *
	 * @return string ISO-8601 timestamp, or '' when absent or over-long.
	 */
	public function generatedAt() {
		return $this->generated_at;
	}

	/**
	 * Write a ranges bundle file with sorted CIDRs and sorted-set checksum.
	 *
	 * @param string   $relpath    Relative path under output_dir.
	 * @param string   $source_url Source identifier (URL or "MANUAL").
	 * @param string   $fetched_at ISO-8601 fetch timestamp.
	 * @param string[] $ranges     Flat list of CIDR strings.
	 * @param array    $extra      Optional extra metadata merged into payload.
	 * @throws \RuntimeException On path traversal or write failure.
	 */
	public function writeRanges( $relpath, $source_url, $fetched_at, $ranges, $extra = array() ) {
		$this->assertSafeRelpath( $relpath );
		$this->writePhpFile( $relpath, $this->buildRangesPayload( $source_url, $fetched_at, $ranges, $extra ) );
	}

	/**
	 * Write a signatures bundle file with sanitised tokens.
	 *
	 * @param string   $relpath    Relative path under output_dir.
	 * @param string   $source_url Source identifier.
	 * @param string   $fetched_at ISO-8601 fetch timestamp.
	 * @param string[] $signatures Raw signature tokens.
	 * @param array    $extra      Optional extra metadata merged into payload.
	 * @param bool     $sort       When true (default) the stored list is sorted
	 *                             alphabetically. Pass false for hand-curated lists
	 *                             to preserve the authored order; the checksum is
	 *                             always computed from the sorted set regardless.
	 * @throws \RuntimeException On path traversal or write failure.
	 */
	public function writeSignatures( $relpath, $source_url, $fetched_at, $signatures, $extra = array(), $sort = true ) {
		$this->assertSafeRelpath( $relpath );
		$this->writePhpFile( $relpath, $this->buildSignaturesPayload( $source_url, $fetched_at, $signatures, $extra, $sort ) );
	}

	/**
	 * Write a providers (rDNS suffix) bundle file with sorted-set checksum.
	 *
	 * @param string $relpath    Relative path under output_dir.
	 * @param string $source_url Source identifier.
	 * @param string $fetched_at ISO-8601 fetch timestamp.
	 * @param array  $providers  Map of provider name => {tokens:[], suffixes:[]}.
	 * @param array  $extra      Optional extra metadata merged into payload.
	 * @param bool   $sort       When true (default) the provider map is stored in
	 *                           alphabetical key order. Pass false for hand-curated
	 *                           maps to preserve the authored order; the checksum is
	 *                           always computed from the sorted map regardless.
	 * @throws \RuntimeException On path traversal or write failure.
	 */
	public function writeProviders( $relpath, $source_url, $fetched_at, $providers, $extra = array(), $sort = true ) {
		$this->assertSafeRelpath( $relpath );
		$this->writePhpFile( $relpath, $this->buildProvidersPayload( $source_url, $fetched_at, $providers, $extra, $sort ) );
	}

	/**
	 * Build a ranges payload (sorted CIDRs, octet-bucketed, sorted-set checksum).
	 *
	 * @param string   $source_url Source identifier.
	 * @param string   $fetched_at ISO-8601 fetch timestamp.
	 * @param string[] $ranges     Flat list of CIDR strings.
	 * @param array    $extra      Optional extra metadata.
	 * @return array
	 */
	private function buildRangesPayload( $source_url, $fetched_at, $ranges, $extra = array() ) {
		sort( $ranges );
		$bucketed = SignatureRefresher::bucketRanges( $ranges );
		return array_merge(
			array(
				'source_url'      => $source_url,
				'fetched_at'      => $fetched_at,
				'checksum'        => 'sha256:' . hash( 'sha256', implode( "\n", $ranges ) ),
				'ranges_by_octet' => $bucketed['ranges_by_octet'],
				'ranges_broad'    => $bucketed['ranges_broad'],
			),
			$extra
		);
	}

	/**
	 * Build a signatures payload (sanitised, sorted-set checksum).
	 *
	 * @param string   $source_url Source identifier.
	 * @param string   $fetched_at ISO-8601 fetch timestamp.
	 * @param string[] $signatures Raw signature tokens.
	 * @param array    $extra      Optional extra metadata.
	 * @param bool     $sort       Whether to store the list in sorted order.
	 * @return array
	 */
	private function buildSignaturesPayload( $source_url, $fetched_at, $signatures, $extra = array(), $sort = true ) {
		$signatures = SignatureRefresher::sanitizeSignatures( $signatures );
		$sorted     = $signatures;
		sort( $sorted );
		return array_merge(
			array(
				'source_url' => $source_url,
				'fetched_at' => $fetched_at,
				'checksum'   => 'sha256:' . hash( 'sha256', implode( "\n", $sorted ) ),
				'signatures' => $sort ? $sorted : $signatures,
			),
			$extra
		);
	}

	/**
	 * Build a providers payload (sorted-set checksum).
	 *
	 * @param string $source_url Source identifier.
	 * @param string $fetched_at ISO-8601 fetch timestamp.
	 * @param array  $providers  Map of provider name => {tokens:[], suffixes:[]}.
	 * @param array  $extra      Optional extra metadata.
	 * @param bool   $sort       Whether to store the provider map in sorted key order.
	 * @return array
	 */
	private function buildProvidersPayload( $source_url, $fetched_at, $providers, $extra = array(), $sort = true ) {
		$clean = array();
		foreach ( $providers as $name => $info ) {
			if ( ! is_string( $name ) || '' === $name || ! is_array( $info ) ) {
				continue;
			}
			$tokens   = isset( $info['tokens'] ) && is_array( $info['tokens'] )
				? array_values( array_filter( $info['tokens'], 'is_string' ) )
				: array();
			$suffixes = isset( $info['suffixes'] ) && is_array( $info['suffixes'] )
				? array_values( array_filter( $info['suffixes'], 'is_string' ) )
				: array();
			if ( empty( $tokens ) || empty( $suffixes ) ) {
				continue;
			}
			$clean[ $name ] = array(
				'tokens'   => $tokens,
				'suffixes' => $suffixes,
			);
		}

		$sorted_clean = $clean;
		ksort( $sorted_clean );

		$digest_lines = array();
		foreach ( $sorted_clean as $name => $info ) {
			$t = $info['tokens'];
			sort( $t );
			$s = $info['suffixes'];
			sort( $s );
			$digest_lines[] = $name . ':tokens=' . implode( ',', $t ) . ';suffixes=' . implode( ',', $s );
		}

		return array_merge(
			array(
				'source_url' => $source_url,
				'fetched_at' => $fetched_at,
				'checksum'   => 'sha256:' . hash( 'sha256', implode( "\n", $digest_lines ) ),
			),
			$extra,
			array( 'providers' => $sort ? $sorted_clean : $clean )
		);
	}

	/**
	 * Throw if $relpath could escape the output directory.
	 *
	 * @param string $relpath Relative path to validate.
	 * @throws \RuntimeException When the path contains traversal sequences.
	 */
	private function assertSafeRelpath( $relpath ) {
		if ( BundledData::isUnsafePath( $relpath ) ) {
			throw new \RuntimeException( 'BotDataConverter: unsafe relpath: ' . $relpath );
		}
	}

	/**
	 * Render a PHP return-array file body.
	 *
	 * @param array $payload Payload array to serialise.
	 * @return string
	 */
	private function renderPhp( $payload ) {
		return "<?php\ndefined( 'ABSPATH' ) || exit;\n// Auto-generated. Do not edit by hand.\n\nreturn " . self::phpExport( $payload ) . ";\n";
	}

	/**
	 * Recursively export a value as a PHP expression using array() long syntax.
	 *
	 * Sequential (0-based integer) arrays omit keys; all other arrays (associative
	 * or non-sequential integer) include them. This produces smaller output than
	 * var_export(), which always emits explicit integer keys.
	 *
	 * @param mixed $var   Value to export.
	 * @param int   $depth Current nesting depth (controls indentation).
	 * @return string
	 */
	public static function phpExport( $var, $depth = 0 ) {
		if ( ! is_array( $var ) ) {
			return var_export( $var, true );
		}
		if ( empty( $var ) ) {
			return 'array()';
		}
		$keys       = array_keys( $var );
		$sequential = range( 0, count( $var ) - 1 ) === $keys;
		$pad        = str_repeat( '    ', $depth + 1 );
		$close_pad  = str_repeat( '    ', $depth );
		$items      = array();
		foreach ( $var as $k => $v ) {
			$entry = $pad;
			if ( ! $sequential ) {
				$entry .= var_export( $k, true ) . ' => ';
			}
			$entry  .= self::phpExport( $v, $depth + 1 );
			$items[] = $entry;
		}
		return "array(\n" . implode( ",\n", $items ) . ",\n" . $close_pad . ')';
	}

	/**
	 * Atomically write a single PHP return-array file (tmp + rename).
	 *
	 * @param string $relpath Relative path under output_dir.
	 * @param array  $payload Payload array to serialise.
	 * @throws \RuntimeException On directory creation or write failure.
	 */
	private function writePhpFile( $relpath, $payload ) {
		$abs = $this->output_dir . '/' . ltrim( $relpath, '/' );
		$dir = dirname( $abs );
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			throw new \RuntimeException( 'BotDataConverter: mkdir failed for ' . $dir );
		}
		if ( ! AtomicFileWriter::write( $abs, $this->renderPhp( $payload ) ) ) {
			throw new \RuntimeException( 'BotDataConverter: failed to write ' . $abs );
		}
	}

	/**
	 * Write all planned bundles atomically: stage every file to .tmp first, and
	 * only commit (rename) once they have all staged successfully. Any failure
	 * discards every staged .tmp and leaves the existing files untouched.
	 *
	 * @param array<string,array> $planned Map of relpath => payload.
	 * @return int Number of files written.
	 * @throws \RuntimeException On any staging or commit failure.
	 */
	private function writeBatch( array $planned ) {
		$staged = array();
		try {
			foreach ( $planned as $relpath => $payload ) {
				$abs            = $this->output_dir . '/' . ltrim( $relpath, '/' );
				$tmp            = $this->stageTmp( $abs, $this->renderPhp( $payload ) );
				$staged[ $tmp ] = $abs;
			}
		} catch ( \RuntimeException $e ) {
			foreach ( array_keys( $staged ) as $tmp ) {
				@unlink( $tmp );
			}
			throw $e;
		}

		$committed = array();
		foreach ( $staged as $tmp => $abs ) {
			$bak = null;
			if ( file_exists( $abs ) ) {
				$bak = $abs . '.bak';
				if ( ! rename( $abs, $bak ) ) {
					$bak = null;
				}
			}
			try {
				$this->commitTmp( $tmp, $abs );
				$committed[ $abs ] = $bak;
			} catch ( \RuntimeException $e ) {
				if ( null !== $bak ) {
					@rename( $bak, $abs );
				}
				foreach ( $committed as $c_abs => $c_bak ) {
					if ( null !== $c_bak ) {
						@rename( $c_bak, $c_abs );
					} else {
						@unlink( $c_abs );
					}
				}
				foreach ( array_keys( $staged ) as $s_tmp ) {
					@unlink( $s_tmp );
				}
				throw $e;
			}
		}
		foreach ( $committed as $c_abs => $c_bak ) {
			if ( null !== $c_bak ) {
				@unlink( $c_bak );
			}
		}
		return count( $planned );
	}

	/**
	 * Write $php to <abs>.tmp, creating the parent directory as needed.
	 *
	 * @param string $abs Absolute target path (the final, non-tmp path).
	 * @param string $php Rendered file contents.
	 * @return string The .tmp path written.
	 * @throws \RuntimeException On directory creation or write failure.
	 */
	private function stageTmp( $abs, $php ) {
		$dir = dirname( $abs );
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			throw new \RuntimeException( 'BotDataConverter: mkdir failed for ' . $dir );
		}
		$tmp = $abs . '.tmp';
		$h   = fopen( $tmp, 'w' );
		if ( false === $h ) {
			throw new \RuntimeException( 'BotDataConverter: fopen failed for ' . $tmp );
		}
		$bytes    = fwrite( $h, $php );
		$expected = strlen( $php );
		fclose( $h );
		if ( false === $bytes || $bytes !== $expected ) {
			@unlink( $tmp );
			throw new \RuntimeException( 'BotDataConverter: fwrite failed for ' . $tmp );
		}
		return $tmp;
	}

	/**
	 * Commit a staged .tmp into place via rename + opcache invalidation.
	 *
	 * @param string $tmp Staged .tmp path.
	 * @param string $abs Final target path.
	 * @throws \RuntimeException On rename failure.
	 */
	private function commitTmp( $tmp, $abs ) {
		if ( ! rename( $tmp, $abs ) ) {
			@unlink( $tmp );
			throw new \RuntimeException( 'BotDataConverter: rename failed for ' . $abs );
		}
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $abs, true );
		}
	}
}
