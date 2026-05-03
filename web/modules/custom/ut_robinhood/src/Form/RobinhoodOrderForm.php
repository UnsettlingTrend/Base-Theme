<?php

declare(strict_types=1);

namespace Drupal\ut_robinhood\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for Robinhood Order add/edit forms.
 *
 * Manual add/edit is provided for admin corrections. The primary data entry
 * path is automated via cron and the RobinhoodOrderImporter service.
 */
class RobinhoodOrderForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $entity = $this->getEntity();

    $label = $entity->get('symbol')->value . ' #' . $entity->id();

    if ($result === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Created Robinhood order %label.', ['%label' => $label]));
    }
    else {
      $this->messenger()->addStatus($this->t('Updated Robinhood order %label.', ['%label' => $label]));
    }

    $form_state->setRedirectUrl($entity->toUrl('canonical'));
    return $result;
  }

}
