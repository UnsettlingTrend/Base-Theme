<?php

declare(strict_types=1);

namespace Drupal\race_day\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Runner Assignment add/edit forms.
 */
class RaceAssignmentForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $entity = $this->getEntity();

    $runner = $entity->get('runner')->entity;
    $leg = $entity->get('leg_id')->entity;

    $message = $result === SAVED_NEW
      ? $this->t('Runner %runner assigned to %leg.', [
        '%runner' => $runner ? $runner->getDisplayName() : 'Unknown',
        '%leg' => $leg ? $leg->label() : 'Unknown',
      ])
      : $this->t('Assignment updated.');
    $this->messenger()->addStatus($message);

    $form_state->setRedirectUrl($entity->toUrl('edit-form'));
    return $result;
  }

}

