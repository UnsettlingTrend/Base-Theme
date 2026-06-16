<?php

declare(strict_types=1);

namespace Drupal\race_day\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a single leg (segment) of a relay race.
 *
 * Each leg belongs to a Race and defines the order, distance, start/end
 * coordinates, and optional route geometry for that segment.
 *
 * @ContentEntityType(
 *   id = "race_leg",
 *   label = @Translation("Race Leg"),
 *   label_collection = @Translation("Race Legs"),
 *   label_singular = @Translation("race leg"),
 *   label_plural = @Translation("race legs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count race leg",
 *     plural = "@count race legs",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\race_day\RaceLegViewsData",
 *     "list_builder" = "Drupal\race_day\RaceLegListBuilder",
 *     "access" = "Drupal\race_day\RaceAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\race_day\Form\RaceLegForm",
 *       "edit" = "Drupal\race_day\Form\RaceLegForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "race_leg",
 *   admin_permission = "administer race_day",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/race-leg/{race_leg}",
 *     "add-form" = "/admin/race-day/legs/add",
 *     "edit-form" = "/admin/race-day/legs/{race_leg}/edit",
 *     "delete-form" = "/admin/race-day/legs/{race_leg}/delete",
 *     "collection" = "/admin/race-day/legs",
 *   },
 *   field_ui_base_route = "race_day.race_leg_settings",
 * )
 */
class RaceLeg extends ContentEntityBase implements EntityChangedInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['race_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Race'))
      ->setDescription(new TranslatableMarkup('The race this leg belongs to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'race')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -30,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -30,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Leg Name'))
      ->setDescription(new TranslatableMarkup('A descriptive name for this leg, e.g. "Leg 1: Downtown Loop".'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -28,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -28,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['leg_number'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Leg Number'))
      ->setDescription(new TranslatableMarkup('The sequential order of this leg in the race (1, 2, 3...).'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setSetting('min', 1)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => -25,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -25,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['distance'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Distance'))
      ->setDescription(new TranslatableMarkup('Distance of this leg in miles.'))
      ->setRequired(TRUE)
      ->setSetting('precision', 10)
      ->setSetting('scale', 2)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => -20,
        'settings' => ['suffix' => ' mi', 'scale' => 2],
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['difficulty'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Difficulty'))
      ->setDescription(new TranslatableMarkup('Difficulty rating for this leg.'))
      ->setSetting('allowed_values', [
        'easy' => 'Easy',
        'moderate' => 'Moderate',
        'hard' => 'Hard',
        'very_hard' => 'Very Hard',
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

    $fields['route'] =BaseFieldDefinition::create('entity_reference_revisions')
      ->setLabel(new TranslatableMarkup('Route'))
      ->setDescription(new TranslatableMarkup('An embedded Strava route map for this leg.'))
      ->setSetting('target_type', 'paragraph')
      ->setSetting('handler', 'default:paragraph')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'strava_route' => 'strava_route',
        ],
        'negate' => 0,
      ])
      ->setCardinality(1)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_revisions_entity_view',
        'weight' => -4,
        'settings' => [
          'view_mode' => 'default',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'paragraphs',
        'weight' => -3,
        'settings' => [
          'title' => 'Route',
          'title_plural' => 'Routes',
          'edit_mode' => 'open',
          'closed_mode' => 'summary',
          'autocollapse' => 'none',
          'closed_mode_threshold' => 0,
          'add_mode' => 'button',
          'form_display_mode' => 'default',
          'default_paragraph_type' => 'strava_route',
          'features' => [
            'add_above' => '0',
            'collapse_edit_all' => 'collapse_edit_all',
            'duplicate' => 'duplicate',
          ],
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Description'))
      ->setDescription(new TranslatableMarkup('Notes about terrain, landmarks, or hazards on this leg.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => -4,
        'settings' => ['rows' => 4],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}

