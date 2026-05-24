<?php
/**
 * @file
 * Upsun settings.
 */

$config['system.logging']['error_level']='verbose';

use Drupal\Core\Installer\InstallerKernel;

$platformsh = new \Platformsh\ConfigReader\Config();

// Configure the database.
if ($platformsh->hasRelationship('database')) {
  $creds = $platformsh->credentials('database');
  $databases['default']['default'] = [
    'driver' => $creds['scheme'],
    'database' => $creds['path'],
    'username' => $creds['username'],
    'password' => $creds['password'],
    'host' => $creds['host'],
    'port' => $creds['port'],
    'pdo' => [PDO::MYSQL_ATTR_COMPRESS => !empty($creds['query']['compression'])]
  ];
}

// Enable verbose error messages on development branches, but not on the production branch.
if (isset($platformsh->branch)) {
  // Production type environment.
  if ($platformsh->branch == 'master' || $platformsh->branch == 'main' || $platformsh->onDedicated()) {
    $config['system.logging']['error_level'] = 'hide';
  } // Development type environment.
  else {
    $config['system.logging']['error_level'] = 'verbose';
  }
}

// Enable Redis caching.
if ($platformsh->hasRelationship('redis') && !InstallerKernel::installationAttempted() && extension_loaded('redis') && class_exists('Drupal\redis\ClientFactory')) {
  $redis = $platformsh->credentials('redis');

  // Set Redis as the default backend for any cache bin not otherwise specified.
  $settings['cache']['default'] = 'cache.backend.redis';
  $settings['redis.connection']['host'] = $redis['host'];
  $settings['redis.connection']['port'] = $redis['port'];

  // Apply changes to the container configuration to better leverage Redis.
  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';

  // Allow the services to work before the Redis module itself is enabled.
  $settings['container_yamls'][] = 'modules/contrib/redis/redis.services.yml';

  // Manually add the classloader path, this is required for the container cache bin definition below
  // and allows to use it without the redis module being enabled.
  $class_loader->addPsr4('Drupal\\redis\\', 'modules/contrib/redis/src');

  // Use redis for container cache.
  $settings['bootstrap_container_definition'] = [
    'parameters' => [],
    'services' => [
      'redis.factory' => [
        'class' => 'Drupal\redis\ClientFactory',
      ],
      'cache.backend.redis' => [
        'class' => 'Drupal\redis\Cache\CacheBackendFactory',
        'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.phpserialize'],
      ],
      'cache.container' => [
        'class' => '\Drupal\redis\Cache\PhpRedis',
        'factory' => ['@cache.backend.redis', 'get'],
        'arguments' => ['container'],
      ],
      'cache_tags_provider.container' => [
        'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
        'arguments' => ['@redis.factory'],
      ],
      'serialization.phpserialize' => [
        'class' => 'Drupal\Component\Serialization\PhpSerialize',
      ],
    ],
  ];
}

if ($platformsh->inRuntime()) {
  // Configure private and temporary file paths.
  if (!isset($settings['file_private_path'])) {
    $settings['file_private_path'] = $platformsh->appDir . '/private';
  }
  if (!isset($settings['file_temp_path'])) {
    $settings['file_temp_path'] = $platformsh->appDir . '/tmp';
  }

  // Set the project-specific entropy value, used for generating one-time
  // keys and such.
  $settings['hash_salt'] = $settings['hash_salt'] ?? $platformsh->projectEntropy;

  // Set the deployment identifier, which is used by some Drupal cache systems.
  $settings['deployment_identifier'] = $settings['deployment_identifier'] ?? $platformsh->treeId;
}

// Upsun manages the Host header so all values are guaranteed safe.
$settings['trusted_host_patterns'] = ['.*'];

// Import variables prefixed with 'drupalsettings:' into $settings
// and 'drupalconfig:' into $config.
foreach ($platformsh->variables() as $name => $value) {
  $parts = explode(':', $name);
  list($prefix, $key) = array_pad($parts, 3, null);
  switch ($prefix) {
    case 'drupalsettings':
    case 'drupal':
      $settings[$key] = $value;
      break;
    case 'drupalconfig':
      if (count($parts) > 2) {
        $temp = &$config[$key];
        foreach (array_slice($parts, 2) as $n) {
          $prev = &$temp;
          $temp = &$temp[$n];
        }
        $prev[$n] = $value;
      }
      break;
  }
}

// Configure solr search
$platformsh->registerFormatter('drupal-solr', function($solr) {
  return [
    'core' => substr($solr['path'], 5) ? : 'collection1',
    'path' => '',
    'host' => $solr['host'],
    'port' => $solr['port'],
  ];
});

$relationship_name = 'search';
$solr_server_name = 'solr';
if ($platformsh->hasRelationship($relationship_name)) {
  $config['search_api.server.' . $solr_server_name]['backend_config']['connector_config'] = $platformsh->formattedCredentials($relationship_name, 'drupal-solr');
}

$settings["config_sync_directory"] = '../config/sync/default';
$settings['file_private_path'] = '../private';

// Make sure all configs are disabled....
$config['config_split.config_split.local']['status'] = FALSE;
$config['config_split.config_split.non_production']['status'] = FALSE;
$config['config_split.config_split.production']['status'] = FALSE;
// ... then turn on the ones needed for the environment.
if (isset($platformsh->branch)) {
  // If this is an Upsun environment, set according to branch/environment
  switch ($platformsh->branch) {
    case 'main':
      $config['config_split.config_split.production']['status'] = TRUE;
      break;
    case 'develop':
      $config['config_split.config_split.non_production']['status'] = TRUE;
      $config['config_split.config_split.develop']['status'] = TRUE;
      break;
    default:
      $config['config_split.config_split.develop']['status'] = TRUE;
      $config['config_split.config_split.local']['status'] = TRUE;
  }
}
else {
  // else, assume local environment.
  $config['config_split.config_split.non_production']['status'] = TRUE;
  $config['config_split.config_split.local']['status'] = TRUE;
}
// Add non_production configuration directory if needed.
if (getenv('UPSUN_ENVIRONMENT_TYPE') !== 'production') {
  $config['config_split.config_split.non_production']['status'] = TRUE;
}

$config['swiftmailer.transport'] = [
  'transport' => 'sendmail',
  'smtp_host' => getenv('UPSUN_SMTP_HOST'),
];
$settings['hash_salt'] = 'SXGNp9wMkgups2dhCJikKb_56ND4Q05Rz3O6D_oDxwEcpBISgDeYYWW_9Wm2e36wCeDADtSd2';

// Add settings from variables stored in Upsun console (check existence first)
$upsun_variables = json_decode(base64_decode(getenv("UPSUN_VARIABLES")), TRUE);
// Creds for Google reCAPTCHA
$config['recaptcha.settings']['site_key'] = !empty($upsun_variables['CREDS_RECAPTCHA_SITE_KEY']) ? $upsun_variables['CREDS_RECAPTCHA_SITE_KEY'] : '<environment-variable>';
$config['recaptcha.settings']['secret_key'] = !empty($upsun_variables['CREDS_RECAPTCHA_API_KEY']) ? $upsun_variables['CREDS_RECAPTCHA_API_KEY'] : '<environment-variable>';
// Creds for Google Maps API
$config['geolocation_google_maps.settings']['google_map_api_key'] = !empty($upsun_variables['CREDS_GOOGLE_MAPS_PLATFORM_API_KEY']) ? $upsun_variables['CREDS_GOOGLE_MAPS_PLATFORM_API_KEY'] : '<environment-variable>';
$config['geolocation_google_maps.settings']['google_map_api_server_key'] = !empty($upsun_variables['CREDS_GOOGLE_MAPS_PLATFORM_API_SERVER_KEY']) ? $upsun_variables['CREDS_GOOGLE_MAPS_PLATFORM_API_SERVER_KEY'] : '<environment-variable>';
// Creds for Google Authenticator API
$config['social_auth_google.settings']['client_id'] = !empty($upsun_variables['CREDS_OAUTH_CLIENT_ID']) ? $upsun_variables['CREDS_OAUTH_CLIENT_ID'] : '<environment-variable>';
$config['social_auth_google.settings']['client_secret'] = !empty($upsun_variables['CREDS_OAUTH_CLIENT_SECRET']) ? $upsun_variables['CREDS_OAUTH_CLIENT_SECRET'] : '<environment-variable>';

// Authentication for Robinhood
$settings['ut_robinhood_username']   = !empty($upsun_variables['CREDS_ROBINHOOD_USERNAME']) ? $upsun_variables['CREDS_ROBINHOOD_USERNAME'] : '<environment-variable>';
$settings['ut_robinhood_password']   = !empty($upsun_variables['CREDS_ROBINHOOD_PASSWORD']) ? $upsun_variables['CREDS_ROBINHOOD_PASSWORD'] : '<environment-variable>';
$settings['ut_robinhood_account_ids']   = !empty($upsun_variables['CREDS_ROBINHOOD_ACCOUNT_IDS']) ? $upsun_variables['CREDS_ROBINHOOD_ACCOUNT_IDS'] : '<environment-variable>';
$settings['ut_robinhood_python_bin'] = '/usr/bin/python3';
$settings['ut_robinhood_pickle_dir'] = '/app/private/ut_robinhood';
