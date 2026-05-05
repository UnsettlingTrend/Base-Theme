<?php

declare(strict_types=1);

namespace Drupal\ut_robinhood;

use Drupal\views\EntityViewsData;

/**
 * Provides Views integration for the Robinhood Order entity type.
 *
 * Extending EntityViewsData gives us automatic exposure of every base field
 * to Views (filters, fields, sort criteria, arguments) with sensible defaults.
 * We then override specific fields below to provide better labels, descriptions,
 * and more appropriate handler classes where the defaults fall short.
 *
 * After enabling this, clear caches and the "Robinhood Orders" base table will
 * appear in Views UI under Add view → Show: Robinhood Orders.
 */
class RobinhoodOrderViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   *
   * Customises the auto-generated Views data for every base field on the
   * Robinhood Order entity. Sets human-readable titles/help text and assigns
   * appropriate Views handler plugins (field, filter, sort, argument) for each
   * column. Also registers virtual fields for bulk operations and entity links.
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();
    // Namespace prefix used to reference standalone options callback functions
    // at the bottom of this file (required by Views' in_operator filter).
    $cb_prefix = __NAMESPACE__ . '\\\\';

    // ------------------------------------------------------------------ //
    // Table-level metadata                                                 //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['table']['group'] = $this->t('Robinhood Order');
    $data['robinhood_order']['table']['base'] = [
      'field' => 'id',
      'title' => $this->t('Robinhood Orders'),
      'help'  => $this->t('Stock orders imported from Robinhood via the UT Robinhood module.'),
      'weight' => -10,
    ];

    // ------------------------------------------------------------------ //
    // id — entity primary key                                             //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['id']['title']  = $this->t('Order ID (Drupal)');
    $data['robinhood_order']['id']['help']   = $this->t('The internal Drupal entity ID for this order.');
    $data['robinhood_order']['id']['field']['id'] = 'numeric';
    $data['robinhood_order']['id']['filter']['id'] = 'numeric';
    $data['robinhood_order']['id']['sort']['id']   = 'standard';
    $data['robinhood_order']['id']['argument']['id'] = 'numeric';

    // ------------------------------------------------------------------ //
    // robinhood_order_id                                                   //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['robinhood_order_id']['title'] = $this->t('Robinhood Order UUID');
    $data['robinhood_order']['robinhood_order_id']['help']  = $this->t("Robinhood's own UUID for this order — the deduplication key used during import.");
    $data['robinhood_order']['robinhood_order_id']['field']['id']    = 'standard';
    $data['robinhood_order']['robinhood_order_id']['filter']['id']   = 'string';
    $data['robinhood_order']['robinhood_order_id']['sort']['id']     = 'standard';
    $data['robinhood_order']['robinhood_order_id']['argument']['id'] = 'string';

    // ------------------------------------------------------------------ //
    // symbol                                                               //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['symbol']['title'] = $this->t('Ticker Symbol');
    $data['robinhood_order']['symbol']['help']  = $this->t('The stock ticker symbol, e.g. AAPL, TSLA.');
    $data['robinhood_order']['symbol']['field']['id']    = 'standard';
    $data['robinhood_order']['symbol']['filter']['id']   = 'string';
    $data['robinhood_order']['symbol']['sort']['id']     = 'standard';
    $data['robinhood_order']['symbol']['argument']['id'] = 'string';

    // ------------------------------------------------------------------ //
    // order_side — buy/sell                                                //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['order_side']['title'] = $this->t('Side (Buy/Sell)');
    $data['robinhood_order']['order_side']['help']  = $this->t('Whether this was a buy or sell order.');
    $data['robinhood_order']['order_side']['field']['id']  = 'standard';
    $data['robinhood_order']['order_side']['filter']['id'] = 'in_operator';
    $data['robinhood_order']['order_side']['filter']['options callback'] = $cb_prefix . '_ut_robinhood_views_order_side_options';
    $data['robinhood_order']['order_side']['sort']['id']   = 'standard';

    // ------------------------------------------------------------------ //
    // order_type                                                           //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['order_type']['title'] = $this->t('Order Type');
    $data['robinhood_order']['order_type']['help']  = $this->t('Market, limit, stop_loss, or stop_limit.');
    $data['robinhood_order']['order_type']['field']['id']  = 'standard';
    $data['robinhood_order']['order_type']['filter']['id'] = 'in_operator';
    $data['robinhood_order']['order_type']['filter']['options callback'] = $cb_prefix . '_ut_robinhood_views_order_type_options';
    $data['robinhood_order']['order_type']['sort']['id']   = 'standard';

    // ------------------------------------------------------------------ //
    // order_state                                                          //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['order_state']['title'] = $this->t('Order State');
    $data['robinhood_order']['order_state']['help']  = $this->t('Current state: filled, cancelled, rejected, etc.');
    $data['robinhood_order']['order_state']['field']['id']  = 'standard';
    $data['robinhood_order']['order_state']['filter']['id'] = 'in_operator';
    $data['robinhood_order']['order_state']['filter']['options callback'] = $cb_prefix . '_ut_robinhood_views_order_state_options';
    $data['robinhood_order']['order_state']['sort']['id']   = 'standard';

    // ------------------------------------------------------------------ //
    // Decimal / numeric price fields                                       //
    // ------------------------------------------------------------------ //

    foreach ([
      'quantity'             => [$this->t('Quantity'), $this->t('Number of shares ordered (supports fractional shares).')],
      'price'                => [$this->t('Limit / Stop Price'), $this->t('The limit or stop price specified on the order.')],
      'average_price'        => [$this->t('Average Fill Price'), $this->t('Average price at which the shares were actually filled.')],
      'total_notional_value' => [$this->t('Total Value'), $this->t('Total notional value of the order (quantity × average fill price).')],
      'fees'                 => [$this->t('Fees'), $this->t('Fees charged on this order.')],
    ] as $field => [$title, $help]) {
      $data['robinhood_order'][$field]['title'] = $title;
      $data['robinhood_order'][$field]['help']  = $help;
      $data['robinhood_order'][$field]['field']['id']    = 'numeric';
      $data['robinhood_order'][$field]['filter']['id']   = 'numeric';
      $data['robinhood_order'][$field]['sort']['id']     = 'standard';
      $data['robinhood_order'][$field]['argument']['id'] = 'numeric';
    }

    // ------------------------------------------------------------------ //
    // time_in_force                                                        //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['time_in_force']['title'] = $this->t('Time in Force');
    $data['robinhood_order']['time_in_force']['help']  = $this->t('GFD (Good for Day), GTC (Good Till Cancelled), etc.');
    $data['robinhood_order']['time_in_force']['field']['id']  = 'standard';
    $data['robinhood_order']['time_in_force']['filter']['id'] = 'in_operator';
    $data['robinhood_order']['time_in_force']['filter']['options callback'] = $cb_prefix . '_ut_robinhood_views_time_in_force_options';
    $data['robinhood_order']['time_in_force']['sort']['id']   = 'standard';

    // ------------------------------------------------------------------ //
    // Datetime fields                                                      //
    // ------------------------------------------------------------------ //
    // The datetime module only auto-configures Views handlers for
    // configurable (Field UI) fields, not base fields. We must explicitly
    // set the correct plugin IDs. The 'field' display handler (EntityField)
    // handles rendering; filter/sort/argument use datetime-specific plugins.

    foreach ([
      'created_at_robinhood' => [$this->t('Order Date (Robinhood)'), $this->t('When the order was placed in Robinhood.')],
      'updated_at_robinhood' => [$this->t('Last Updated (Robinhood)'), $this->t('When the order was last updated in Robinhood.')],
    ] as $field => [$title, $help]) {
      $data['robinhood_order'][$field]['title'] = $title;
      $data['robinhood_order'][$field]['help']  = $help;
      $data['robinhood_order'][$field]['field']['id']    = 'field';
      $data['robinhood_order'][$field]['filter']['id']   = 'datetime';
      $data['robinhood_order'][$field]['sort']['id']     = 'datetime';
      $data['robinhood_order'][$field]['argument']['id'] = 'datetime';
    }

    // ------------------------------------------------------------------ //
    // Drupal bookkeeping timestamps                                        //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['created']['title'] = $this->t('Imported (Drupal)');
    $data['robinhood_order']['created']['help']  = $this->t('When this record was first created in Drupal.');
    $data['robinhood_order']['created']['field']['id']    = 'date';
    $data['robinhood_order']['created']['filter']['id']   = 'date';
    $data['robinhood_order']['created']['sort']['id']     = 'date';
    $data['robinhood_order']['created']['argument']['id'] = 'date';

    $data['robinhood_order']['changed']['title'] = $this->t('Last Synced (Drupal)');
    $data['robinhood_order']['changed']['help']  = $this->t('When this Drupal record was last modified.');
    $data['robinhood_order']['changed']['field']['id']    = 'date';
    $data['robinhood_order']['changed']['filter']['id']   = 'date';
    $data['robinhood_order']['changed']['sort']['id']     = 'date';
    $data['robinhood_order']['changed']['argument']['id'] = 'date';

    // ------------------------------------------------------------------ //
    // extended_hours — boolean                                             //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['extended_hours']['title'] = $this->t('Extended Hours');
    $data['robinhood_order']['extended_hours']['help']  = $this->t('Whether this order is eligible to execute during extended hours.');
    $data['robinhood_order']['extended_hours']['field']['id']  = 'boolean';
    $data['robinhood_order']['extended_hours']['filter']['id'] = 'boolean';
    $data['robinhood_order']['extended_hours']['filter']['label'] = $this->t('Extended Hours');
    $data['robinhood_order']['extended_hours']['filter']['type']  = 'yes-no';
    $data['robinhood_order']['extended_hours']['sort']['id']      = 'standard';

    // ------------------------------------------------------------------ //
    // trigger                                                              //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['trigger']['title'] = $this->t('Trigger');
    $data['robinhood_order']['trigger']['help']  = $this->t('Immediate or stop trigger.');
    $data['robinhood_order']['trigger']['field']['id']  = 'standard';
    $data['robinhood_order']['trigger']['filter']['id'] = 'in_operator';
    $data['robinhood_order']['trigger']['filter']['options callback'] = $cb_prefix . '_ut_robinhood_views_trigger_options';
    $data['robinhood_order']['trigger']['sort']['id']   = 'standard';

    // ------------------------------------------------------------------ //
    // instrument_id                                                        //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['instrument_id']['title'] = $this->t('Instrument ID');
    $data['robinhood_order']['instrument_id']['help']  = $this->t("Robinhood's internal instrument UUID for this stock.");
    $data['robinhood_order']['instrument_id']['field']['id']    = 'standard';
    $data['robinhood_order']['instrument_id']['filter']['id']   = 'string';
    $data['robinhood_order']['instrument_id']['sort']['id']     = 'standard';
    $data['robinhood_order']['instrument_id']['argument']['id'] = 'string';

    // ------------------------------------------------------------------ //
    // account_id                                                           //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['account_id']['title'] = $this->t('Account ID');
    $data['robinhood_order']['account_id']['help']  = $this->t("Robinhood's account identifier for this order.");
    $data['robinhood_order']['account_id']['field']['id']    = 'standard';
    $data['robinhood_order']['account_id']['filter']['id']   = 'string';
    $data['robinhood_order']['account_id']['sort']['id']     = 'standard';
    $data['robinhood_order']['account_id']['argument']['id'] = 'string';

    // ------------------------------------------------------------------ //
    // account_name                                                         //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['account_name']['title'] = $this->t('Account Name');
    $data['robinhood_order']['account_name']['help']  = $this->t('Human-readable Robinhood account name (type + ID).');
    $data['robinhood_order']['account_name']['field']['id']    = 'standard';
    $data['robinhood_order']['account_name']['filter']['id']   = 'in_operator';
    $data['robinhood_order']['account_name']['filter']['options callback'] = $cb_prefix . '_ut_robinhood_views_account_name_options';
    $data['robinhood_order']['account_name']['sort']['id']     = 'standard';
    $data['robinhood_order']['account_name']['argument']['id'] = 'string';

    // ------------------------------------------------------------------ //
    // uid — relationship to users table                                    //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['uid']['title']  = $this->t('Author');
    $data['robinhood_order']['uid']['help']   = $this->t('The Drupal user who owns this record.');
    $data['robinhood_order']['uid']['relationship'] = [
      'title'      => $this->t('Author'),
      'help'       => $this->t('Relate each Robinhood order to its Drupal owner user.'),
      'base'       => 'users_field_data',
      'base field' => 'uid',
      'id'         => 'standard',
      'label'      => $this->t('Author'),
    ];
    $data['robinhood_order']['uid']['filter']['id']   = 'numeric';
    $data['robinhood_order']['uid']['sort']['id']     = 'standard';
    $data['robinhood_order']['uid']['argument']['id'] = 'numeric';

    // ------------------------------------------------------------------ //
    // Bulk operations field                                                //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['robinhood_order_bulk_form'] = [
      'title' => $this->t('Robinhood Order operations bulk form'),
      'help'  => $this->t('Add a form element that lets you run operations on multiple Robinhood orders.'),
      'field' => [
        'id' => 'bulk_form',
      ],
    ];

    // ------------------------------------------------------------------ //
    // Link fields                                                          //
    // ------------------------------------------------------------------ //

    $data['robinhood_order']['view_robinhood_order'] = [
      'title' => $this->t('Link to order'),
      'help'  => $this->t('A link to the Robinhood order canonical page.'),
      'field' => [
        'id' => 'entity_link',
      ],
    ];

    $data['robinhood_order']['edit_robinhood_order'] = [
      'title' => $this->t('Link to edit order'),
      'help'  => $this->t('A link to the Robinhood order edit form.'),
      'field' => [
        'id'          => 'entity_link',
        'route_name'  => 'entity.robinhood_order.edit_form',
        'link_to_entity' => TRUE,
      ],
    ];

    $data['robinhood_order']['delete_robinhood_order'] = [
      'title' => $this->t('Link to delete order'),
      'help'  => $this->t('A link to the Robinhood order delete confirmation form.'),
      'field' => [
        'id'         => 'entity_link',
        'route_name' => 'entity.robinhood_order.delete_form',
        'link_to_entity' => TRUE,
      ],
    ];

    return $data;
  }

}


// ------------------------------------------------------------------ //
// Options callbacks for in_operator filters                           //
// These must be plain functions (not methods) as Views calls them     //
// via call_user_func() without an object context.                     //
// ------------------------------------------------------------------ //

/**
 * Returns allowed values for the order_side Views filter.
 */
function _ut_robinhood_views_order_side_options(): array {
  return ['buy' => t('Buy'), 'sell' => t('Sell')];
}

/**
 * Returns allowed values for the order_type Views filter.
 */
function _ut_robinhood_views_order_type_options(): array {
  return [
    'market'     => t('Market'),
    'limit'      => t('Limit'),
    'stop_loss'  => t('Stop Loss'),
    'stop_limit' => t('Stop Limit'),
  ];
}

/**
 * Returns allowed values for the order_state Views filter.
 */
function _ut_robinhood_views_order_state_options(): array {
  return [
    'queued'           => t('Queued'),
    'unconfirmed'      => t('Unconfirmed'),
    'confirmed'        => t('Confirmed'),
    'partially_filled' => t('Partially Filled'),
    'filled'           => t('Filled'),
    'rejected'         => t('Rejected'),
    'cancelled'        => t('Cancelled'),
    'failed'           => t('Failed'),
    'voided'           => t('Voided'),
    'pending_cancel'   => t('Pending Cancel'),
    'pending_review'   => t('Pending Review'),
  ];
}

/**
 * Returns allowed values for the time_in_force Views filter.
 */
function _ut_robinhood_views_time_in_force_options(): array {
  return [
    'gfd' => t('Good for Day (GFD)'),
    'gtc' => t('Good Till Cancelled (GTC)'),
    'ioc' => t('Immediate or Cancel (IOC)'),
    'opg' => t('At the Open (OPG)'),
  ];
}

/**
 * Returns allowed values for the trigger Views filter.
 */
function _ut_robinhood_views_trigger_options(): array {
  return [
    'immediate' => t('Immediate'),
    'stop'      => t('Stop'),
  ];
}

/**
 * Returns available account names for the Views filter.
 *
 * Queries the robinhood_order table for distinct account_name values so the
 * dropdown always reflects the accounts that actually have imported orders.
 */
function _ut_robinhood_views_account_name_options(): array {
  $names = \Drupal::database()
    ->select('robinhood_order', 'ro')
    ->fields('ro', ['account_name'])
    ->isNotNull('account_name')
    ->condition('account_name', '', '<>')
    ->distinct()
    ->orderBy('account_name')
    ->execute()
    ->fetchCol();

  $options = [];
  foreach ($names as $name) {
    $options[$name] = $name;
  }
  return $options;
}

