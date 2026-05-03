<?php

declare(strict_types=1);

namespace Drupal\ut_robinhood\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a confirmation form for deleting a Robinhood Order entity.
 */
class RobinhoodOrderDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    /** @var \Drupal\ut_robinhood\Entity\RobinhoodOrder $entity */
    $entity = $this->getEntity();
    return $this->t(
      'Are you sure you want to delete the Robinhood order for %symbol (ID: %rh_id)?',
      [
        '%symbol' => $entity->get('symbol')->value,
        '%rh_id'  => $entity->get('robinhood_order_id')->value,
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return $this->getEntity()->toUrl('collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $form_state->setRedirectUrl(Url::fromRoute('entity.robinhood_order.collection'));
  }

}
