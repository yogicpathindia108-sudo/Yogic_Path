<?php
defined( 'ABSPATH' ) || exit;
// Auto-generated. Do not edit by hand.

return array(
    'source_url' => 'MANUAL',
    'fetched_at' => '2026-06-11T08:45:54+00:00',
    'checksum' => 'sha256:97d8ac7955f73c27a290ba15ac135e85a6a831f852fcb047bf9abda09825b5d3',
    'signatures' => array(
        'sqlmap',
        'nikto',
        'nmap',
        'masscan',
        'zgrab',
        'Nessus',
        'OpenVAS',
        'w3af',
        'skipfish',
        'dirbuster',
        'gobuster',
        'wpscan',
        'acunetix',
        'netsparker',
        'WebInspect',
        'AppScan',
        'havij',
        'arachni',
        'wfuzz',
        'hydra',
        'medusa',
        'feroxbuster',
        'ffuf',
        'wget/0',
        'Baiduspider/test',
        'Scrapy',
        'Xenu Link Sleuth',
    ),
    'note' => 'offensive security tools; deliberately excludes generic HTTP-library UAs (python-requests, go-http-client, curl) which have overwhelming legitimate use',
);
