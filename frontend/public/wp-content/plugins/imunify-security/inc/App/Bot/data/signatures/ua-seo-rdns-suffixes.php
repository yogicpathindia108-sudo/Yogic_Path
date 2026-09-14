<?php
defined( 'ABSPATH' ) || exit;
// Auto-generated. Do not edit by hand.

return array(
    'source_url' => 'MANUAL (provider docs — babbar.tech/crawler)',
    'fetched_at' => '2026-07-23T16:03:41+00:00',
    'checksum' => 'sha256:6c2da7de32d75db44a8da9cbb5ebf3c6c5588b7194a472ad14c4c31a0d4e301c',
    'note' => 'SEO crawler rDNS providers. Same FCrDNS mechanism as ua-rdns-suffixes.php. Consulted only after the bundled IpRangeLookup misses for SEO crawler UAs. Barkrowler publishes the .babbar.eu PTR namespace as an official verification path.',
    'providers' => array(
        'barkrowler' => array(
            'tokens' => array(
                'Barkrowler',
            ),
            'suffixes' => array(
                '.babbar.eu',
            ),
        ),
    ),
);
