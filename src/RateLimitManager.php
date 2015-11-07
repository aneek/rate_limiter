<?php
/**
 * @file
 * Contains \Drupal\rate_limiter\RateLimitManager.
 */

namespace Drupal\rate_limiter;

use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\HeaderBag;

/**
 * Rate Limiting Manager.
 *
 * @package Drupal\rate_limiter
 */
class RateLimitManager implements RateLimitManagerInterface {

  /**
   * Rate limiter configuration storage.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $rateLimitingConfig;

  /**
   * Enable rate limiting for all requests including anonymous access to API.
   *
   * @var const
   */
  const RATE_LIMIT_ALL_REQUEST = 0;

  /**
   * Enable rate limiting based on IP addresses.
   *
   * @var constant
   */
  const RATE_LIMIT_ON_IP = 1;

  /**
   * Rate limiter global cache id.
   *
   * @var constant
   */
  const RATE_LIMIT_GLOBAL_CID = 'rate_limiter.global';

  /**
   * Class Constructor.
   */
  public function __construct() {
    $this->rateLimitingConfig = \Drupal::config('rate_limiter.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function availableLimitOptions() {
    return [
      self::RATE_LIMIT_ALL_REQUEST => t('Rate limit all requests'),
      self::RATE_LIMIT_ON_IP => t('Rate limit based on IP address'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (bool) $this->rateLimitingConfig->get('enable');
  }

  /**
   * {@inheritdoc}
   */
  public function isServiceRequest(Request $request) {
    // The request is not an AJAX request.
    if (!$request->isXmlHttpRequest()) {
      $requestHeaders = $request->headers;
      $accept = AcceptHeader::fromString($requestHeaders->get('Accept'));
      if ((string) $accept->filter('/\btext\/\b/')->first() == 'text/html') {
        return FALSE;
      }
      elseif ($this->acceptType($requestHeaders) !== NULL) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function acceptType(HeaderBag $header) {
    $mapping = [
      'application/json' => 'json',
      'application/hal+json' => 'hal_json',
      'application/xml' => 'xml',
      'text/html' => 'html',
    ];
    $type = NULL;
    $accept = AcceptHeader::fromString($header->get('Accept'));
    foreach ($mapping as $header => $name) {
      if ($accept->has($header)) {
        $type = $name;
        break;
      }
    }
    return $type;
  }

}