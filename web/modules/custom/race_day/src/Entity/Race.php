<?php

declare(strict_types=1);

namespace Drupal\race_day\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Race entity type.
 *
 * Represents a relay race event with a name, date, status, and optional
 * description and map data. Race legs and teams reference this entity.
 *
 * @ContentEntityType(
 *   id = "race",
 *   label = @Translation("Race"),
 *   label_collection = @Translation("Races"),
 *   label_singular = @Translation("race"),
 *   label_plural = @Translation("races"),
 *   label_count = @PluralTranslation(
 *     singular = "@count race",
 *     plural = "@count races",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\race_day\RaceViewsData",
 *     "list_builder" = "Drupal\race_day\RaceListBuilder",
 *     "access" = "Drupal\race_day\RaceAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\race_day\Form\RaceForm",
 *       "edit" = "Drupal\race_day\Form\RaceForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "race",
 *   admin_permission = "administer race_day",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/race/{race}",
 *     "add-form" = "/admin/race-day/races/add",
 *     "edit-form" = "/admin/race-day/races/{race}/edit",
 *     "delete-form" = "/admin/race-day/races/{race}/delete",
 *     "collection" = "/admin/race-day/races",
 *   },
 *   field_ui_base_route = "race_day.race_settings",
 * )
 */
class Race extends ContentEntityBase implements EntityOwnerInterface, EntityChangedInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setDescription(new TranslatableMarkup('The name of the relay race.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -30,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -30,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Description'))
      ->setDescription(new TranslatableMarkup('A description of the race, rules, and logistics.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => -25,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => -25,
        'settings' => ['rows' => 5],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['race_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Date'))
      ->setDescription(new TranslatableMarkup('The date and start time of the race.'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'datetime')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'weight' => -20,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => -20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setDescription(new TranslatableMarkup('Current status of the race.'))
      ->setRequired(TRUE)
      ->setDefaultValue('draft')
      ->setSetting('allowed_values', [
        'draft' => 'Draft',
        'registration_open' => 'Registration Open',
        'registration_closed' => 'Registration Closed',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => -18,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -18,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['total_distance'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Total Distance'))
      ->setDescription(new TranslatableMarkup('Total race distance in miles.'))
      ->setSetting('precision', 10)
      ->setSetting('scale', 2)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => -15,
        'settings' => ['suffix' => ' mi', 'scale' => 2],
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['max_teams'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Maximum Teams'))
      ->setDescription(new TranslatableMarkup('Maximum number of teams allowed. Leave empty for unlimited.'))
      ->setSetting('unsigned', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['max_runners_per_team'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Maximum Runners Per Team'))
      ->setDescription(new TranslatableMarkup('Maximum number of runners allowed on each team for this race. Leave empty for unlimited.'))
      ->setSetting('unsigned', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => -9,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -9,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // ------------------------------------------------------------------ //
    // Drupal bookkeeping                                                   //
    // ------------------------------------------------------------------ //

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Author'))
      ->setDescription(new TranslatableMarkup('The user who created this race.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDescription(new TranslatableMarkup('When this race was created.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDescription(new TranslatableMarkup('When this race was last updated.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['path'] = BaseFieldDefinition::create('path')
      ->setLabel(new TranslatableMarkup('URL alias'))
      ->setDisplayOptions('form', ['type' => 'path', 'weight' => 30])
      ->setDisplayConfigurable('form', TRUE)
      ->setComputed(TRUE);

    return $fields;
  }

}

