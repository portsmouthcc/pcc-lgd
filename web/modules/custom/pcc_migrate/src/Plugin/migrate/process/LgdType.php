<?php

namespace Drupal\pcc_migrate\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateSkipRowException;

/**
 * Perform custom value transformations.
 *
 * @MigrateProcessPlugin(
 *   id = "lgd_type"
 * )
 *
 * To do custom value transformations use the following:
 *
 * @code
 * field_text:
 *   plugin: lgd_type
 * @endcode
 *
 */
class LgdType extends ProcessPluginBase
{
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property)
  {

    $guide_paths = ["/services/environmental-health/safety/flooding/"];

    if (isset($value)) {
      return $value;
    } else {
      $parent = $row->get('parent');
      $wp_type = $row->get('wp_type');
      $child_ids = $row->get('child_ids');
      $link = $row->get('link');

      // Check the list of guide overviews.
      if (in_array($link, $guide_paths)) {
        return 'localgov_guides_overview';
      }

      // Check for children of guide overviews.
      foreach ($guide_paths as $guide_path) {
        print_r($guide_path, $link);
        if (str_starts_with($link, $guide_path)) {
          return 'localgov_guides_page';
        }
      }

      // We only want pages.
      if ($wp_type == "page") {
        if ($parent == "766") {
          // Children of the services page are landing pages.
          return 'localgov_services_landing';
        } else {
          if (strlen($child_ids) > 0) {
            // Pages with children are sublanding pages.
            return 'localgov_services_sublanding';
          } else {
            // Anything else is a service page.
            return 'localgov_services_page';
          }
        }
      }

      // Anything else, don't import.
      throw new MigrateSkipRowException('Not a service landing page.');
    }
  }
}
