<?php

namespace Drupal\rate_limiter;

use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\HeaderBag;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Rate Limiting Manager.
 *
 * @package rate_limiter
 */
class RateLimitManager implements RateLimitManagerInterface {

  /**
   * Rate limiter configuration storage.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $rateLimitingConfig;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private $request;

  /**
   * The cache id.
   *
   * @var string
   */
  private $cid;

  /**
   * The cache tag.
   *
   * @var array
   */
  private $cacheTag = [];

  /**
   * The bucket which stores the requests.
   *
   * @var array
   */
  private $bucket = [];

  /**
   * Flag checks if the bucket is overflowing or not.
   *
   * @var bool
   */
  private $overflowing = FALSE;

  /**
   * Default serialization format options (backward compatibility).
   * 
   * Used for '_format' query string.
   *
   * @link https://www.drupal.org/docs/8/core/modules/rest/1-getting-started-rest-configuration-rest-request-fundamentals
   *
   * @var array
   */
  private $default_format = ['json', 'hal_json', 'xml'];

  /**
   * Enable rate limiting for all requests including anonymous access to API.
   */
  const RATE_LIMIT_ALL_REQUEST = 0;

  /**
   * Enable rate limiting based on IP addresses.
   */
  const RATE_LIMIT_ON_IP = 1;

  /**
   * The rate limiter cache bin name.
   */
  const RATE_LIMIT_CACHE_BIN = 'ratelimit';

  /**
   * Rate limiter global cache id.
   */
  const RATE_LIMIT_CACHE_PREFIX = 'rate_limiter.';

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
   * Method returns an associative array of allowed accept header types.
   *
   * @return array
   *   Associative array of allowd accept header types.
   */
  public static function allowedAcceptTypes() {
    return [
      'application/json' => 'json',
      'application/hal+json' => 'hal_json',
      'application/xml' => 'xml',
      'text/html' => 'html',
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
    // Check if this is not a php cli request.
    if (php_sapi_name() === 'cli' || defined('STDIN')) {
      return FALSE;
    }
    // The request is not an AJAX request.
    if (!$request->isXmlHttpRequest()) {
      // What option the user selected?
      $selected = $this->rateLimitingConfig->get('override_default_types');
      // If user selected the default one then query string, 
      // Accept header and Content type will be checked.
      switch ($selected) {
        case 'none':
          return (
            $this->validateOnQueryString($request) || 
            $this->validateOnAcceptTypes($request) ||
            $this->validateOnContentTypes($request)
          );
        
        case 'query_string':
          return ($this->validateOnQueryString($request) || $this->validateOnContentTypes($request));
        
        case 'request_header':
          return ($this->validateOnAcceptTypes($request) || $this->validateOnContentTypes($request));
      }
    }
    elseif ($request->get('ajax_iframe_upload', FALSE)) {
      return FALSE;
    } else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function limit(Request $request) {

  }

  /**
   * {@inheritdoc}
   */
  public function respond() {

  }

  /**
   * Returns the user defined query string which will be used to determine a service request.
   *
   * @return array
   */
  private function getQueryStringOption() {
    $data = [];
    $selected = $this->rateLimitingConfig->get('override_default_types');
    switch ($selected) {
      case 'none':
        // If nothing selected then we'll use '_format'.
        $data = [
          'key' => '_format',
          'values' => $this->default_format
        ];
        break;
      case 'query_string':
        // User has selected a custom format. So we have to parse and then return.
        $input = explode("=", $this->rateLimitingConfig->get('query_string'));
        $data = [
          'key' => $input[0],
          'values' => [$input[1]],
        ];
        break;
    }
    return $data;
  }

  /**
   * Checks if a request is a service request or not based on URL.
   *
   * @param Request $request
   *
   * @return bool
   */
  private function validateOnQueryString(Request $request) {
    $qsKey = $this->getQueryStringOption()['key'];
    $qsValues = $this->getQueryStringOption()['values'];
    if (($request->query->has($qsKey))) {
      $format = $request->query->get($qsKey);
      if (in_array($format, $qsValues)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function validateOnAcceptTypes(Request $request) {

  }

  /**
   * If the Content type header returns 'application/json' or anything similar,
   * then the request is a service request.
   *
   * @param Request $request
   * 
   * @return bool
   */
  private function validateOnContentTypes(Request $request) {
    $allowed = array_keys(self::allowedAcceptTypes());
    array_pop($allowed);
    $contentType = $request->headers->get('content-type', null);
    if (in_array($contentType, $allowed)) {
      return TRUE;
    }
    return FALSE;
  }
}
