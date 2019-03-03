<?php

/**
 *  Custom configuration settings for the testing server
 */

$config_directories['local'] = '../config/test';

$settings['trusted_host_patterns'][] = [
  '^test\.chrisferagotti\.com$',
];

$databases['default']['default'] = [
  'database' => '',
  'username' => '',
  'password' => '',
  'prefix' => '',
  'host' => 'database',
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
];