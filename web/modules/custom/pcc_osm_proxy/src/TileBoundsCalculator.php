<?php

declare(strict_types=1);

namespace Drupal\pcc_osm_proxy;

/**
 * Converts geographic coordinates to OSM tile coordinates.
 *
 * OSM uses a Mercator projection. The formulae here are from the OSM wiki:
 * https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames
 */
class TileBoundsCalculator {

  /**
   * Returns the tile X index for a given longitude and zoom level.
   */
  public function longitudeToTileX(float $lng, int $zoom): int {
    return (int) floor(($lng + 180) / 360 * (2 ** $zoom));
  }

  /**
   * Returns the tile Y index for a given latitude and zoom level.
   */
  public function latitudeToTileY(float $lat, int $zoom): int {
    $latRad = deg2rad($lat);
    return (int) floor(
      (1 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2 * (2 ** $zoom)
    );
  }

  /**
   * Returns the bounding tile range for a lat/lng box at a given zoom level.
   *
   * @return array{min_x: int, max_x: int, min_y: int, max_y: int, count: int}
   */
  public function tileBoundsForBox(
    float $minLat,
    float $maxLat,
    float $minLng,
    float $maxLng,
    int $zoom,
  ): array {
    $minX = $this->longitudeToTileX($minLng, $zoom);
    $maxX = $this->longitudeToTileX($maxLng, $zoom);
    // Note: Y increases southward in tile coordinates, so maxLat = minY.
    $minY = $this->latitudeToTileY($maxLat, $zoom);
    $maxY = $this->latitudeToTileY($minLat, $zoom);

    return [
      'min_x' => $minX,
      'max_x' => $maxX,
      'min_y' => $minY,
      'max_y' => $maxY,
      'count' => ($maxX - $minX + 1) * ($maxY - $minY + 1),
    ];
  }

  /**
   * Returns total tile count across all zoom levels for a bounding box.
   *
   * @return array{total: int, by_zoom: array<int, int>}
   */
  public function totalTileCount(
    float $minLat,
    float $maxLat,
    float $minLng,
    float $maxLng,
    int $minZoom,
    int $maxZoom,
  ): array {
    $total = 0;
    $byZoom = [];
    for ($z = $minZoom; $z <= $maxZoom; $z++) {
      $bounds = $this->tileBoundsForBox($minLat, $maxLat, $minLng, $maxLng, $z);
      $byZoom[$z] = $bounds['count'];
      $total += $bounds['count'];
    }
    return ['total' => $total, 'by_zoom' => $byZoom];
  }

  /**
   * Checks whether a tile x/y/z falls within a lat/lng bounding box.
   */
  public function tileIsWithinBounds(
    int $z,
    int $x,
    int $y,
    float $minLat,
    float $maxLat,
    float $minLng,
    float $maxLng,
  ): bool {
    $bounds = $this->tileBoundsForBox($minLat, $maxLat, $minLng, $maxLng, $z);
    return $x >= $bounds['min_x']
      && $x <= $bounds['max_x']
      && $y >= $bounds['min_y']
      && $y <= $bounds['max_y'];
  }

}
