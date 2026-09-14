<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Bot classification engine.
 *
 * Composes the UA signature matcher, anti-spoofing real-IP resolver, bot-IP
 * range lookups, datacenter detector, and header anomaly scorer into a
 * single classify() call that returns a {@see Classification} — one of the
 * six Category constants paired with the matched bot signature token.
 *
 * Decision pipeline (order matters):
 *   1. Honeypot triggered                              -> MALICIOUS_BOT
 *   2. UA matches malicious tool signature             -> MALICIOUS_BOT
 *   3. UA matches search-engine signature + IP in bundled range -> VERIFIED_SEARCH_ENGINE
 *      UA matches search-engine signature + IP not in range, but rDNS verifier
 *                                          forward-confirms a provider suffix -> VERIFIED_SEARCH_ENGINE
 *      UA matches search-engine signature otherwise   -> UNVERIFIED_BOT
 *   4. UA matches seo-crawler signature + IP in bundled range -> VERIFIED_SEO_CRAWLER
 *      UA matches seo-crawler signature + IP not in range, but rDNS verifier
 *                                          forward-confirms a provider suffix -> VERIFIED_SEO_CRAWLER
 *      UA matches seo-crawler signature otherwise      -> UNVERIFIED_BOT
 *   5. UA matches ai-crawler signature + IP matches    -> VERIFIED_AI_CRAWLER
 *      UA matches ai-crawler signature + IP not in range, but rDNS verifier
 *                                          forward-confirms a provider suffix -> VERIFIED_AI_CRAWLER
 *      UA matches ai-crawler signature + IP not in range, but ClaudeBot UA
 *                                          from datacenter IP (weak verify)   -> VERIFIED_AI_CRAWLER
 *      UA matches ai-crawler signature otherwise      -> UNVERIFIED_BOT
 *   6. Empty / whitespace-only UA                      -> UNVERIFIED_BOT
 *   7. Datacenter IP + at least one header anomaly     -> UNKNOWN_AUTOMATED
 *   8. Otherwise                                       -> HUMAN
 *
 * The real client IP is determined by RealIpResolver before any verification
 * step runs, so forged CDN headers from a direct attacker never succeed in
 * impersonating a verified bot provider.
 *
 * @since 4.0.0
 */
class Classifier {

	/**
	 * Minimum header anomaly score that promotes a datacenter-origin
	 * request to Unknown Automated. One anomaly is a very low bar,
	 * intentionally — the classifier only reaches this branch when no
	 * bot UA matched, so a genuinely clean browser request from a
	 * datacenter IP is still left as HUMAN.
	 */
	const UNKNOWN_AUTOMATED_MIN_ANOMALIES = 1;

	/**
	 * UA signature matcher configured with malicious / search-engine / ai-crawler categories.
	 *
	 * @var UserAgentSignatures
	 */
	private $ua_signatures;

	/**
	 * Anti-spoofing resolver used to derive the real client IP before verification.
	 *
	 * @var RealIpResolver
	 */
	private $real_ip_resolver;

	/**
	 * Bot-IP lookup restricted to verified search-engine providers.
	 *
	 * @var IpRangeLookup
	 */
	private $search_engine_ips;

	/**
	 * Bot-IP lookup restricted to verified AI-crawler providers.
	 *
	 * @var IpRangeLookup
	 */
	private $ai_crawler_ips;

	/**
	 * Bot-IP lookup restricted to verified SEO-crawler providers.
	 *
	 * @var IpRangeLookup
	 */
	private $seo_crawler_ips;

	/**
	 * Datacenter IP detector driving the Unknown Automated signal.
	 *
	 * @var DatacenterDetector
	 */
	private $datacenter;

	/**
	 * Optional rDNS verifier for search-engine providers whose IP ranges
	 * are not bundled (Yandex, Baidu, Sogou, Seznam, Naver, Mojeek).
	 *
	 * @var RdnsVerifier|null
	 */
	private $rdns_verifier;

	/**
	 * Optional rDNS verifier for AI-crawler providers whose IP ranges
	 * are not bundled (Amazonbot, CCBot, Bytespider, YouBot).
	 *
	 * @var RdnsVerifier|null
	 */
	private $ai_rdns_verifier;

	/**
	 * Optional rDNS verifier for SEO-crawler providers that publish a
	 * documented PTR suffix (Barkrowler / .babbar.eu).
	 *
	 * @var RdnsVerifier|null
	 */
	private $seo_rdns_verifier;

	/**
	 * Wire up the classifier over its component primitives.
	 *
	 * @param UserAgentSignatures $ua_signatures     UA matcher with malicious / search-engine / seo-crawler / ai-crawler categories.
	 * @param RealIpResolver      $real_ip_resolver  Anti-spoofing client-IP resolver.
	 * @param IpRangeLookup       $search_engine_ips Bot-IP lookup for verified search engines.
	 * @param IpRangeLookup       $ai_crawler_ips    Bot-IP lookup for verified AI crawlers.
	 * @param IpRangeLookup       $seo_crawler_ips   Bot-IP lookup for verified SEO crawlers.
	 * @param DatacenterDetector  $datacenter        Datacenter IP detector for Unknown Automated signal.
	 * @param RdnsVerifier|null   $rdns_verifier     Optional FCrDNS verifier for non-bundled search-engine providers.
	 * @param RdnsVerifier|null   $ai_rdns_verifier  Optional FCrDNS verifier for non-bundled AI-crawler providers.
	 * @param RdnsVerifier|null   $seo_rdns_verifier Optional FCrDNS verifier for SEO-crawler providers with a documented PTR suffix.
	 */
	public function __construct(
		$ua_signatures,
		$real_ip_resolver,
		$search_engine_ips,
		$ai_crawler_ips,
		$seo_crawler_ips,
		$datacenter,
		$rdns_verifier = null,
		$ai_rdns_verifier = null,
		$seo_rdns_verifier = null
	) {
		$this->ua_signatures     = $ua_signatures;
		$this->real_ip_resolver  = $real_ip_resolver;
		$this->search_engine_ips = $search_engine_ips;
		$this->ai_crawler_ips    = $ai_crawler_ips;
		$this->seo_crawler_ips   = $seo_crawler_ips;
		$this->datacenter        = $datacenter;
		$this->rdns_verifier     = $rdns_verifier;
		$this->ai_rdns_verifier  = $ai_rdns_verifier;
		$this->seo_rdns_verifier = $seo_rdns_verifier;
	}

	/**
	 * Classify a request into one of the six Category values plus the matched
	 * bot signature.
	 *
	 * The returned {@see Classification} pairs the category with the signature
	 * token that produced it (e.g. "GPTBot"). The token is populated only on
	 * the three UA-signature branches — malicious, search-engine, ai-crawler,
	 * including their UNVERIFIED_BOT downgrades — and is '' for honeypot,
	 * empty-UA, datacenter-heuristic, and human outcomes, none of which come
	 * from a named signature.
	 *
	 * @param array  $headers            Request headers (case-insensitive keys).
	 * @param string $remote_addr        Socket peer IP.
	 * @param string $server_protocol    Value of $_SERVER['SERVER_PROTOCOL'] (e.g. "HTTP/2.0").
	 * @param bool   $honeypot_triggered Whether the pipeline detected a honeypot hit.
	 * @return Classification Category + matched signature token.
	 */
	public function classify( $headers, $remote_addr, $server_protocol = '', $honeypot_triggered = false ) {
		if ( true === $honeypot_triggered ) {
			return new Classification( Category::MALICIOUS_BOT );
		}

		$normalised = RealIpResolver::normaliseHeaders( $headers );
		$ua         = isset( $normalised['user-agent'] ) && is_string( $normalised['user-agent'] )
			? $normalised['user-agent']
			: '';
		$client_ip  = $this->real_ip_resolver->resolveFromNormalised( $normalised, $remote_addr );

		// Check UA categories in Classifier's documented priority order rather
		// than delegating to UserAgentSignatures::classify(), which returns the
		// first match in *its* registration order. Bundled token lists overlap
		// (e.g. "Scrapy" in ai-crawler and malicious), so enforcing priority
		// here is load-bearing: a UA that matches both categories must take
		// the higher-priority classification.
		if ( $this->ua_signatures->matchesCategory( $ua, UserAgentSignatures::CATEGORY_MALICIOUS ) ) {
			$bot = $this->ua_signatures->matchedToken( $ua, UserAgentSignatures::CATEGORY_MALICIOUS );
			return new Classification( Category::MALICIOUS_BOT, $bot );
		}

		if ( $this->ua_signatures->matchesCategory( $ua, UserAgentSignatures::CATEGORY_SEARCH_ENGINE ) ) {
			$bot = $this->ua_signatures->matchedToken( $ua, UserAgentSignatures::CATEGORY_SEARCH_ENGINE );
			if ( null !== $this->search_engine_ips->find( $client_ip ) ) {
				return new Classification( Category::VERIFIED_SEARCH_ENGINE, $bot );
			}
			// Bundled IP range missed — fall through to forward-confirmed
			// reverse DNS for providers whose ranges we don't ship
			// (Yandex/Baidu/Sogou/Seznam/Naver/Mojeek). The verifier is
			// optional; when not wired, behaviour matches Phase-0: UA
			// match without IP confirmation drops to UNVERIFIED_BOT.
			if ( null !== $this->rdns_verifier && $this->rdns_verifier->verifyAgainstUa( $client_ip, $ua ) ) {
				return new Classification( Category::VERIFIED_SEARCH_ENGINE, $bot );
			}
			return new Classification( Category::UNVERIFIED_BOT, $bot );
		}

		if ( $this->ua_signatures->matchesCategory( $ua, UserAgentSignatures::CATEGORY_SEO_CRAWLER ) ) {
			$bot = $this->ua_signatures->matchedToken( $ua, UserAgentSignatures::CATEGORY_SEO_CRAWLER );
			if ( null !== $this->seo_crawler_ips->find( $client_ip ) ) {
				return new Classification( Category::VERIFIED_SEO_CRAWLER, $bot );
			}
			// Bundled IP range missed — try forward-confirmed reverse DNS for
			// SEO crawlers that publish a PTR suffix (Barkrowler / .babbar.eu).
			// SEO crawlers without any published IP list or rDNS (SemrushBot,
			// DotBot, MJ12bot) never reach a verify path and drop to
			// UNVERIFIED_BOT — the intended throttle for third-party index bots.
			if ( null !== $this->seo_rdns_verifier && $this->seo_rdns_verifier->verifyAgainstUa( $client_ip, $ua ) ) {
				return new Classification( Category::VERIFIED_SEO_CRAWLER, $bot );
			}
			return new Classification( Category::UNVERIFIED_BOT, $bot );
		}

		if ( $this->ua_signatures->matchesCategory( $ua, UserAgentSignatures::CATEGORY_AI_CRAWLER ) ) {
			$bot = $this->ua_signatures->matchedToken( $ua, UserAgentSignatures::CATEGORY_AI_CRAWLER );
			if ( null !== $this->ai_crawler_ips->find( $client_ip ) ) {
				return new Classification( Category::VERIFIED_AI_CRAWLER, $bot );
			}
			if ( null !== $this->ai_rdns_verifier && $this->ai_rdns_verifier->verifyAgainstUa( $client_ip, $ua ) ) {
				return new Classification( Category::VERIFIED_AI_CRAWLER, $bot );
			}
			if ( $this->isClaudeBot( $ua ) && 'aws' === $this->datacenter->provider( $client_ip ) ) {
				return new Classification( Category::VERIFIED_AI_CRAWLER, $bot );
			}
			return new Classification( Category::UNVERIFIED_BOT, $bot );
		}

		// Empty / whitespace-only UA is a strong bot signal: well-behaved
		// HTTP clients identify themselves, including most CLI tools
		// (curl, wget) and headless browsers. Empty-UA traffic from any
		// IP is overwhelmingly automated. Wordfence's reference design
		// treats it as "always crawler"; we land it as UNVERIFIED_BOT
		// (BALANCED preset → 2 req/min) rather than HUMAN. Allowlist
		// matchers run BEFORE classify() in Pipeline::runInner, so
		// wp-cron loopbacks, monitoring services with documented
		// empty-UA quirks, and similar internals stay unaffected.
		if ( '' === trim( $ua ) ) {
			return new Classification( Category::UNVERIFIED_BOT );
		}

		if ( $this->datacenter->isDatacenter( $client_ip ) ) {
			$anomalies = HeaderAnomalyScorer::scoreFromNormalised( $normalised, $server_protocol );
			if ( $anomalies >= self::UNKNOWN_AUTOMATED_MIN_ANOMALIES ) {
				return new Classification( Category::UNKNOWN_AUTOMATED );
			}
		}

		return new Classification( Category::HUMAN );
	}

	/**
	 * Check whether the UA identifies as ClaudeBot.
	 *
	 * @param string $ua User-Agent string.
	 * @return bool
	 */
	private function isClaudeBot( $ua ) {
		return 1 === preg_match( '/\bClaudeBot\b/i', $ua );
	}
}
