<?php

declare(strict_types=1);

namespace Drupal\race_day\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Race add/edit forms.
 */
class RaceForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $entity = $this->getEntity();

    $message = $result === SAVED_NEW
      ? $this->t('Race %label has been created.', ['%label' => $entity->label()])
      : $this->t('Race %label has been updated.', ['%label' => $entity->label()]);
    $this->messenger()->addStatus($message);

    $form_state->setRedirectUrl($entity->toUrl('canonical'));
    return $result;
  }

}

