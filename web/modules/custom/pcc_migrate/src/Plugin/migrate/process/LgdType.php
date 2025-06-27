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

    $guide_paths = [
      "/services/parking-roads-and-travel/parking/blue-badges/applying-for-a-blue-badge/",
      "/services/parking-roads-and-travel/parking/where-to-park-in-the-city/resident-parking-zones/resident-parking-permits/",
      "/services/parking-roads-and-travel/parking/parking-fines-and-penalty-charge-notices/",
      "/services/parking-roads-and-travel/parking/disabled-parking-bays/",
      "/services/parking-roads-and-travel/parking/parking-suspensions/",
      "/services/parking-roads-and-travel/travel/road-safety/",
      "/services/parking-roads-and-travel/travel/bus-passes/older-persons-bus-pass/",
      "/services/parking-roads-and-travel/travel/bus-passes/disabled-persons-bus-pass/",
      "/services/parking-roads-and-travel/travel/bus-passes/companion-bus-passes/",
      "/services/children-and-families/early-help-and-prevention-service/lgbtq-young-people-and-their-families/",
      "/services/children-and-families/portsmouth-family-hubs/support-for-you-and-your-child-at-our-hubs/",
      "/services/children-and-families/support-for-families/being-a-parent/",
    ];

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
