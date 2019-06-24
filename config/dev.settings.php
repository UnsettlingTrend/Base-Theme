<?php

/**
 *  Custom configuration settings for the development server
 */

$config_directories['local'] = '../config/dev';

$settings['trusted_host_patterns'][] = [
  '^dev\.chrisferagotti\.com$',
  '54.174.234.165'
];

$databases['default']['default'] = [
  'database' => 'drupal',
  'username' => 'drupal',
  'password' => 'FK9DzauSMtxm9ywY',
  'prefix' => '',
  'host' => 'dev-com-chrisferagotti-unsettlingtrend-db.c1bni6hcamtp.us-east-1.rds.amazonaws.com',
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
];