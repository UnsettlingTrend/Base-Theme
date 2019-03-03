<?php

/**
 *  Custom configuration settings for the development server
 */

$config_directories['local'] = '../config/dev';

$settings['trusted_host_patterns'][] = [
  '^dev\.chrisferagotti\.com$',
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