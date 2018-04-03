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
   * @var array
   *
   * @link https://www.drupal.org/docs/8/core/modules/rest/1-getting-started-rest-configuration-rest-request-fundamentals
   */
  private $defaultFormat = ['json', 'hal_json', 'xml'];

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
            $this->validateOnHeaderTypes($request) ||
            $this->validateOnContentTypes($request)
          );

        case 'query_string':
          return ($this->validateOnQueryString($request) || $this->validateOnContentTypes($request));

        case 'request_header':
          return ($this->validateOnHeaderTypes($request) || $this->validateOnContentTypes($request));
      }
    }
    elseif ($request->get('ajax_iframe_upload', FALSE)) {
      return FALSE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function limit(Request $request) {
    $this->request = $request;
    // Based on the selected Rate limiting rule set the cache and bucket.
    switch ($this->rateLimitingConfig->get('limiting_rule')) {
      case self::RATE_LIMIT_ALL_REQUEST:
        $this->cid = self::RATE_LIMIT_CACHE_PREFIX . 'global';
        $this->cacheTag = ['rate_limit_global'];
        break;

      case self::RATE_LIMIT_ON_IP:
        $ip = $request->getClientIp();
        $this->cid = self::RATE_LIMIT_CACHE_PREFIX . str_replace(".", "_", $ip);
        $this->cacheTag = ['rate_limit_on_ip'];
        // Do not rate limit the current client ip if the whitelisting is
        // enabled and the current ip is in the list.
        $whitelists = $this->getWhiteListedIps();
        if (!empty($whitelists) && in_array($ip, $whitelists)) {
          // Early return so the rate limiter services will not be called.
          return FALSE;
        }
        break;
    }
    // Get the bucket that was previously set.
    $this->bucket = $this->getBucket();
    // Fill the bucket.
    $this->fillBucket();
    // Save the bucket.
    $this->saveBucket();
    // Return the status if the bucket is at capacity or overflowing.
    return $this->isOverflowing();
  }

  /**
   * {@inheritdoc}
   */
  public function respond() {
    $type = $this->acceptType($this->request->headers);
    $custom_message = $this->rateLimitingConfig->get('message');
    $message = 'Too many requests';
    if (!empty($custom_message)) {
      $message = $custom_message;
    }
    // Set the retry after header.
    $retry = $this->bucket['bucket_flush_time'] - $this->bucket['request_time'];
    $headers = ['Retry-After' => $retry];
    // If request header requested for JSON data then respond with JSON.
    if (in_array($type, ['json', 'hal_json'])) {
      return new JsonResponse(['message' => $message], Response::HTTP_TOO_MANY_REQUESTS, $headers);
    }
    else {
      return new Response($message, Response::HTTP_TOO_MANY_REQUESTS, $headers);
    }
  }

  /**
   * Method fills the bucket.
   *
   * If the bucket doesn't have more capacity then it is marked as overflown.
   */
  protected function fillBucket() {
    // If the bucket is empty then fill with the first drop and update the
    // bucket leaking time. Once the leaking time is reached then the bucket
    // will be empty.
    $this->bucket['drops'] = $this->bucket['drops'] ?: 0;
    $this->bucket['drops']++;
    $request_time = time();
    $this->bucket['request_time'] = $request_time;
    $this->bucket['bucket_flush_time'] = $this->bucket['bucket_flush_time'] ?: $request_time + (int) $this->rateLimitingConfig->get('time_cap');
    // If the bucket flush time is lapsed then reset the counter.
    if ($this->bucket['request_time'] > $this->bucket['bucket_flush_time']) {
      $this->bucket['drops'] = 1;
      $this->bucket['bucket_flush_time'] = $request_time + (int) $this->rateLimitingConfig->get('time_cap');
    }
    // Once the bucket data is stored, check if it's overflowing or not.
    $allowed_drops = $this->rateLimitingConfig->get('requests');
    if ($this->bucket['drops'] > $allowed_drops && $this->bucket['request_time'] < $this->bucket['bucket_flush_time']) {
      $this->overflowing = TRUE;
    }
  }

  /**
   * Saves the bucket information in Drupal's caching system.
   */
  protected function saveBucket() {
    \Drupal::cache(self::RATE_LIMIT_CACHE_BIN)->set($this->cid, $this->bucket, CacheBackendInterface::CACHE_PERMANENT, $this->cacheTag);
  }

  /**
   * Denotes if the bucket is overflowing or not.
   *
   * @return bool
   *   If the current bucket is overflowing or not.
   */
  protected function isOverflowing() {
    return $this->overflowing;
  }

  /**
   * Method returns the whitelisted IPs.
   *
   * @return array
   *   Returns any white listed IPs.
   */
  protected function getWhiteListedIps() {
    return array_map('trim', array_filter(explode(PHP_EOL, unserialize($this->rateLimitingConfig->get('whitelist')))));
  }

  /**
   * Method returns the current bucket.
   *
   * @return array
   *   The current bucket.
   */
  protected function getBucket() {
    if ($cached = \Drupal::cache(self::RATE_LIMIT_CACHE_BIN)->get($this->cid)) {
      return $cached->data;
    }
    return [];
  }

  /**
   * Method scans the Accept header present in Request header.
   *
   * @param \Symfony\Component\HttpFoundation\HeaderBag $header
   *   The request header instance.
   *
   * @return string
   *   The accept header type.
   */
  protected function acceptType(HeaderBag $header) {
    $type = NULL;
    $accept = AcceptHeader::fromString($header->get('Accept'));
    foreach (self::allowedAcceptTypes() as $header => $name) {
      if ($accept->has($header)) {
        $type = $name;
        break;
      }
    }
    return $type;
  }

  /**
   * User defined query string to determine a service request.
   *
   * @return array
   *   Returns an associative array containing the format and value.
   */
  protected function getQueryStringOption() {
    $data = [];
    $selected = $this->rateLimitingConfig->get('override_default_types');
    switch ($selected) {
      case 'none':
        // If nothing selected then we'll use '_format'.
        $data = [
          'key' => '_format',
          'values' => $this->defaultFormat,
        ];
        break;

      case 'query_string':
        // User has selected a custom format.
        // So we have to parse and then return.
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
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request.
   *
   * @return bool
   *   Returns either TRUE or FALSE based on query string match.
   */
  protected function validateOnQueryString(Request $request) {
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

  /**
   * Merthod determines a service request on HTTP header value.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request.
   *
   * @return bool
   *   Returns either TRUE or FALSE based on header match.
   */
  protected function validateOnHeaderTypes(Request $request) {
    $request_headers = $request->headers;
    // Return on 'text/html'.
    $accept = AcceptHeader::fromString($request_headers->get('Accept'));
    if ((string) $accept->filter('/\btext\/\b/')->first() == 'text/html') {
      return FALSE;
    }

    // What option is selected?
    $selectedType = $this->rateLimitingConfig->get('request_header_type');
    if ($selectedType == 'custom') {
      // User selected 'Custom' so only check for this header.
      $header_name = $this->rateLimitingConfig->get('request_header_name');
      $header_value = $this->rateLimitingConfig->get('request_header_value');
      $actual_value = trim($request_headers->get($header_name));
      // Only a single value can be given at this moment.
      if ($header_value == $actual_value) {
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    elseif ($selectedType == 'accept') {
      // Using default Accept header, merge with the default value.
      $header_value = $this->rateLimitingConfig->get('request_header_value');
      $actual_value = trim($request_headers->get('Accept'));
      $headers = array_merge(array_keys(self::allowedAcceptTypes()), [$header_value]);
      if (in_array($actual_value, $headers)) {
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
  }

  /**
   * Determine a service request on HTTP Content type header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Incoming HTTP header.
   *
   * @return bool
   *   Returns TRUE or FALSE based on content-type header.
   */
  protected function validateOnContentTypes(Request $request) {
    $allowed = array_keys(self::allowedAcceptTypes());
    array_pop($allowed);
    $contentType = $request->headers->get('content-type', NULL);
    if (in_array($contentType, $allowed)) {
      return TRUE;
    }
    return FALSE;
  }

}
