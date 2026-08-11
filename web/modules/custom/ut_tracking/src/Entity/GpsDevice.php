<?php

declare(strict_types=1);

namespace Drupal\ut_tracking\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the GPS Device entity type.
 *
 * One record per (user, device identifier) pair that has successfully
 * authenticated and reported a location via ut_tracking.report_location.
 * LocationReportController auto-creates one the first time a new
 * device_id shows up for a user — there's no separate registration step
 * or admin approval gate, matching how "registered" is used elsewhere in
 * this module: a device is registered by virtue of having reported.
 *
 * @ContentEntityType(
 *   id = "gps_device",
 *   label = @Translation("GPS Device"),
 *   label_collection = @Translation("GPS Devices"),
 *   label_singular = @Translation("GPS device"),
 *   label_plural = @Translation("GPS devices"),
 *   label_count = @PluralTranslation(
 *     singular = "@count GPS device",
 *     plural = "@count GPS devices",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\ut_tracking\GpsDeviceListBuilder",
 *     "access" = "Drupal\ut_tracking\GpsDeviceAccessControlHandler",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "gps_device",
 *   admin_permission = "administer ut_tracking",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/gps-device/{gps_device}",
 *     "delete-form" = "/gps-device/{gps_device}/delete",
 *     "collection" = "/admin/content/gps-device",
 *   },
 * )
 */
class GpsDevice extends ContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if ($this->isNew() && $this->get('created')->isEmpty()) {
      $this->set('created', \Drupal::time()->getRequestTime());
    }
  }

  /**
   * {@inheritdoc}
   *
   * Falls back to the device identifier when no human-friendly name was
   * ever reported (e.g. an app version that only sends device_id).
   */
  public function label() {
    $name = $this->get('device_name')->value;
    return $name ?: $this->get('device_identifier')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Registered to'))
      ->setDescription(new TranslatableMarkup('The user this device is registered under.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setDisplayConfigurable('view', TRUE);

    $fields['device_identifier'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Device identifier'))
      ->setDescription(new TranslatableMarkup('Stable ID the app generates and persists locally on first launch — not a hardware serial.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['device_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Device name'))
      ->setDescription(new TranslatableMarkup('Human-readable device model, as reported by the app (e.g. "Pixel 8 Pro").'))
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['last_seen'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Last seen'))
      ->setDescription(new TranslatableMarkup('When this device last successfully reported a location.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('First seen'))
      ->setDescription(new TranslatableMarkup('When this device first successfully reported a location.'))
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
