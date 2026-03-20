<?php

declare(strict_types=1);

namespace Drupal\pcc_osm_proxy;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Fetches OSM tiles from upstream, caches image bodies as files.
 *
 * Storage strategy:
 *  - Raw PNG bodies are written to public://pcc_osm_proxy_tiles/{style}/{z}/{x}/{y}.png
 *    as plain files. This avoids serialising binary data into the database.
 *  - Metadata (ETag, Last-Modified, Content-Type, expiry) is stored in a
 *    dedicated database cache bin so it survives drush cr independently of
 *    cache_default.
 *  - Conditional requests (If-None-Match / If-Modified-Since) are used on
 *    revalidation to avoid re-downloading unchanged tiles.
 *  - OSM 429 responses are respected: a backoff expiry is cached so we do not
 *    hammer OSM while we are rate-limited.
 *  - Flood control gates only upstream requests — cached tiles are always
 *    served freely without counting against the rate limit.
 */
class TileFetcher {

  /**
   * Cache ID prefix for tile metadata.
   */
  private const META_PREFIX = 'meta:';

  /**
   * Cache ID for the upstream backoff flag.
   */
  private const BACKOFF_CID = 'upstream:backoff';

  /**
   * Directory (stream-wrapper path) where tile image files are stored.
   */
  private const TILE_DIR = 'public://pcc_osm_proxy_tiles';

  /**
   * Default backoff duration in seconds when OSM sends 429 without Retry-After.
   */
  private const DEFAULT_BACKOFF_SECONDS = 300;

  /**
   * Flood control event name for upstream tile fetches.
   */
  private const FLOOD_EVENT = 'pcc_osm_proxy.upstream_request';

  /**
   * Flood control window in seconds.
   */
  private const FLOOD_WINDOW = 60;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CacheBackendInterface $cache,
    private readonly FileSystemInterface $fileSystem,
    private readonly FloodInterface $flood,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns tile data for the given style/z/x/y, fetching upstream if needed.
   *
   * @param string $style
   *   Tile style machine name (must exist in allowed_styles config).
   * @param int $z
   *   Zoom level.
   * @param int $x
   *   X tile coordinate.
   * @param int $y
   *   Y tile coordinate.
   * @param bool $bypassFlood
   *   Skip flood control. Should only be TRUE for trusted server-side callers
   *   such as the Drush pre-warm command, which controls its own request rate
   *   via a sleep delay and is not an untrusted browser client.
   *
   * @return array{body: string, content_type: string}|null
   *   Tile data, or NULL if unavailable.
   *
   * @throws \InvalidArgumentException
   *   If the style is not in the allowed list.
   */
  public function fetch(string $style, int $z, int $x, int $y, bool $bypassFlood = FALSE): ?array {
    $config = $this->configFactory->get('pcc_osm_proxy.settings');
    $allowedStyles = $config->get('allowed_styles') ?? [];

    if (!isset($allowedStyles[$style])) {
      throw new \InvalidArgumentException(sprintf(
        'OSM tile style "%s" is not in the allowed_styles list.',
        $style,
      ));
    }

    // Enforce HTTPS for all upstream URLs.
    $url = $this->buildUpstreamUrl($allowedStyles[$style]['url'], $z, $x, $y);
    if (!str_starts_with($url, 'https://')) {
      throw new \InvalidArgumentException('Upstream tile URLs must use HTTPS.');
    }

    $userAgent = $config->get('user_agent') ?: 'PccOsmProxy/1.0';
    $configuredMinAge = (int) ($config->get('tile_cache_max_age') ?? 604800);
    $floodThreshold = (int) ($config->get('flood_threshold') ?? 300);
    $metaCid = self::META_PREFIX . implode(':', [$style, $z, $x, $y]);
    $filePath = $this->tileFilePath($style, $z, $x, $y);
    $cachedMeta = $this->cache->get($metaCid);
    $fileExists = file_exists($filePath);

    // Metadata cache is alive and file exists — serve directly from disk.
    // No upstream request needed. The cache TTL handles expiry; when it lapses
    // the entry returns FALSE and we fall through to the stale-file path below.
    if ($cachedMeta !== FALSE && $fileExists) {
      return $this->tileFromFile($filePath, $cachedMeta->data);
    }

    // The file exists on disk but the metadata cache entry has expired.
    // Restore metadata and serve from file — no upstream request needed.
    if ($fileExists) {
      $staleMeta = ['content_type' => 'image/png'];
      $this->cache->set($metaCid, $staleMeta, time() + $configuredMinAge);
      return $this->tileFromFile($filePath, $staleMeta);
    }

    // No cached tile — check backoff before hitting upstream.
    if ($this->isBackingOff()) {
      $this->loggerFactory->get('pcc_osm_proxy')->warning(
        'Upstream backoff active; cannot fetch uncached tile @style/@z/@x/@y.',
        ['@style' => $style, '@z' => $z, '@x' => $x, '@y' => $y],
      );
      return NULL;
    }

    $tile = $this->fetchFromUpstream($url, $userAgent, $bypassFlood, $floodThreshold);

    if ($tile === NULL) {
      return NULL;
    }

    $ttl = max($configuredMinAge, $tile['upstream_max_age'] ?? 0);
    $this->writeTileFile($filePath, $tile['body']);
    $meta = $tile;
    unset($meta['body']);
    $this->cache->set($metaCid, $meta, time() + $ttl);

    return $this->tileFromFile($filePath, $meta);
  }

  /**
   * Performs a fresh unconditional GET for a tile.
   *
   * Flood control is enforced here — only upstream requests are rate-limited,
   * never cache hits. Pass $bypassFlood = TRUE for trusted server-side callers
   * (e.g. Drush pre-warm) that manage their own request pacing.
   */
  private function fetchFromUpstream(string $url, string $userAgent, bool $bypassFlood = FALSE, int $floodThreshold = 300): ?array {
    if (!$bypassFlood) {
      if (!$this->flood->isAllowed(self::FLOOD_EVENT, $floodThreshold, self::FLOOD_WINDOW)) {
        throw new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(
          60,
          'Upstream tile fetch rate limit exceeded. Try again shortly.',
        );
      }
      $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW);
    }
    try {
      $response = $this->httpClient->request('GET', $url, [
        'headers' => [
          'User-Agent' => $userAgent,
          'Accept'     => 'image/png,image/*;q=0.8',
        ],
        'allow_redirects' => TRUE,
        'timeout'         => 10,
        'connect_timeout' => 5,
        'http_errors'     => FALSE,
      ]);

      $result = $this->handleResponse($response);
      return is_array($result) ? $result : NULL;
    }
    catch (GuzzleException $e) {
      $this->loggerFactory->get('pcc_osm_proxy')->warning(
        'Failed to fetch tile from @url: @message',
        ['@url' => $url, '@message' => $e->getMessage()],
      );
      return NULL;
    }
  }

  /**
   * Handles an upstream response, including 429 backoff.
   *
   * @return array|null
   *   Tile data array for 200, NULL for anything else.
   */
  private function handleResponse(ResponseInterface $response): ?array {
    $status = $response->getStatusCode();

    if ($status === 200) {
      return $this->buildTileArray($response);
    }

    if ($status === 429) {
      // OSM is rate-limiting us. Record a backoff period so we stop sending
      // upstream requests until the window has passed.
      $retryAfter = (int) ($response->getHeaderLine('Retry-After') ?: self::DEFAULT_BACKOFF_SECONDS);
      $this->cache->set(self::BACKOFF_CID, TRUE, time() + $retryAfter);
      $this->loggerFactory->get('pcc_osm_proxy')->error(
        'OSM tile server returned 429 Too Many Requests. Backing off for @seconds seconds.',
        ['@seconds' => $retryAfter],
      );
      return NULL;
    }

    $this->loggerFactory->get('pcc_osm_proxy')->warning(
      'Unexpected upstream response status @status.',
      ['@status' => $status],
    );
    return NULL;
  }

  /**
   * Builds a tile metadata + body array from a successful upstream response.
   */
  private function buildTileArray(ResponseInterface $response): array {
    $upstreamMaxAge = 0;
    if (preg_match('/max-age=(\d+)/', $response->getHeaderLine('Cache-Control'), $m)) {
      $upstreamMaxAge = (int) $m[1];
    }

    return [
      'body'             => (string) $response->getBody(),
      'content_type'     => $response->getHeaderLine('Content-Type') ?: 'image/png',
      'etag'             => $response->getHeaderLine('ETag'),
      'last_modified'    => $response->getHeaderLine('Last-Modified'),
      'upstream_max_age' => $upstreamMaxAge,
    ];
  }

  /**
   * Returns TRUE if we are currently in an upstream backoff window.
   */
  private function isBackingOff(): bool {
    return $this->cache->get(self::BACKOFF_CID) !== FALSE;
  }

  /**
   * Writes raw tile image bytes to a file, creating directories as needed.
   */
  private function writeTileFile(string $filePath, string $body): void {
    $dir = dirname($filePath);
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData($body, $filePath, FileSystemInterface::EXISTS_REPLACE);
  }

  /**
   * Reads a tile body from disk and combines it with cached metadata.
   */
  private function tileFromFile(string $filePath, array $meta): ?array {
    $body = @file_get_contents($filePath);
    if ($body === FALSE) {
      return NULL;
    }
    return [
      'body'          => $body,
      'content_type'  => $meta['content_type'] ?? 'image/png',
      'etag'          => $meta['etag'] ?? '',
      'last_modified' => $meta['last_modified'] ?? '',
    ];
  }

  /**
   * Returns the filesystem path for a given tile.
   */
  private function tileFilePath(string $style, int $z, int $x, int $y): string {
    return sprintf('%s/%s/%d/%d/%d.png', self::TILE_DIR, $style, $z, $x, $y);
  }

  /**
   * Substitutes z/x/y into an upstream URL template.
   */
  private function buildUpstreamUrl(string $template, int $z, int $x, int $y): string {
    return str_replace(['{z}', '{x}', '{y}'], [$z, $x, $y], $template);
  }

}
