<?php

declare(strict_types=1);

namespace Drupal\PccOsmTileProxy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\RfcLogLevel;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Controller for OSM proxy route.
 */
class TilesController extends ControllerBase {

  /**
   * HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private $httpClient;

  /**
   * Logging service set to PccOsmTileProxy channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * Controller constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   HTTP client.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
    $this->logger = $this->getLogger('PccOsmTileProxy');
  }

  /**
   * Build the response.
   */

  /**
   * Public function build($z, $x, $y): Response {.
   */
  public function build(): array {
    // $url = "https://tile.openstreetmap.org/{$z}/{$x}/{$y}.png";
    $url = "https://tile.openstreetmap.org/1/1/1.png";

    $data = NULL;

    try {
      $response = $this->httpClient->get($url);
      $data = $response->getBody();
    }
    catch (RequestException $e) {
      $this->logger->log(RfcLogLevel::WARNING, $e->getMessage());
    }

  }

}
