<?php

namespace Drupal\ut_robinhood\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the StockPrice entity.
 *
 * @ingroup stock_price
 *
 * @ContentEntityType(
 *   id = "stock_price",
 *   label = @Translation("Stock Price"),
 *   base_table = "stock_price",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "sid" = "sid"
 *   },
 * )
 */
class StockPrice extends ContentEntityBase implements ContentEntityInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the StockPrice entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the StockPrice entity.'))
      ->setReadOnly(TRUE);

    // Field to point to the taxonomy term of the stock symbol.
      $fields['sid'] = BaseFieldDefinition::create('decimal')
        ->setLabel(t('Symbol ID'))
        ->setDescription(t('The Symbol ID of the StockPrice entity.'))
        ->setReadOnly(TRUE);

    return $fields;
  }

}
