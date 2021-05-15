<?php

namespace Drupal\ut_robinhood\Entity;

use Drupal\Core\Entity\Annotation\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the Price entity.
 *
 * @ingroup price
 *
 * @ContentEntityType(
 *   id = "price",
 *   label = @Translation("Price"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "price",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "sid" = "sid",
 *     "price" = "price"
 *   },
 * )
 */
class Price extends ContentEntityBase implements ContentEntityInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Price entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Price entity.'))
      ->setReadOnly(TRUE);

    // Field to point to the taxonomy term of the stock/coin/etc. symbol.
    $fields['sid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Symbol ID'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings',
        array(
          'target_bundles' => array(
            'symbols' => 'symbols'
          )))
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
        'settings' => array(
          'match_operator' => 'CONTAINS',
          'size' => '10',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ),
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setTranslatable($entity_type->isTranslatable())
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setDescription(t('The unique taxonomy term ID for the Symbol of the Price entity.'));

    // Field to store the price at the time requested
    $fields['price'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Price'))
      ->setDescription(t('The price at the time mark.'));

    return $fields;
  }

}
