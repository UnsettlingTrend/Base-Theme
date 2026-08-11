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
 * Defines the GPS Location entity type.
 *
 * One record per location report POSTed by the mobile app to
 * ut_tracking.report_location. This is an append-only log — reports are
 * never edited after creation, so there's deliberately no "changed" field
 * and no add/edit form (see ut_tracking.routing.yml and
 * GpsLocationAccessControlHandler).
 *
 * The admin listing at /admin/content/gps-location is a real Views page
 * (views.view.gps_location_admin, installed by
 * ut_tracking_update_10001()) — its page display provides that route
 * (view.gps_location_admin.page_1), not an auto-generated entity
 * collection route, so there's deliberately no "collection" link
 * template below and no entity.gps_location.collection route in
 * ut_tracking.routing.yml (both are what DefaultHtmlRouteProvider needs
 * to generate one; omitting the link template is enough to prevent a
 * second route at the same path).
 *
 * "list_builder" is still declared, pointing at the plain core
 * EntityListBuilder rather than a custom class — nothing renders it as a
 * page, but Drupal\views\EntityViewsData only exposes the "operations"
 * pseudo-field (the View's Operations/delete-link column) when a
 * list_builder handler exists, since entity_operations calls its
 * getOperations() under the hood.
 *
 * @ContentEntityType(
 *   id = "gps_location",
 *   label = @Translation("GPS Location"),
 *   label_collection = @Translation("GPS Locations"),
 *   label_singular = @Translation("GPS location"),
 *   label_plural = @Translation("GPS locations"),
 *   label_count = @PluralTranslation(
 *     singular = "@count GPS location",
 *     plural = "@count GPS locations",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "access" = "Drupal\ut_tracking\GpsLocationAccessControlHandler",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "gps_location",
 *   admin_permission = "administer ut_tracking",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/gps-location/{gps_location}",
 *     "delete-form" = "/gps-location/{gps_location}/delete",
 *   },
 * )
 */
class GpsLocation extends ContentEntityBase implements EntityOwnerInterface {

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
   * No natural label field, so build one from the coordinates and the
   * device-reported time rather than declaring an "id"-only label key.
   */
  public function label() {
    $latitude = $this->get('latitude')->value;
    $longitude = $this->get('longitude')->value;
    $recorded = (int) $this->get('recorded')->value;
    $when = $recorded ? date('Y-m-d H:i:s', $recorded) : (string) new TranslatableMarkup('unknown time');
    return "$latitude, $longitude @ $when";
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Reported by'))
      ->setDescription(new TranslatableMarkup('The user this location report belongs to.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setDisplayConfigurable('view', TRUE);

    $fields['latitude'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Latitude'))
      ->setRequired(TRUE)
      ->setSetting('precision', 10)
      ->setSetting('scale', 7)
      ->setDisplayConfigurable('view', TRUE);

    $fields['longitude'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Longitude'))
      ->setRequired(TRUE)
      ->setSetting('precision', 10)
      ->setSetting('scale', 7)
      ->setDisplayConfigurable('view', TRUE);

    $fields['accuracy'] = BaseFieldDefinition::create('float')
      ->setLabel(new TranslatableMarkup('Accuracy'))
      ->setDescription(new TranslatableMarkup('Horizontal accuracy of the reading, in meters, as reported by the device.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['device'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Device'))
      ->setDescription(new TranslatableMarkup('The GPS Device this report was sent from.'))
      ->setSetting('target_type', 'gps_device')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['recorded'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Recorded'))
      ->setDescription(new TranslatableMarkup('When the device captured this location (client-reported, not when the server received it).'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDescription(new TranslatableMarkup('When the server received and stored this report.'))
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
