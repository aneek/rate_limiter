<?php
/**
 * @file
 * Contains \Drupal\rate_limiter\RateLimitManager.
 */

namespace Drupal\rate_limiter;

use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\HeaderBag;
use Drupal\Core\Cache\CacheBackendInterface;

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
   * Class Constructor.
   */
  public function __construct() {
    $this->rateLimitingConfig = \Drupal::config('rate_limiter.settings');
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
  public function isServiceRequest(HeaderBag $header) {
    $accept = AcceptHeader::fromString($header->get('Accept'));
    if ($accept->has('text/html')) {
      return FALSE;
    }
    // @todo: Find a better way to validate requests against each service call.
    return TRUE;
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

  /**
   * {@inheritdoc}
   */
  public function getRateLimitingRule() {
    return (int) $this->rateLimitingConfig->get('limiting_rule');
  }

  /**
   * {@inheritdoc}
   */
  public function limitRequests() {
    // Check which rate limiting rule is enabled.
    $limitOption = $this->getRateLimitingRule();
    // If the option is global rate limiter then we only need a simple cache
    // store.
    $this->rateLimitAll();
    \Drupal::cache()->set('my_value', ['A', 'B'], CacheBackendInterface::CACHE_PERMANENT, ['my_first_tag']);

    $a = \Drupal::cache()->get('my_value');
    var_dump($a);
    return TRUE;
  }


  protected function rateLimitAll() {
    $cache = \Drupal::cache()->get('rate_limiter.global');

  }
}