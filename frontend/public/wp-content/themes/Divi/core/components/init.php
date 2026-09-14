<?php
// phpcs:disable Generic.WhiteSpace.ScopeIndent -- our preference is to not indent the whole inner function in this scenario.
if ( ! function_exists( 'et_core_init' ) ) :
/**
 * {@see 'plugins_loaded' (9999999) Must run after cache plugins have been loaded.}
 */
function et_core_init() {
	ET_Core_API_Spam_Providers::instance();
	ET_Core_Cache_Directory::instance();
	ET_Core_PageResource::startup();

	// only load the following if we're in the admin.
	if ( is_admin() ) {
		ET_Core_CompatibilityWarning::instance();
	}

	if ( defined( 'ET_CORE_UPDATED' ) ) {
		global $wp_rewrite;
		add_action( 'shutdown', array( $wp_rewrite, 'flush_rules' ) );

		update_option( 'et_core_page_resource_remove_all', true );
	}

	$cache_dir = ET_Core_PageResource::get_cache_directory();

	if ( file_exists( $cache_dir . '/DONOTCACHEPAGE' ) ) {
		! defined( 'DONOTCACHEPAGE' ) ? define( 'DONOTCACHEPAGE', true ) : '';
		@unlink( $cache_dir . '/DONOTCACHEPAGE' );
	}

	// Checking if user is logged in to make sure this isnt called on every frontend pageload.
	if ( is_user_logged_in() && get_option( 'et_core_page_resource_remove_all' ) ) {
		ET_Core_PageResource::remove_static_resources( 'all', 'all', true );
	}
}
endif;

if ( ! function_exists( 'et_core_site_has_builder' ) ) :
/**
 * Check is `et_core_site_has_builder` allowed.
 * We can clear cache managed by 3rd party plugins only
 * if Divi, Extra, or the Divi Builder plugin
 * is active when the core was called.
 *
 * @return boolean
 */
function et_core_site_has_builder() {
	global $shortname;

	$core_path                     = get_transient( 'et_core_path' );
	$is_divi_builder_plugin_active = false;

	if ( ! empty( $core_path ) && false !== strpos( $core_path, '/divi-builder/' ) && function_exists('is_plugin_active') ) {
		$is_divi_builder_plugin_active = is_plugin_active( 'divi-builder/divi-builder.php' );
	}

	if( $is_divi_builder_plugin_active || in_array( $shortname, array( 'divi', 'extra' ) ) ) {
		return true;
	}

	return false;
}
endif;

if ( ! function_exists( 'et_core_clear_wp_cache' ) ):
function et_core_clear_wp_cache( $post_id = '' ) {
	if ( ( ! wp_doing_cron() && ! et_core_security_check_passed( 'edit_posts' ) ) || ! et_core_site_has_builder() ) {
		return;
	}

	try {
		// Get homepage ID to always clear homepage cache.
		$homepage_id = (int) get_option( 'page_on_front' );

		// Cache Plugins
		// Comet Cache
		if ( is_callable( 'comet_cache::clearPost' ) || is_callable( 'comet_cache::clear' ) ) {
			if ( '' !== $post_id && is_callable( 'comet_cache::clearPost' ) ) {
				comet_cache::clearPost( $post_id );
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					comet_cache::clearPost( $homepage_id );
				}
			} else if ( is_callable( 'comet_cache::clear' ) ) {
				comet_cache::clear();
			}
		}

		// WP Rocket
		if ( function_exists( 'rocket_clean_post' ) ) {
			if ( '' !== $post_id ) {
				rocket_clean_post( $post_id );
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					rocket_clean_post( $homepage_id );
				}
				if ( 0 === $homepage_id && function_exists( 'rocket_clean_files' ) ) {
					rocket_clean_files( home_url( '/' ) );
				}
			} else if ( function_exists( 'rocket_clean_domain' ) ) {
				rocket_clean_domain();
			}
		}

		// W3 Total Cache
		if ( has_action( 'w3tc_flush_post' ) || has_action( 'w3tc_flush_all' ) ) {
			if ( '' !== $post_id && has_action( 'w3tc_flush_post' ) ) {
				do_action( 'w3tc_flush_post', $post_id );
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					do_action( 'w3tc_flush_post', $homepage_id );
				}
			} elseif ( has_action( 'w3tc_flush_all' ) ) {
				do_action( 'w3tc_flush_all' );
			}
		}

		// WP Super Cache
		if ( function_exists( 'wp_cache_debug' ) && defined( 'WPCACHEHOME' ) ) {
			$wp_cache_phase1 = WPCACHEHOME . 'wp-cache-phase1.php';
			$wp_cache_phase2 = WPCACHEHOME . 'wp-cache-phase2.php';

			if ( file_exists( $wp_cache_phase1 ) ) {
				include_once $wp_cache_phase1;
			}
			if ( file_exists( $wp_cache_phase2 ) ) {
				include_once $wp_cache_phase2;
			}

			if ( '' !== $post_id && function_exists( 'clear_post_supercache' ) ) {
				clear_post_supercache( $post_id );
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					clear_post_supercache( $homepage_id );
				}
			} else if ( '' === $post_id && function_exists( 'wp_cache_clear_cache_on_menu' ) ) {
				wp_cache_clear_cache_on_menu();
			}
		}

		// WP Fastest Cache
		if ( isset( $GLOBALS['wp_fastest_cache'] ) ) {
			if ( '' !== $post_id && method_exists( $GLOBALS['wp_fastest_cache'], 'singleDeleteCache' ) ) {
				$wp_fastest_cache_instance = $GLOBALS['wp_fastest_cache'];
				$wp_fastest_cache_instance->singleDeleteCache( $post_id );
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					$wp_fastest_cache_instance->singleDeleteCache( $homepage_id );
				}
			} else if ( '' === $post_id && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
				$GLOBALS['wp_fastest_cache']->deleteCache();
			}
		}

		// Hummingbird
		if ( has_action( 'wphb_clear_page_cache' ) ) {
			if ( '' !== $post_id ) {
				do_action( 'wphb_clear_page_cache', $post_id );
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					do_action( 'wphb_clear_page_cache', $homepage_id );
				}
			} else {
				do_action( 'wphb_clear_page_cache' );
			}
		}

		// WordPress Cache Enabler
		if ( has_action( 'cache_enabler_clear_complete_cache' ) ) {
			if ( '' !== $post_id && has_action( 'cache_enabler_clear_page_cache_by_post' ) ) {
				do_action( 'cache_enabler_clear_page_cache_by_post', $post_id );
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					do_action( 'cache_enabler_clear_page_cache_by_post', $homepage_id );
				}
			} else {
				do_action( 'cache_enabler_clear_complete_cache' );
			}
		}

		// LiteSpeed Cache v3.0+.
		if ( '' !== $post_id && has_action( 'litespeed_purge_post' ) ) {
			do_action( 'litespeed_purge_post', $post_id );
			if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
				do_action( 'litespeed_purge_post', $homepage_id );
			}
		} elseif ( '' === $post_id && has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}

		// LiteSpeed Cache v1.1.3 until v3.0.
		if ( '' !== $post_id && function_exists( 'litespeed_purge_single_post' ) ) {
			litespeed_purge_single_post( $post_id );
			if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
				litespeed_purge_single_post( $homepage_id );
			}
		} elseif ( '' === $post_id && is_callable( 'LiteSpeed_Cache_API::purge_all' ) ) {
			LiteSpeed_Cache_API::purge_all();
		} elseif ( is_callable( array( 'LiteSpeed_Cache', 'get_instance' ) ) ) {
			// LiteSpeed Cache v1.1.3 below. LiteSpeed_Cache still exist on v2.9.9.2, but no
			// longer exist on v3.0. Keep it here as backward compatibility for lower version.
			$litespeed = LiteSpeed_Cache::get_instance();

			if ( is_object( $litespeed ) ) {
				if ( '' !== $post_id && method_exists( $litespeed, 'purge_post' ) ) {
					$litespeed->purge_post( $post_id );
					if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
						$litespeed->purge_post( $homepage_id );
					}
				} else if ( '' === $post_id && method_exists( $litespeed, 'purge_all' ) ) {
					$litespeed->purge_all();
				}
			}
		}

		// Hyper Cache
		if ( class_exists( 'HyperCache' ) && isset( HyperCache::$instance ) ) {
			if ( '' !== $post_id && method_exists( HyperCache::$instance, 'clean_post' ) ) {
				HyperCache::$instance->clean_post( $post_id );
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					HyperCache::$instance->clean_post( $homepage_id );
				}
			} else if ( '' === $post_id && method_exists( HyperCache::$instance, 'clean' ) ) {
				HyperCache::$instance->clean();
			}
		}

		// Hosting Provider Caching
		// Pantheon Advanced Page Cache
		$pantheon_clear     = 'pantheon_wp_clear_edge_keys';
		$pantheon_clear_all = 'pantheon_wp_clear_edge_all';
		if ( function_exists( $pantheon_clear ) || function_exists( $pantheon_clear_all ) ) {
			if ( '' !== $post_id && function_exists( $pantheon_clear ) ) {
				$pantheon_keys = array( "post-{$post_id}" );
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					$pantheon_keys[] = "post-{$homepage_id}";
				}
				pantheon_wp_clear_edge_keys( $pantheon_keys );
			} else if ( '' === $post_id && function_exists( $pantheon_clear_all ) ) {
				pantheon_wp_clear_edge_all();
			}
		}

		// Siteground Optimizer
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			if ( '' !== $post_id ) {
				$post_url = get_permalink( $post_id );
				if ( $post_url ) {
					sg_cachepress_purge_cache( $post_url );
				}
				// Always clear homepage cache.
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					$homepage_url = get_permalink( $homepage_id );
					if ( $homepage_url ) {
						sg_cachepress_purge_cache( $homepage_url );
					}
				} else if ( 0 === $homepage_id ) {
					sg_cachepress_purge_cache( home_url( '/' ) );
				}
			} else {
				sg_cachepress_purge_cache();
			}
		} elseif ( isset( $GLOBALS['sg_cachepress_supercacher'] ) ) {
			global $sg_cachepress_supercacher;

			if ( is_object( $sg_cachepress_supercacher ) && method_exists( $sg_cachepress_supercacher, 'purge_cache' ) ) {
				$sg_cachepress_supercacher->purge_cache( true );
			}
		}

		// WP Engine
		if ( class_exists( 'WpeCommon' ) ) {
			is_callable( 'WpeCommon::purge_memcached' ) ? WpeCommon::purge_memcached() : '';
			is_callable( 'WpeCommon::clear_maxcdn_cache' ) ? WpeCommon::clear_maxcdn_cache() : '';
			is_callable( 'WpeCommon::purge_varnish_cache' ) ? WpeCommon::purge_varnish_cache() : '';

			if ( is_callable( 'WpeCommon::instance' ) && $instance = WpeCommon::instance() ) {
				method_exists( $instance, 'purge_object_cache' ) ? $instance->purge_object_cache() : '';
			}
		}

		// Bluehost
		if ( class_exists( 'Endurance_Page_Cache' ) && class_exists( 'ET_Core_LIB_BluehostCache' ) && is_callable( array( 'ET_Core_LIB_BluehostCache', 'get_instance' ) ) ) {
			if ( wp_doing_ajax() ) {
				$bluehost_cache = ET_Core_LIB_BluehostCache::get_instance();
				if ( is_object( $bluehost_cache ) && method_exists( $bluehost_cache, 'clear' ) ) {
					$bluehost_cache->clear( $post_id );
					if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
						$bluehost_cache->clear( $homepage_id );
					}
				}
			} else {
				do_action( 'epc_purge' );
			}
		}

		// Pressable.
		if ( isset( $GLOBALS['batcache'] ) && is_object( $GLOBALS['batcache'] ) ) {
			wp_cache_flush();
		}

		// Cloudways - Breeze.
		if ( class_exists( 'Breeze_Admin' ) ) {
			$breeze_admin = new Breeze_Admin();
			if ( is_object( $breeze_admin ) && method_exists( $breeze_admin, 'breeze_clear_all_cache' ) ) {
				$breeze_admin->breeze_clear_all_cache();
			}
		}

		// Kinsta.
		if ( class_exists( '\Kinsta\Cache' ) && isset( $GLOBALS['kinsta_cache'] ) && is_object( $GLOBALS['kinsta_cache'] ) ) {
			global $kinsta_cache;

			if ( isset( $kinsta_cache->kinsta_cache_purge ) && method_exists( $kinsta_cache->kinsta_cache_purge, 'purge_complete_caches' ) ) {
				$kinsta_cache->kinsta_cache_purge->purge_complete_caches();
			}
		}

		// GoDaddy.
		if ( class_exists( '\WPaaS\Cache' ) ) {
			global $wpaas_cache_class;

			// Since GD System Plugin 4.51.1 the cache class instance can be accessed
			// with $wpaas_cache_class global. In addition to this, the 'has_ban' method
			// is no longer static. To cover both static and non-static versions we
			// can test if $wpaas_cache_class exists and use the correct type accordingly.
			$has_ban = false;
			if ( $wpaas_cache_class && is_object( $wpaas_cache_class ) && method_exists( $wpaas_cache_class, 'has_ban' ) ) {
				$has_ban = $wpaas_cache_class->has_ban();
			} elseif ( is_callable( array( '\WPaaS\Cache', 'has_ban' ) ) ) {
				$has_ban = \WPaaS\Cache::has_ban();
			}

			if ( ! $has_ban ) {
				$gd_cache_class = $wpaas_cache_class ? $wpaas_cache_class : '\WPaaS\Cache';

				if ( is_callable( array( $gd_cache_class, 'purge' ) ) ) {
					remove_action( 'shutdown', array( $gd_cache_class, 'purge' ), PHP_INT_MAX );
				}
				if ( is_callable( array( $gd_cache_class, 'ban' ) ) ) {
					add_action( 'shutdown', array( $gd_cache_class, 'ban' ), PHP_INT_MAX );
				}
			}
		}

		// Complimentary Performance Plugins.
		// Autoptimize.
		if ( is_callable( 'autoptimizeCache::clearall' ) ) {
			autoptimizeCache::clearall();
		}

		// WP Optimize (full purge: wpo_cache_flush clears file HTML cache for guests).
		if ( class_exists( 'WP_Optimize' ) && defined( 'WPO_PLUGIN_MAIN_PATH' ) ) {
			if ( '' === $post_id && function_exists( 'wpo_cache_flush' ) ) {
				wpo_cache_flush();
			}

			if ( '' !== $post_id && is_callable( array( 'WPO_Page_Cache', 'delete_single_post_cache' ) ) ) {
				WPO_Page_Cache::delete_single_post_cache( $post_id );

				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					WPO_Page_Cache::delete_single_post_cache( $homepage_id );
				}
			} else {
				$wp_optimize = WP_Optimize();
				$page_cache  = is_object( $wp_optimize ) ? $wp_optimize->get_page_cache() : null;

				if ( is_object( $page_cache ) && is_callable( array( $page_cache, 'purge' ) ) ) {
					$page_cache->purge();
				}
			}
		}

		// FlyingPress
		if ( class_exists( '\FlyingPress\Purge' ) ) {
			if ( '' !== $post_id && method_exists( '\FlyingPress\Purge', 'purge_urls' ) ) {
				$post_url = get_permalink( $post_id );
				if ( $post_url ) {
					$urls_to_purge = array( trailingslashit( $post_url ) );
					// Always clear homepage cache.
					if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
						$homepage_url = get_permalink( $homepage_id );
						if ( $homepage_url ) {
							$urls_to_purge[] = trailingslashit( $homepage_url );
						}
					} else if ( 0 === $homepage_id ) {
						$urls_to_purge[] = trailingslashit( home_url( '/' ) );
					}
					\FlyingPress\Purge::purge_urls( $urls_to_purge );
				}
			} elseif ( method_exists( '\FlyingPress\Purge', 'purge_pages' ) ) {
				\FlyingPress\Purge::purge_pages();
			}
		}

		// Nitropack
		if ( function_exists( 'nitropack_purge' ) ) {
			if ( '' !== $post_id ) {
				nitropack_purge( null, "post:{$post_id}", 'Divi CSS cache cleared' );
				// Always clear homepage cache.
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					nitropack_purge( null, "post:{$homepage_id}", 'Divi CSS cache cleared' );
				} else if ( 0 === $homepage_id ) {
					nitropack_purge( home_url( '/' ), null, 'Divi CSS cache cleared' );
				}
			} else {
				nitropack_purge( null, null, 'Divi CSS cache cleared' );
			}
		}

		// Super Page Cache for Cloudflare
		if ( has_action( 'swcfpc_purge_urls' ) || has_action( 'swcfpc_purge_all' ) ) {
			if ( '' !== $post_id && has_action( 'swcfpc_purge_urls' ) ) {
				$post_url = get_permalink( $post_id );
				if ( $post_url ) {
					$urls_to_purge = array( trailingslashit( $post_url ) );
					// Always clear homepage cache.
					if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
						$homepage_url = get_permalink( $homepage_id );
						if ( $homepage_url ) {
							$urls_to_purge[] = trailingslashit( $homepage_url );
						}
					} else if ( 0 === $homepage_id ) {
						$urls_to_purge[] = trailingslashit( home_url( '/' ) );
					}
					do_action( 'swcfpc_purge_urls', $urls_to_purge );
				}
			} elseif ( has_action( 'swcfpc_purge_all' ) ) {
				do_action( 'swcfpc_purge_all' );
			}
		}

		// SpeedyCache by Softaculous
		if ( class_exists( '\SpeedyCache\Delete' ) && method_exists( '\SpeedyCache\Delete', 'cache' ) ) {
			if ( '' !== $post_id ) {
				\SpeedyCache\Delete::cache( $post_id );
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					\SpeedyCache\Delete::cache( $homepage_id );
				}
			} elseif ( method_exists( '\SpeedyCache\Delete', 'all' ) ) {
				\SpeedyCache\Delete::all();
			} elseif ( is_callable( array( '\SpeedyCache\Delete', 'cache' ) ) ) {
				// Try calling cache() without parameters to clear all.
				\SpeedyCache\Delete::cache();
			}
		}

		// WP Compress
		if ( class_exists( 'wps_ic_cache' ) ) {
			$wpc_used_new_integrations = false;

			$wpc_plugin_path = WP_PLUGIN_DIR . '/wp-compress-image-optimizer/wp-compress.php';

			if ( file_exists( $wpc_plugin_path ) && class_exists( 'wps_ic_cache_integrations' ) ) {
				$wpc_plugin_headers = get_file_data(
					$wpc_plugin_path,
					array(
						'Version' => 'Version',
					),
					'plugin'
				);

				$wpc_plugin_version = $wpc_plugin_headers['Version'];

				if ( ! empty( $wpc_plugin_version ) && version_compare( $wpc_plugin_version, '6.60.46', '>=' ) ) {
					$wpc_integrations = new wps_ic_cache_integrations();

					if ( '' !== $post_id && is_callable( array( $wpc_integrations, 'purge_id' ) ) ) {
						$wpc_integrations->purge_id( $post_id );

						// Always clear homepage cache.
						if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
							$wpc_integrations->purge_id( $homepage_id );
						}

						$wpc_used_new_integrations = true;
					} elseif ( '' === $post_id && is_callable( array( $wpc_integrations, 'purge_site' ) ) ) {
						$wpc_integrations->purge_site();

						$wpc_used_new_integrations = true;
					}
				}
			}

			if ( ! $wpc_used_new_integrations ) {
				$wpc_cache_logic = new wps_ic_cache();
				if ( '' !== $post_id ) {
					if ( is_callable( array( 'wps_ic_cache', 'removeHtmlCacheFiles' ) ) ) {
						wps_ic_cache::removeHtmlCacheFiles( $post_id );
					}
					if ( is_callable( array( 'wps_ic_cache', 'removeCriticalFiles' ) ) ) {
						wps_ic_cache::removeCriticalFiles( $post_id );
					}
					if ( is_callable( array( 'wps_ic_cache', 'removeCombinedFiles' ) ) ) {
						wps_ic_cache::removeCombinedFiles( $post_id );
					}
					// Always clear homepage cache.
					if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
						if ( is_callable( array( 'wps_ic_cache', 'removeHtmlCacheFiles' ) ) ) {
							wps_ic_cache::removeHtmlCacheFiles( $homepage_id );
						}
						if ( is_callable( array( 'wps_ic_cache', 'removeCriticalFiles' ) ) ) {
							wps_ic_cache::removeCriticalFiles( $homepage_id );
						}
						if ( is_callable( array( 'wps_ic_cache', 'removeCombinedFiles' ) ) ) {
							wps_ic_cache::removeCombinedFiles( $homepage_id );
						}
					}
				} else {
					// Clear entire website cache.
					if ( class_exists( 'wps_ic_cache_integrations' ) ) {
						$wpc_cache = new wps_ic_cache_integrations();
						if ( is_callable( array( 'wps_ic_cache_integrations', 'purgeAll' ) ) ) {
							wps_ic_cache_integrations::purgeAll( false, true, false, false );
						}
						if ( is_callable( array( 'wps_ic_cache_integrations', 'purgeCombinedFiles' ) ) ) {
							wps_ic_cache_integrations::purgeCombinedFiles();
						}
					}
					if ( is_callable( array( 'wps_ic_cache', 'removeHtmlCacheFiles' ) ) ) {
						wps_ic_cache::removeHtmlCacheFiles( 'all' );
					}
					if ( is_callable( array( 'wps_ic_cache', 'removeCriticalFiles' ) ) ) {
						wps_ic_cache::removeCriticalFiles( 'all' );
					}
				}
			}
		}

		// Swift Performance
		if ( class_exists( 'Swift_Performance_Cache' ) && is_callable( array( 'Swift_Performance_Cache', 'clear_all_cache' ) ) ) {
			// Swift Performance only provides clear_all_cache() method.
			// It automatically clears single post cache when posts are updated,
			// but there's no documented API for clearing specific posts programmatically.
			Swift_Performance_Cache::clear_all_cache();
		}

		// EWWW Image Optimizer - SWIS Performance
		if ( has_action( 'swis_clear_page_cache_by_post_id' ) || has_action( 'swis_clear_site_cache' ) ) {
			if ( '' !== $post_id && has_action( 'swis_clear_page_cache_by_post_id' ) ) {
				do_action( 'swis_clear_page_cache_by_post_id', $post_id );
				// Always clear homepage cache.
				if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
					do_action( 'swis_clear_page_cache_by_post_id', $homepage_id );
				} elseif ( 0 === $homepage_id && has_action( 'swis_clear_page_cache_by_url' ) ) {
					// For blog homepage, use URL-based clearing.
					do_action( 'swis_clear_page_cache_by_url', home_url( '/' ) );
				}
			} elseif ( has_action( 'swis_clear_site_cache' ) ) {
				do_action( 'swis_clear_site_cache' );
			}
		}

		// Cloudflare WordPress Plugin (latest API - Cloudflare\APO namespace)
		if ( class_exists( '\Cloudflare\APO\WordPress\Hooks' ) ) {
			// Try to get instance using common singleton patterns.
			$cloudflare_hooks_instance = null;
			if ( is_callable( array( '\Cloudflare\APO\WordPress\Hooks', 'get_instance' ) ) ) {
				$cloudflare_hooks_instance = call_user_func( array( '\Cloudflare\APO\WordPress\Hooks', 'get_instance' ) );
			} elseif ( isset( $GLOBALS['cloudflare_hooks'] ) && is_object( $GLOBALS['cloudflare_hooks'] ) ) {
				$cloudflare_hooks_instance = $GLOBALS['cloudflare_hooks'];
			}

			if ( null !== $cloudflare_hooks_instance && is_object( $cloudflare_hooks_instance ) ) {
				if ( '' !== $post_id && method_exists( $cloudflare_hooks_instance, 'purgeCacheByRelevantURLs' ) ) {
					$cloudflare_hooks_instance->purgeCacheByRelevantURLs( $post_id );
					// Always clear homepage cache.
					if ( $homepage_id > 0 && $homepage_id !== (int) $post_id ) {
						$cloudflare_hooks_instance->purgeCacheByRelevantURLs( $homepage_id );
					} elseif ( 0 === $homepage_id ) {
						$homepage_url = home_url( '/' );
						$cloudflare_hooks_instance->purgeCacheByRelevantURLs( array( array( 'url' => $homepage_url ) ) );
					}
				} elseif ( '' === $post_id && method_exists( $cloudflare_hooks_instance, 'purgeCacheEverything' ) ) {
					$cloudflare_hooks_instance->purgeCacheEverything();
				}
			}
		}
	} catch( Exception $err ) {
		ET_Core_Logger::error( 'An exception occurred while attempting to clear site cache.' );
	}
}
endif;


if ( ! function_exists( 'et_core_get_nonces' ) ):
/**
 * Returns the nonces for this component group.
 *
 * @return string[]
 */
function et_core_get_nonces() {
	static $nonces = null;

	return $nonces ? $nonces : $nonces = array(
		'clear_page_resources_nonce' => wp_create_nonce( 'clear_page_resources' ),
		'et_core_portability_export' => wp_create_nonce( 'et_core_portability_export' ),
	);
}
endif;


if ( ! function_exists( 'et_core_page_resource_auto_clear' ) ):
function et_core_page_resource_auto_clear() {
	ET_Core_PageResource::remove_static_resources( 'all', 'all' );
}
add_action( 'switch_theme', 'et_core_page_resource_auto_clear' );
add_action( 'after_switch_theme', 'et_core_page_resource_auto_clear' );
add_action( 'activated_plugin', 'et_core_page_resource_auto_clear', 10, 0 );
add_action( 'deactivated_plugin', 'et_core_page_resource_auto_clear', 10, 0 );
add_action( 'upgrader_process_complete', 'et_core_page_resource_auto_clear', 10, 2 );
endif;


if ( ! function_exists( 'et_core_page_resource_clear' ) ):
/**
 * Ajax handler for clearing cached page resources.
 */
function et_core_page_resource_clear() {
	et_core_security_check( 'manage_options', 'clear_page_resources' );

	if ( empty( $_POST['et_post_id'] ) ) {
		et_core_die();
	}

	$post_id     = sanitize_key( $_POST['et_post_id'] );
	$owner       = sanitize_key( $_POST['et_owner'] );
	$delete_files = ! empty( $_POST['et_delete_files'] ) && 'true' === $_POST['et_delete_files'];

	ET_Core_PageResource::remove_static_resources( $post_id, $owner, false, 'all', false, $delete_files );
}
add_action( 'wp_ajax_et_core_page_resource_clear', 'et_core_page_resource_clear' );
endif;


if ( ! function_exists( 'et_core_page_resource_get' ) ):
/**
 * Get a page resource instance.
 *
 * @param string     $owner    The owner of the instance (core|divi|builder|bloom|monarch|custom).
 * @param string     $slug     A string that uniquely identifies the resource.
 * @param string|int $post_id  The post id that the resource is associated with or `global`.
 *                             If `null`, the return value of {@link get_the_ID()} will be used.
 * @param string     $type     The resource type (style|script). Default: `style`.
 * @param string     $location Where the resource should be output (head|footer). Default: `head-late`.
 *
 * @return ET_Core_PageResource
 */
function et_core_page_resource_get( $owner, $slug, $post_id = null, $priority = 10, $location = 'head-late', $type = 'style' ) {
	// Use null check instead of truthy check to preserve 0 as a valid post_id value.
	$post_id = null !== $post_id ? $post_id : et_core_page_resource_get_the_ID();

	// Generate lookup slug matching the filename generation logic in PageResource constructor.
	// Use consolidated method to determine if post_id should be excluded.
	$lookup_post_id = '';
	if ( ! ET_Core_PageResource::should_exclude_post_id_from_filename( $post_id, $slug ) ) {
		$lookup_post_id = '-' . $post_id;
	}
	$_slug = "et-{$owner}-{$slug}{$lookup_post_id}-cached-inline-{$type}s";

	$all_resources = ET_Core_PageResource::get_resources();

	return isset( $all_resources[ $_slug ] )
		? $all_resources[ $_slug ]
		: new ET_Core_PageResource( $owner, $slug, $post_id, $priority, $location, $type );
}
endif;


if ( ! function_exists( 'et_core_page_resource_get_the_ID' ) ):
function et_core_page_resource_get_the_ID() {
	static $post_id = null;

	if ( is_int( $post_id ) ) {
		return $post_id;
	}

	return $post_id = apply_filters( 'et_core_page_resource_current_post_id', get_the_ID() );
}
endif;


if ( ! function_exists( 'et_core_page_resource_is_singular' ) ):
function et_core_page_resource_is_singular() {
	return apply_filters( 'et_core_page_resource_is_singular', is_singular() );
}
endif;


if ( ! function_exists( 'et_debug' ) ):
function et_debug( $msg, $bt_index = 4, $log_ajax = true ) {
	ET_Core_Logger::debug( $msg, $bt_index, $log_ajax );
}
endif;


if ( ! function_exists( 'et_wrong' ) ):
function et_wrong( $msg, $error = false ) {
	$msg = "You're Doing It Wrong! {$msg}";

	if ( $error ) {
		et_error( $msg );
	} else {
		et_debug( $msg );
	}
}
endif;


if ( ! function_exists( 'et_error' ) ):
function et_error( $msg, $bt_index = 4 ) {
	ET_Core_Logger::error( "[ERROR]: {$msg}", $bt_index );
}
endif;
