<?php

declare(strict_types=1);

namespace Drupal\ut_robinhood\Entity;

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
 * Defines the Robinhood Order entity type.
 *
 * Each entity represents a single stock order imported from Robinhood via the
 * robin_stocks Python library. The robinhood_order_id field stores Robinhood's
 * own UUID for the order and is used as the deduplication key on import.
 *
 * @ContentEntityType(
 *   id = "robinhood_order",
 *   label = @Translation("Robinhood Order"),
 *   label_collection = @Translation("Robinhood Orders"),
 *   label_singular = @Translation("Robinhood order"),
 *   label_plural = @Translation("Robinhood orders"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Robinhood order",
 *     plural = "@count Robinhood orders",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "list_builder" = "Drupal\ut_robinhood\RobinhoodOrderListBuilder",
 *     "access" = "Drupal\ut_robinhood\RobinhoodOrderAccessControlHandler",
 *     "views_data" = "Drupal\ut_robinhood\RobinhoodOrderViewsData",
 *     "form" = {
 *       "add" = "Drupal\ut_robinhood\Form\RobinhoodOrderForm",
 *       "edit" = "Drupal\ut_robinhood\Form\RobinhoodOrderForm",
 *       "delete" = "Drupal\ut_robinhood\Form\RobinhoodOrderDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "robinhood_order",
 *   admin_permission = "administer ut_robinhood",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "symbol",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/robinhood-order/{robinhood_order}",
 *     "add-form" = "/admin/content/robinhood-orders/add",
 *     "edit-form" = "/admin/content/robinhood-orders/{robinhood_order}/edit",
 *     "delete-form" = "/admin/content/robinhood-orders/{robinhood_order}/delete",
 *     "collection" = "/admin/content/robinhood-orders",
 *   },
 *   field_ui_base_route = "ut_robinhood.settings",
 * )
 */
class RobinhoodOrder extends ContentEntityBase implements EntityOwnerInterface, EntityChangedInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   *
   * Ensures every order has an owner UID before saving. If no owner has been
   * set (e.g. during automated cron imports), defaults to the anonymous user.
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Defines all base fields for the Robinhood Order entity. Fields are grouped
   * into logical sections:
   * - Robinhood-native identifiers (robinhood_order_id)
   * - Core order fields (symbol, side, type, state)
   * - Price / quantity fields (quantity, price, average_price, total, fees)
   * - Time-related fields (time_in_force, timestamps)
   * - Extended / instrument fields (instrument_id, account_id, trigger, etc.)
   * - Drupal bookkeeping fields (uid, created, changed)
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    // Inherit id, uuid, and langcode from ContentEntityBase.
    $fields = parent::baseFieldDefinitions($entity_type);
    // Add the uid (owner) base field from EntityOwnerTrait.
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    // ------------------------------------------------------------------ //
    // Robinhood-native identifier fields                                   //
    // ------------------------------------------------------------------ //

    $fields['robinhood_order_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Robinhood Order ID'))
      ->setDescription(new TranslatableMarkup("Robinhood's own UUID for this order. Used as the deduplication key during import."))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -30,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -30,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // ------------------------------------------------------------------ //
    // Core order fields mirroring the Robinhood stock order object         //
    // ------------------------------------------------------------------ //

    $fields['symbol'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Ticker Symbol'))
      ->setDescription(new TranslatableMarkup('The stock ticker, e.g. AAPL.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 16)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -25,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -25,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['order_side'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Side'))
      ->setDescription(new TranslatableMarkup('Whether this was a buy or sell order.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'buy' => 'Buy',
        'sell' => 'Sell',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => -20,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['order_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Order Type'))
      ->setDescription(new TranslatableMarkup('Market, limit, stop_loss, or stop_limit.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'market' => 'Market',
        'limit' => 'Limit',
        'stop_loss' => 'Stop Loss',
        'stop_limit' => 'Stop Limit',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => -19,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -19,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['order_state'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('State'))
      ->setDescription(new TranslatableMarkup('Current state of the order (filled, cancelled, etc.).'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'queued' => 'Queued',
        'unconfirmed' => 'Unconfirmed',
        'confirmed' => 'Confirmed',
        'partially_filled' => 'Partially Filled',
        'filled' => 'Filled',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'failed' => 'Failed',
        'voided' => 'Voided',
        'pending_cancel' => 'Pending Cancel',
        'pending_review' => 'Pending Review',
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

    // ------------------------------------------------------------------ //
    // Price / quantity fields                                              //
    // ------------------------------------------------------------------ //

    $fields['quantity'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Quantity'))
      ->setDescription(new TranslatableMarkup('Number of shares ordered (supports fractional shares).'))
      ->setRequired(TRUE)
      ->setSetting('precision', 18)
      ->setSetting('scale', 8)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => -15,
        'settings' => ['scale' => 4],
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['price'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Limit / Stop Price'))
      ->setDescription(new TranslatableMarkup('The limit or stop price specified on the order. NULL for pure market orders.'))
      ->setSetting('precision', 18)
      ->setSetting('scale', 4)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => -14,
        'settings' => ['prefix' => '$', 'scale' => 4],
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -14,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['average_price'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Average Fill Price'))
      ->setDescription(new TranslatableMarkup('Average price at which shares were actually filled.'))
      ->setSetting('precision', 18)
      ->setSetting('scale', 4)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => -13,
        'settings' => ['prefix' => '$', 'scale' => 4],
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -13,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['total_notional_value'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Total Value'))
      ->setDescription(new TranslatableMarkup('Total notional value of the order (quantity × average fill price).'))
      ->setSetting('precision', 18)
      ->setSetting('scale', 4)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => -12,
        'settings' => ['prefix' => '$', 'scale' => 2],
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -12,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['fees'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Fees'))
      ->setDescription(new TranslatableMarkup('Fees charged on this order.'))
      ->setSetting('precision', 18)
      ->setSetting('scale', 4)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_decimal',
        'weight' => -11,
        'settings' => ['prefix' => '$', 'scale' => 4],
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -11,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // ------------------------------------------------------------------ //
    // Time-related fields                                                  //
    // ------------------------------------------------------------------ //

    $fields['time_in_force'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Time in Force'))
      ->setDescription(new TranslatableMarkup('GFD (Good for Day) or GTC (Good Till Cancelled).'))
      ->setSetting('allowed_values', [
        'gfd' => 'Good for Day (GFD)',
        'gtc' => 'Good Till Cancelled (GTC)',
        'ioc' => 'Immediate or Cancel (IOC)',
        'opg' => 'At the Open (OPG)',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created_at_robinhood'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Order Created (Robinhood)'))
      ->setDescription(new TranslatableMarkup("The timestamp when the order was placed in Robinhood, as returned by the API."))
      ->setSetting('datetime_type', 'datetime')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'weight' => -8,
        'settings' => ['format_type' => 'medium'],
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => -8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['updated_at_robinhood'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Order Last Updated (Robinhood)'))
      ->setDescription(new TranslatableMarkup("The timestamp when the order was last updated in Robinhood, as returned by the API."))
      ->setSetting('datetime_type', 'datetime')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'weight' => -7,
        'settings' => ['format_type' => 'medium'],
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => -7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // ------------------------------------------------------------------ //
    // Extended / instrument fields                                         //
    // ------------------------------------------------------------------ //

    $fields['instrument_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Instrument ID'))
      ->setDescription(new TranslatableMarkup("Robinhood's internal instrument UUID for this stock."))
      ->setSetting('max_length', 64)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['account_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Account ID'))
      ->setDescription(new TranslatableMarkup("Robinhood's account identifier for this order."))
      ->setSetting('max_length', 64)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['account_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Account Name'))
      ->setDescription(new TranslatableMarkup('Human-readable account name, e.g. "Individual (ABC123)" or "Roth Ira (DEF456)".'))
      ->setSetting('max_length', 128)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['trigger'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Trigger'))
      ->setDescription(new TranslatableMarkup('Immediate or stop trigger.'))
      ->setSetting('allowed_values', [
        'immediate' => 'Immediate',
        'stop' => 'Stop',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['extended_hours'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Extended Hours'))
      ->setDescription(new TranslatableMarkup('Whether this order is eligible to execute during extended hours.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // ------------------------------------------------------------------ //
    // Drupal bookkeeping fields                                            //
    // ------------------------------------------------------------------ //

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Author'))
      ->setDescription(new TranslatableMarkup('The Drupal user who owns this record (typically the cron user or admin).'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Drupal Created'))
      ->setDescription(new TranslatableMarkup('The time this Drupal record was first created.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Drupal Changed'))
      ->setDescription(new TranslatableMarkup('The time this Drupal record was last modified.'))
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
