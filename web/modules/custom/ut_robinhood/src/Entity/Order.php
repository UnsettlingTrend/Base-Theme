<?php

namespace Drupal\ut_robinhood\Entity;

use Drupal\Core\Entity\Annotation\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the Order entity.
 *
 * @ingroup order
 *
 * @ContentEntityType(
 *   id = "order",
 *   label = @Translation("Order"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "order",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "sid" = "sid",
 *     "price_ordered" = "price_ordered",
 *     "price_fulfilled" = "price_fulfilled"
 *   },
 * )
 */
class Order extends ContentEntityBase implements ContentEntityInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Order entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Order entity.'))
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
      ->setDescription(t('The unique taxonomy term ID for the Symbol of the Order entity.'));

    // Field to store the price the order was set at.
    $fields['price_ordered'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Order Price'))
      ->setDescription(t('The Price being asked for.'));

    // Field to store the price the order was fulfilled at.
    $fields['price_fulfilled'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Fulfilled Price'))
      ->setDescription(t('The actual Price at time of purchase/sale.'));

    return $fields;
  }

}
