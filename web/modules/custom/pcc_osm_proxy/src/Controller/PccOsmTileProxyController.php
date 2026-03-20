<?php

declare(strict_types=1);

namespace Drupal\pcc_osm_proxy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\pcc_osm_proxy\TileBoundsCalculator;
use Drupal\pcc_osm_proxy\TileFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Serves OSM map tiles fetched server-side.
 */
class PccOsmTileProxyController extends ControllerBase {

  /**
   * Maximum sensible zoom level for OSM standard tiles.
   */
  private const MAX_ZOOM = 19;

  public function __construct(
    private readonly TileFetcher $tileFetcher,
    private readonly TileBoundsCalculator $boundsCalculator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('pcc_osm_proxy.tile_fetcher'),
      $container->get('pcc_osm_proxy.bounds_calculator'),
    );
  }

  /**
   * Returns a proxied tile image.
   *
   * TooManyRequestsHttpException may bubble up from TileFetcher if the
   * upstream request rate limit is exceeded on a cache miss.
   */
  public function tile(Request $request, string $style, string $z, string $x, string $y): Response {
    // Sanitise the style name — only lowercase letters, digits, underscores.
    if (!preg_match('/^[a-z0-9_]+$/', $style)) {
      throw new BadRequestHttpException('Invalid tile style name.');
    }

    $z = (int) $z;
    $x = (int) $x;
    $y = (int) $y;

    $this->validateCoordinates($z, $x, $y);
    $this->validateBoundingBox($z, $x, $y);

    try {
      $tile = $this->tileFetcher->fetch($style, $z, $x, $y);
    }
    catch (\InvalidArgumentException) {
      throw new NotFoundHttpException();
    }

    if ($tile === NULL) {
      throw new ServiceUnavailableHttpException(60, 'Upstream tile server unavailable.');
    }

    $config = $this->config('pcc_osm_proxy.settings');
    $maxAge = (int) ($config->get('tile_cache_max_age') ?? 604800);

    $headers = [
      'Content-Type'           => $tile['content_type'],
      'Cache-Control'          => sprintf('public, max-age=%d', $maxAge),
      'Vary'                   => 'Accept-Encoding',
      'Referrer-Policy'        => 'no-referrer',
      'X-Content-Type-Options' => 'nosniff',
    ];

    if (!empty($tile['etag'])) {
      $headers['ETag'] = $tile['etag'];
    }
    if (!empty($tile['last_modified'])) {
      $headers['Last-Modified'] = $tile['last_modified'];
    }

    return new Response($tile['body'], Response::HTTP_OK, $headers);
  }

  /**
   * Enforces the configured geographic bounding box.
   *
   * If no bounding box is configured, all tiles are allowed. If one is
   * configured, tiles outside it return 403 — this keeps the cache small and
   * prevents the proxy being used as a general-purpose OSM mirror.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  private function validateBoundingBox(int $z, int $x, int $y): void {
    $config = $this->config('pcc_osm_proxy.settings');
    $bbox = $config->get('bounding_box');

    // If any coordinate is null, bounding box is disabled.
    if (
      empty($bbox) ||
      $bbox['min_lat'] === NULL || $bbox['max_lat'] === NULL ||
      $bbox['min_lng'] === NULL || $bbox['max_lng'] === NULL
    ) {
      return;
    }

    if (!$this->boundsCalculator->tileIsWithinBounds(
      $z, $x, $y,
      (float) $bbox['min_lat'],
      (float) $bbox['max_lat'],
      (float) $bbox['min_lng'],
      (float) $bbox['max_lng'],
    )) {
      throw new AccessDeniedHttpException('Tile is outside the configured bounding box.');
    }
  }

  /**
   * Validates that z/x/y are within the legal range for OSM tiles.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  private function validateCoordinates(int $z, int $x, int $y): void {
    if ($z < 0 || $z > self::MAX_ZOOM) {
      throw new BadRequestHttpException(sprintf(
        'Zoom level %d is out of range (0–%d).',
        $z,
        self::MAX_ZOOM,
      ));
    }

    $max = (2 ** $z) - 1;
    if ($x < 0 || $x > $max || $y < 0 || $y > $max) {
      throw new BadRequestHttpException(sprintf(
        'Tile coordinates x=%d, y=%d are out of range for zoom %d (0–%d).',
        $x,
        $y,
        $z,
        $max,
      ));
    }
  }

}
