<?php
defined( 'ABSPATH' ) || exit;
// Auto-generated. Do not edit by hand.

return array(
    'source_url' => 'MANUAL (provider docs — developer.amazon.com/amazonbot, commoncrawl.org/faq, bytedance.com, you.com/bot)',
    'fetched_at' => '2026-06-11T08:45:54+00:00',
    'checksum' => 'sha256:e4fe1b04797cf67c40d7ce78294ddd7cef360c11061c0a7b16aae7d417255bcb',
    'note' => 'AI crawler rDNS providers. Same FCrDNS mechanism as ua-rdns-suffixes.php. Consulted only after the bundled IpRangeLookup misses for AI crawler UAs.',
    'providers' => array(
        'amazon' => array(
            'tokens' => array(
                'Amazonbot',
                'Amzn-SearchBot',
            ),
            'suffixes' => array(
                '.amazonbot.amazon',
            ),
        ),
        'commoncrawl' => array(
            'tokens' => array(
                'CCBot',
            ),
            'suffixes' => array(
                '.commoncrawl.org',
            ),
        ),
        'bytedance' => array(
            'tokens' => array(
                'Bytespider',
            ),
            'suffixes' => array(
                '.bytedance.com',
            ),
        ),
        'you' => array(
            'tokens' => array(
                'YouBot',
            ),
            'suffixes' => array(
                '.you.com',
            ),
        ),
    ),
);
