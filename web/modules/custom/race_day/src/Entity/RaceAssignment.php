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
 * Assigns a runner (Drupal user) to a specific leg.
 *
 * Also tracks the runner's live GPS location and running status during
 * race day.
 *
 * @ContentEntityType(
 *   id = "race_assignment",
 *   label = @Translation("Runner Assignment"),
 *   label_collection = @Translation("Runner Assignments"),
 *   label_singular = @Translation("runner assignment"),
 *   label_plural = @Translation("runner assignments"),
 *   label_count = @PluralTranslation(
 *     singular = "@count runner assignment",
 *     plural = "@count runner assignments",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "list_builder" = "Drupal\race_day\RaceAssignmentListBuilder",
 *     "access" = "Drupal\race_day\RaceAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\race_day\Form\RaceAssignmentForm",
 *       "edit" = "Drupal\race_day\Form\RaceAssignmentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 *   base_table = "race_assignment",
 *   admin_permission = "administer race_day",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "add-form" = "/admin/race-day/assignments/add",
 *     "edit-form" = "/admin/race-day/assignments/{race_assignment}/edit",
 *     "delete-form" = "/admin/race-day/assignments/{race_assignment}/delete",
 *     "collection" = "/admin/race-day/assignments",
 *   },
 *   field_ui_base_route = "race_day.race_assignment_settings",
 * )
 */
class RaceAssignment extends ContentEntityBase implements EntityChangedInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);


    $fields['leg_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Race Leg'))
      ->setDescription(new TranslatableMarkup('The leg this runner is assigned to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'race_leg')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -25,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -25,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['runner'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Runner'))
      ->setDescription(new TranslatableMarkup('The Drupal user running this leg.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -20,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['runner_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Runner Status'))
      ->setDescription(new TranslatableMarkup('Current status of this runner on race day.'))
      ->setRequired(TRUE)
      ->setDefaultValue('waiting')
      ->setSetting('allowed_values', [
        'waiting' => 'Waiting',
        'ready' => 'Ready at Handoff',
        'running' => 'Running',
        'completed' => 'Completed',
        'dnf' => 'Did Not Finish',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => -15,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // ------------------------------------------------------------------ //
    // Live GPS location fields                                             //
    // ------------------------------------------------------------------ //

    $fields['current_lat'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Current Latitude'))
      ->setDescription(new TranslatableMarkup("Runner's last known latitude."))
      ->setSetting('precision', 10)
      ->setSetting('scale', 7)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['current_lng'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Current Longitude'))
      ->setDescription(new TranslatableMarkup("Runner's last known longitude."))
      ->setSetting('precision', 10)
      ->setSetting('scale', 7)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => -9,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['location_updated'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Location Updated'))
      ->setDescription(new TranslatableMarkup('Unix timestamp of the last GPS update.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => -8,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['speed'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Speed'))
      ->setDescription(new TranslatableMarkup("Runner's speed in mph at last GPS update."))
      ->setSetting('precision', 6)
      ->setSetting('scale', 2)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => -7,
        'settings' => ['suffix' => ' mph'],
      ])
      ->setDisplayConfigurable('view', TRUE);

    // ------------------------------------------------------------------ //
    // Timing fields                                                        //
    // ------------------------------------------------------------------ //

    $fields['started_at'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Started At'))
      ->setDescription(new TranslatableMarkup('When this runner started their leg.'))
      ->setSetting('datetime_type', 'datetime')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['finished_at'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Finished At'))
      ->setDescription(new TranslatableMarkup('When this runner completed their leg.'))
      ->setSetting('datetime_type', 'datetime')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => -4,
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

