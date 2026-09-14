<?php
defined( 'ABSPATH' ) || exit;
// Auto-generated. Do not edit by hand.

return array(
    'source_url' => 'MANUAL (provider docs — yandex.com/support/webmaster, baidu.com/spider help, datadome.co/bots, seznam-napoveda, naver developer docs, mojeek.com/bot.html)',
    'fetched_at' => '2026-06-11T08:45:54+00:00',
    'checksum' => 'sha256:029a8c4b64f7ce7f94af1a4fdbe4b8c4e66b1a21b8a5f289d94bc9e6890ee744',
    'note' => 'Each provider entry binds a UA-token list (case-insensitive substring match, same semantics as ua-search-engines.php) to the documented PTR suffixes. Classifier consults this map only after the bundled IpRangeLookup misses, so the rDNS round-trip is paid solely for providers whose IP ranges we deliberately do not bundle.',
    'providers' => array(
        'baidu' => array(
            'tokens' => array(
                'Baiduspider',
                'Baiduspider-image',
            ),
            'suffixes' => array(
                '.crawl.baidu.com',
                '.baidu.jp',
            ),
        ),
        'mojeek' => array(
            'tokens' => array(
                'MojeekBot',
            ),
            'suffixes' => array(
                '.mojeek.com',
            ),
        ),
        'naver' => array(
            'tokens' => array(
                'NaverBot',
                'Yeti',
            ),
            'suffixes' => array(
                '.naver.com',
            ),
        ),
        'petal' => array(
            'tokens' => array(
                'PetalBot',
            ),
            'suffixes' => array(
                '.petalsearch.com',
            ),
        ),
        'seznam' => array(
            'tokens' => array(
                'SeznamBot',
            ),
            'suffixes' => array(
                '.seznam.cz',
            ),
        ),
        'sogou' => array(
            'tokens' => array(
                'Sogou',
                'Sogou web spider',
            ),
            'suffixes' => array(
                '.crawl.sogou.com',
                '.sogou.com',
            ),
        ),
        'yandex' => array(
            'tokens' => array(
                'YandexBot',
                'YandexImages',
                'YandexMobileBot',
            ),
            'suffixes' => array(
                '.yandex.com',
                '.yandex.ru',
                '.yandex.net',
            ),
        ),
    ),
);
