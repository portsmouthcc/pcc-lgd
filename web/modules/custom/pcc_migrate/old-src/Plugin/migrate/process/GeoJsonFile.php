<?php

namespace Drupal\custom_migrate_tools\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Creates a file from supplied data.
 *
 * @MigrateProcessPlugin(
 *   id = "file_create"
 * )
 */
class GeoJsonFile extends ProcessPluginBase
{

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
  {
    // Ensure the value is an array with 'data' and 'filename' keys.
    if (!is_array($value) || !isset($value['data']) || !isset($value['filename'])) {
      throw new \InvalidArgumentException('The input must be an array with "data" and "filename" keys.');
    }

    $data = $value['data'];
    $filename = $value['filename'];

    // Prepare the directory.
    $directory = 'public://geojson';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Save the file.
    $file_path = $directory . '/' . $filename;
    $file = file_save_data($data, $file_path, FileSystemInterface::EXISTS_REPLACE);

    if ($file) {
      return $file->id(); // Return the file ID for the destination.
    }

    throw new \RuntimeException('Failed to create the file.');
  }
}
