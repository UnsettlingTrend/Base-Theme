<?php

declare(strict_types=1);

namespace Drupal\race_day\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\race_day\Service\RaceLegCsvService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form to import race legs from a CSV file upload.
 */
class RaceLegImportForm extends FormBase {

  public function __construct(
    protected readonly RaceLegCsvService $csvService,
    protected readonly FileSystemInterface $fileSystem,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('race_day.csv_service'),
      $container->get('file_system'),
    );
  }

  public function getFormId(): string {
    return 'race_leg_import_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['csv_file'] = [
      '#type'              => 'managed_file',
      '#title'             => $this->t('CSV file'),
      '#description'       => $this->t(
        'Upload a CSV with columns: %cols. The <em>race_id</em> must match an existing race.',
        ['%cols' => implode(', ', RaceLegCsvService::HEADERS)]
      ),
      '#upload_location'   => 'temporary://',
      '#upload_validators' => ['FileExtension' => ['extensions' => 'csv']],
      '#required'          => TRUE,
    ];

    $form['delete_missing'] = [
      '#type'        => 'checkbox',
      '#title'       => $this->t('Delete legs not present in the CSV'),
      '#description' => $this->t(
        'For each race_id that appears in the CSV, any existing legs whose leg_number is absent from the file will be permanently deleted.'
      ),
      '#default_value' => FALSE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $fids = $form_state->getValue('csv_file');
    if (empty($fids)) {
      $this->messenger()->addError($this->t('No file uploaded.'));
      return;
    }

    /** @var \Drupal\file\FileInterface $file */
    $file = \Drupal::entityTypeManager()->getStorage('file')->load(reset($fids));
    if (!$file) {
      $this->messenger()->addError($this->t('Could not load the uploaded file.'));
      return;
    }

    $real_path = $this->fileSystem->realpath($file->getFileUri());
    if (!$real_path || !file_exists($real_path)) {
      $this->messenger()->addError($this->t('Could not locate the uploaded file on disk.'));
      return;
    }

    $csv            = file_get_contents($real_path);
    $delete_missing = (bool) $form_state->getValue('delete_missing');

    // Clean up the temporary file immediately — we have the contents.
    $file->delete();

    $stats = $this->csvService->import($csv, $delete_missing);

    $this->messenger()->addStatus($this->t(
      'Import complete: @created created, @updated updated, @skipped skipped, @errors error(s).',
      [
        '@created' => $stats['created'],
        '@updated' => $stats['updated'],
        '@skipped' => $stats['skipped'],
        '@errors'  => $stats['errors'],
      ]
    ));

    foreach ($stats['messages'] as $msg) {
      if ($stats['errors'] > 0) {
        $this->messenger()->addWarning($msg);
      }
      else {
        $this->messenger()->addStatus($msg);
      }
    }
  }

}
