<?php

declare(strict_types=1);

namespace Drupal\pcc_osm_proxy\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\pcc_osm_proxy\TileBoundsCalculator;
use Drupal\pcc_osm_proxy\TileFetcher;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for PCC OSM Proxy.
 */
class PccOsmProxyCommands extends DrushCommands {

  /**
   * Microseconds to sleep between upstream tile fetches (default: 100ms).
   *
   * OSM asks that bulk fetching tools be polite. 100ms gives a maximum rate
   * of 10 requests/second, well within normal limits.
   */
  private const FETCH_DELAY_US = 100000;

  public function __construct(
    private readonly TileFetcher $tileFetcher,
    private readonly TileBoundsCalculator $boundsCalculator,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  /**
   * Pre-warms the tile cache for the configured bounding box.
   *
   * Fetches all tiles within the bounding_box config up to prewarm_max_zoom,
   * skipping tiles that are already cached. Outputs progress as it goes.
   *
   * @command pcc-osm-proxy:prewarm
   * @aliases osm-prewarm
   * @option min-zoom Minimum zoom level to pre-warm (default: 0)
   * @option max-zoom Override the configured prewarm_max_zoom
   * @option style Tile style machine name to pre-warm (default: standard)
   * @option delay-ms Milliseconds to sleep between requests (default: 100)
   * @usage drush pcc-osm-proxy:prewarm
   *   Pre-warm all tiles using config defaults.
   * @usage drush pcc-osm-proxy:prewarm --max-zoom=14
   *   Pre-warm tiles up to zoom 14 only.
   * @usage drush pcc-osm-proxy:prewarm --min-zoom=12 --max-zoom=16
   *   Pre-warm only zoom levels 12–16 (useful for topping up after initial warm).
   */
  public function prewarm(array $options = [
    'min-zoom' => 0,
    'max-zoom' => NULL,
    'style' => 'standard',
    'delay-ms' => 100,
  ]): void {
    $config = $this->configFactory->get('pcc_osm_proxy.settings');
    $bbox = $config->get('bounding_box');

    if (empty($bbox['min_lat']) || empty($bbox['max_lat']) || empty($bbox['min_lng']) || empty($bbox['max_lng'])) {
      $this->logger()->error('No bounding_box configured. Set min_lat, max_lat, min_lng, max_lng in pcc_osm_proxy.settings before pre-warming.');
      return;
    }

    $minLat = (float) $bbox['min_lat'];
    $maxLat = (float) $bbox['max_lat'];
    $minLng = (float) $bbox['min_lng'];
    $maxLng = (float) $bbox['max_lng'];
    $style = $options['style'];
    $minZoom = (int) $options['min-zoom'];
    $maxZoom = $options['max-zoom'] !== NULL
      ? (int) $options['max-zoom']
      : (int) ($config->get('prewarm_max_zoom') ?? 16);
    $delayUs = (int) $options['delay-ms'] * 1000;

    if ($minZoom > $maxZoom) {
      $this->logger()->error('min-zoom must be less than or equal to max-zoom.');
      return;
    }

    // Show a summary before starting so the operator knows what they're in for.
    $counts = $this->boundsCalculator->totalTileCount($minLat, $maxLat, $minLng, $maxLng, $minZoom, $maxZoom);
    $this->io()->title('PCC OSM Proxy — Cache Pre-warm');
    $this->io()->text(sprintf('Bounding box: %.4f,%.4f → %.4f,%.4f', $minLat, $minLng, $maxLat, $maxLng));
    $this->io()->text(sprintf('Zoom levels:  %d – %d', $minZoom, $maxZoom));
    $this->io()->text(sprintf('Style:        %s', $style));
    $this->io()->text(sprintf('Total tiles:  %s', number_format($counts['total'])));
    $this->io()->newLine();

    foreach ($counts['by_zoom'] as $z => $count) {
      $this->io()->text(sprintf('  Zoom %2d: %s tiles', $z, number_format($count)));
    }
    $this->io()->newLine();

    if (!$this->io()->confirm(sprintf('Fetch up to %s tiles from OSM? This may take a while.', number_format($counts['total'])))) {
      return;
    }

    $fetched = 0;
    $skipped = 0;
    $failed = 0;
    $total = $counts['total'];

    for ($z = $minZoom; $z <= $maxZoom; $z++) {
      $bounds = $this->boundsCalculator->tileBoundsForBox($minLat, $maxLat, $minLng, $maxLng, $z);
      $this->io()->text(sprintf('Zoom %d: fetching %s tiles (%d–%d x, %d–%d y)…',
        $z,
        number_format($bounds['count']),
        $bounds['min_x'], $bounds['max_x'],
        $bounds['min_y'], $bounds['max_y'],
      ));

      $progressBar = $this->io()->createProgressBar($bounds['count']);
      $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — fetched: %fetched%, skipped: %skipped%, failed: %failed%');
      $progressBar->setMessage('0', 'fetched');
      $progressBar->setMessage('0', 'skipped');
      $progressBar->setMessage('0', 'failed');
      $progressBar->start();

      // Collect failure messages silently during the loop so they don't
      // interrupt the progress bar. Printed after finish().
      $zoomFailures = [];

      for ($x = $bounds['min_x']; $x <= $bounds['max_x']; $x++) {
        for ($y = $bounds['min_y']; $y <= $bounds['max_y']; $y++) {
          // Check if tile file already exists — skip without hitting upstream.
          $filePath = sprintf(
            'public://pcc_osm_proxy_tiles/%s/%d/%d/%d.png',
            $style, $z, $x, $y,
          );

          if (file_exists($filePath)) {
            $skipped++;
          }
          else {
            try {
              $tile = $this->tileFetcher->fetch($style, $z, $x, $y, TRUE);
              if ($tile !== NULL) {
                $fetched++;
              }
              else {
                $failed++;
                $zoomFailures[] = sprintf('%s/%d/%d/%d — null response', $style, $z, $x, $y);
              }
            }
            catch (\Exception $e) {
              $failed++;
              $zoomFailures[] = sprintf('%s/%d/%d/%d — %s', $style, $z, $x, $y, $e->getMessage());
            }

            // Be polite to OSM — don't fetch faster than configured rate.
            usleep($delayUs);
          }

          $progressBar->setMessage((string) $fetched, 'fetched');
          $progressBar->setMessage((string) $skipped, 'skipped');
          $progressBar->setMessage((string) $failed, 'failed');
          $progressBar->advance();
        }
      }

      $progressBar->finish();
      $this->io()->newLine(2);

      // Now safe to print any failures — progress bar is done.
      foreach ($zoomFailures as $msg) {
        $this->logger()->warning($msg);
      }
    }

    $this->io()->success(sprintf(
      'Pre-warm complete. Fetched: %s, Skipped (already cached): %s, Failed: %s / %s total.',
      number_format($fetched),
      number_format($skipped),
      number_format($failed),
      number_format($total),
    ));

    if ($failed > 0) {
      $this->io()->warning('Some tiles failed to fetch. Check the Drupal logs. Re-run this command to retry failed tiles (cached tiles will be skipped).');
    }
  }

  /**
   * Shows a tile count estimate for the configured bounding box.
   *
   * Useful for checking before committing to a full pre-warm.
   *
   * @command pcc-osm-proxy:count-tiles
   * @aliases osm-count
   * @option min-zoom Minimum zoom level (default: 0)
   * @option max-zoom Maximum zoom level (default: prewarm_max_zoom config)
   * @usage drush pcc-osm-proxy:count-tiles
   *   Show tile counts for the configured bounding box.
   * @usage drush pcc-osm-proxy:count-tiles --max-zoom=18
   *   Show what a full zoom-18 pre-warm would cost.
   */
  public function countTiles(array $options = [
    'min-zoom' => 0,
    'max-zoom' => NULL,
  ]): void {
    $config = $this->configFactory->get('pcc_osm_proxy.settings');
    $bbox = $config->get('bounding_box');

    if (empty($bbox['min_lat']) || empty($bbox['max_lat']) || empty($bbox['min_lng']) || empty($bbox['max_lng'])) {
      $this->logger()->error('No bounding_box configured in pcc_osm_proxy.settings.');
      return;
    }

    $minLat = (float) $bbox['min_lat'];
    $maxLat = (float) $bbox['max_lat'];
    $minLng = (float) $bbox['min_lng'];
    $maxLng = (float) $bbox['max_lng'];
    $minZoom = (int) $options['min-zoom'];
    $maxZoom = $options['max-zoom'] !== NULL
      ? (int) $options['max-zoom']
      : (int) ($config->get('prewarm_max_zoom') ?? 16);

    $counts = $this->boundsCalculator->totalTileCount($minLat, $maxLat, $minLng, $maxLng, $minZoom, $maxZoom);

    $this->io()->title('Tile count estimate');
    $this->io()->text(sprintf('Bounding box: %.4f,%.4f → %.4f,%.4f', $minLat, $minLng, $maxLat, $maxLng));
    $this->io()->newLine();

    $rows = [];
    foreach ($counts['by_zoom'] as $z => $count) {
      // Rough time estimate at 100ms per tile.
      $seconds = $count * 0.1;
      $rows[] = [
        $z,
        number_format($count),
        $seconds < 60 ? sprintf('%ds', (int) $seconds) : sprintf('%dm %ds', (int) ($seconds / 60), (int) ($seconds % 60)),
      ];
    }

    $this->io()->table(['Zoom', 'Tiles', 'Est. time @ 100ms/tile'], $rows);
    $this->io()->text(sprintf('Total: %s tiles', number_format($counts['total'])));

    $totalSeconds = $counts['total'] * 0.1;
    $this->io()->text(sprintf(
      'Estimated pre-warm time: %dh %dm at 100ms/tile (10 req/s)',
      (int) ($totalSeconds / 3600),
      (int) (($totalSeconds % 3600) / 60),
    ));
  }

}
