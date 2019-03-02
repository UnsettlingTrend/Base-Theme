<?php

/**
 *  Custom configuration settings for the production server
 */

$config_directories['local'] = '../config/prod';

$settings['trusted_host_patterns'][] = [
  '^chrisferagotti\.com$',
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