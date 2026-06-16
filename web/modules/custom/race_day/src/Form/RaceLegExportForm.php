<?php

declare(strict_types=1);

namespace Drupal\race_day\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\race_day\Service\RaceLegCsvService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin form to export race legs as a CSV download.
 */
class RaceLegExportForm extends FormBase {

  public function __construct(
    protected RaceLegCsvService $csvService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('race_day.csv_service'));
  }

  public function getFormId(): string {
    return 'race_leg_export_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $race_options = $this->csvService->raceOptions();

    $form['race_id'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Race'),
      '#options'       => ['' => $this->t('— All races —')] + $race_options,
      '#empty_value'   => '',
      '#description'   => $this->t('Optionally restrict the export to a single race.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Download CSV'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $race_id_raw = $form_state->getValue('race_id');
    $race_id     = ($race_id_raw !== '' && $race_id_raw !== NULL) ? (int) $race_id_raw : NULL;

    $csv      = $this->csvService->export($race_id);
    $filename = $race_id ? "race_legs_race_{$race_id}.csv" : 'race_legs.csv';

    $response = new Response($csv, 200, [
      'Content-Type'        => 'text/csv; charset=utf-8',
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      'Cache-Control'       => 'no-cache, no-store, must-revalidate',
    ]);

    $form_state->setResponse($response);
  }

}
