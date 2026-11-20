<?php return array(
    'root' => array(
        'name' => 'civicrm/ses',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '7d0147251a109f117aa93f5a8535a1b0c5352299',
        'type' => 'civicrm-extension',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'aws/aws-crt-php' => array(
            'pretty_version' => 'v1.2.7',
            'version' => '1.2.7.0',
            'reference' => 'd71d9906c7bb63a28295447ba12e74723bd3730e',
            'type' => 'library',
            'install_path' => __DIR__ . '/../aws/aws-crt-php',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'aws/aws-sdk-php' => array(
            'pretty_version' => '3.394.10',
            'version' => '3.394.10.0',
            'reference' => '3b7cbf17b8ba568f77546d1e5edd3f2071861c8f',
            'type' => 'library',
            'install_path' => __DIR__ . '/../aws/aws-sdk-php',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'civicrm/ses' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '7d0147251a109f117aa93f5a8535a1b0c5352299',
            'type' => 'civicrm-extension',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'guzzlehttp/guzzle' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '^6.3 || ^7.3',
            ),
        ),
        'guzzlehttp/promises' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '^2.0',
            ),
        ),
        'guzzlehttp/psr7' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '^1.9 || ^2.0',
            ),
        ),
        'mtdowling/jmespath.php' => array(
            'pretty_version' => '2.9.2',
            'version' => '2.9.2.0',
            'reference' => '2157c5e50e813ec6a96c1eed3be7f64a20fb32a8',
            'type' => 'library',
            'install_path' => __DIR__ . '/../mtdowling/jmespath.php',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'psr/http-message' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '^1.1 || ^2.0',
            ),
        ),
        'symfony/filesystem' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '^6.4',
            ),
        ),
        'symfony/polyfill-ctype' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '^1.8',
            ),
        ),
        'symfony/polyfill-mbstring' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '^1.17',
            ),
        ),
    ),
);
