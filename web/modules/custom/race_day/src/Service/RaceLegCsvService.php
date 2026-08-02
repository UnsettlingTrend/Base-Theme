<?php

declare(strict_types=1);

namespace Drupal\race_day\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Shared CSV export/import logic for race_leg entities.
 *
 * Used by both the Drush commands and the admin UI forms.
 */
class RaceLegCsvService {

  const HEADERS = [
    'race_id',
    'leg_number',
    'label',
    'distance',
    'description',
    'strava_route_id',
  ];

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  // ---------------------------------------------------------------------------
  // Export
  // ---------------------------------------------------------------------------

  /**
   * Builds CSV content for all race legs, optionally filtered by race.
   *
   * @param int|null $race_id
   *   Restrict to a single race, or NULL for all races.
   *
   * @return string
   *   Raw CSV text including the header row.
   */
  public function export(?int $race_id = NULL): string {
    $leg_storage  = $this->entityTypeManager->getStorage('race_leg');
    $para_storage = $this->entityTypeManager->getStorage('paragraph');

    $query = $leg_storage->getQuery()->accessCheck(FALSE);
    if ($race_id !== NULL) {
      $query->condition('race_id', $race_id);
    }
    $query->sort('race_id')->sort('leg_number');
    $ids = $query->execute();

    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, self::HEADERS);

    foreach ($leg_storage->loadMultiple($ids) as $leg) {
      $strava_route_id = '';
      $para = NULL;
      if (!$leg->get('route')->isEmpty()) {
        $para_id = $leg->get('route')->target_id;
        if ($para_id && $para = $para_storage->load($para_id)) {
          $strava_route_id = (string) ($para->get('field_strava_route_id')->value ?? '');
        }
      }

      $description = '';
      if (!$leg->get('description')->isEmpty()) {
        $description = (string) ($leg->get('description')->value ?? '');
      }

      fputcsv($fh, [
        $leg->get('race_id')->target_id,
        $leg->get('leg_number')->value,
        $leg->label(),
        $para?->get('field_distance')->value,
        $description,
        $strava_route_id,
      ]);
    }

    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);

    return $csv;
  }

  // ---------------------------------------------------------------------------
  // Import
  // ---------------------------------------------------------------------------

  /**
   * Imports race legs from CSV text.
   *
   * Rows are matched on race_id + leg_number. Existing legs are updated;
   * missing legs are created. If $delete_missing is TRUE, any leg belonging
   * to a race_id present in the CSV but absent from the CSV rows is deleted.
   *
   * @param string $csv
   *   Raw CSV content (including header row).
   * @param bool $delete_missing
   *   Whether to delete legs not present in the CSV.
   *
   * @return array{created: int, updated: int, skipped: int, errors: int, messages: list<string>}
   *   Summary stats plus human-readable error messages.
   */
  public function import(string $csv, bool $delete_missing = FALSE): array {
    $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'messages' => [], 'info' => []];

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $csv);
    rewind($fh);

    $header = fgetcsv($fh);
    if ($header === FALSE || $header === NULL) {
      $stats['messages'][] = 'CSV file is empty.';
      $stats['errors']++;
      fclose($fh);
      return $stats;
    }

    $header  = array_map('trim', $header);
    $missing = array_diff(self::HEADERS, $header);
    if (!empty($missing)) {
      $stats['messages'][] = 'CSV is missing required columns: ' . implode(', ', $missing);
      $stats['errors']++;
      fclose($fh);
      return $stats;
    }

    $col          = array_flip($header);
    $leg_storage  = $this->entityTypeManager->getStorage('race_leg');
    $para_storage = $this->entityTypeManager->getStorage('paragraph');
    $race_storage = $this->entityTypeManager->getStorage('race');
    $seen         = [];
    $row_num      = 1;

    while (($row = fgetcsv($fh)) !== FALSE) {
      $row_num++;
      if (count($row) < count(self::HEADERS)) {
        $stats['messages'][] = "Row $row_num: too few columns, skipped.";
        $stats['skipped']++;
        continue;
      }

      $race_id         = (int) trim($row[$col['race_id']]);
      $leg_number      = (int) trim($row[$col['leg_number']]);
      $label           = trim($row[$col['label']]);
      $distance        = trim($row[$col['distance']]);
      $description     = trim($row[$col['description']]);
      $strava_route_id = trim($row[$col['strava_route_id']]);

      if (!$race_id || !$leg_number || $label === '') {
        $stats['messages'][] = "Row $row_num: race_id, leg_number, and label are required. Skipped.";
        $stats['skipped']++;
        continue;
      }
      if (!is_numeric($distance) || (float) $distance <= 0) {
        $stats['messages'][] = "Row $row_num: invalid distance '$distance'. Skipped.";
        $stats['skipped']++;
        continue;
      }
      if (!$race_storage->load($race_id)) {
        $stats['messages'][] = "Row $row_num: race_id $race_id does not exist. Skipped.";
        $stats['skipped']++;
        continue;
      }

      $seen[$race_id][$leg_number] = TRUE;

      try {
        $existing_ids = $leg_storage->getQuery()
          ->condition('race_id', $race_id)
          ->condition('leg_number', $leg_number)
          ->accessCheck(FALSE)
          ->execute();

        if ($existing_ids) {
          $leg    = $leg_storage->load(reset($existing_ids));
          $is_new = FALSE;
        }
        else {
          $leg    = $leg_storage->create(['race_id' => $race_id]);
          $is_new = TRUE;
        }

        $leg->set('leg_number', $leg_number);
        $leg->set('label', $label);
        $leg->set('description', $description !== '' ? ['value' => $description, 'format' => 'plain_text'] : NULL);

        $this->upsertStravaRoute($leg, $para_storage, $strava_route_id, $distance, $is_new);

        $leg->save();
        $is_new ? $stats['created']++ : $stats['updated']++;
      }
      catch (\Throwable $e) {
        $stats['messages'][] = "Row $row_num: " . $e->getMessage();
        $stats['errors']++;
      }
    }

    fclose($fh);

    if ($delete_missing && !empty($seen)) {
      $deleted = $this->deleteMissing($leg_storage, $para_storage, $seen);
      if ($deleted > 0) {
        $stats['info'][] = "Deleted $deleted leg(s) not present in CSV.";
      }
    }

    return $stats;
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns all races as [id => label] for use in select elements.
   */
  public function raceOptions(): array {
    $races = $this->entityTypeManager->getStorage('race')->loadMultiple();
    $options = [];
    foreach ($races as $race) {
      $options[$race->id()] = $race->label();
    }
    return $options;
  }

  /**
   * Creates or updates the leg's route paragraph with the CSV row's data.
   *
   * The route paragraph is required on every leg (it's the sole home for
   * distance, whether or not the leg has real Strava route data), so unlike
   * the old distance-on-the-leg behaviour, a blank strava_route_id no longer
   * deletes the paragraph — it just leaves field_strava_route_id empty.
   */
  private function upsertStravaRoute($leg, $para_storage, string $strava_route_id, string $distance, bool $is_new): void {
    $existing_para = NULL;
    if (!$is_new && !$leg->get('route')->isEmpty()) {
      $existing_para = $para_storage->load($leg->get('route')->target_id);
    }

    if ($existing_para) {
      $existing_para->set('field_strava_route_id', $strava_route_id !== '' ? $strava_route_id : NULL);
      $existing_para->set('field_distance', $distance);
      $existing_para->setNewRevision(FALSE);
      $existing_para->save();
      $leg->set('route', [
        'target_id'          => $existing_para->id(),
        'target_revision_id' => $existing_para->getRevisionId(),
      ]);
    }
    else {
      $para = $para_storage->create([
        'type'                  => 'strava_route',
        'field_strava_route_id' => $strava_route_id !== '' ? $strava_route_id : NULL,
        'field_distance'        => $distance,
      ]);
      $para->save();
      $leg->set('route', [
        'target_id'          => $para->id(),
        'target_revision_id' => $para->getRevisionId(),
      ]);
    }
  }

  private function deleteMissing($leg_storage, $para_storage, array $seen): int {
    $deleted = 0;
    foreach (array_keys($seen) as $race_id) {
      $all_ids = $leg_storage->getQuery()
        ->condition('race_id', $race_id)
        ->accessCheck(FALSE)
        ->execute();

      foreach ($leg_storage->loadMultiple($all_ids) as $leg) {
        $leg_number = (int) $leg->get('leg_number')->value;
        if (!isset($seen[$race_id][$leg_number])) {
          if (!$leg->get('route')->isEmpty()) {
            $para = $para_storage->load($leg->get('route')->target_id);
            if ($para) {
              $para_storage->delete([$para]);
            }
          }
          $leg_storage->delete([$leg]);
          $deleted++;
        }
      }
    }
    return $deleted;
  }

}
