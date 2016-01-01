<?php
/**
 * @file
 * Contains \Drupal\rate_limiter\RateLimitManagerInterface.
 */

namespace Drupal\rate_limiter;

use Symfony\Component\HttpFoundation\Request;

/**
 * Provides an interface to RateLimitmanager.
 *
 * @package Drupal\rate_limiter
 */
interface RateLimitManagerInterface {

  /**
   * Method returns the rate limiting options.
   *
   * This method is mainly for Form API. Currently the below are the supported
   * options.
   *   - Enable rate limit for all requests.
   *   - Enable rate limit based on IP address.
   *
   * @return array
   *   An associative array of rate limiting options.s
   */
  public static function availableLimitOptions();

  /**
   * Method checks if the Rate Limiter is enabled or not.
   *
   * @return bool
   *   Returns TRUE if Rate Limiter is enabled or FALSE.
   */
  public function isEnabled();

  /**
   * Method checks if the request is for Service endpoint URL or not.
   *
   * First check if the request is an AJAX request or not. If not AJAX request
   * then if the Request header has "text/html" we can assume that this is a
   * basic drupal page access.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request instance.
   *
   * @return bool
   *   Returns TRUE if this is a service request or FALSE.
   */
  public function isServiceRequest(Request $request);

  /**
   * Method used to limit the service requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request instance.
   *
   *   This method has the following steps to execute.
   *   - Checks which 'limiting_rule' is set.
   *   - Based on the rule, sets the cache id and the tags.
   *   - If IP white listing is enabled then checks if the client IP exists in
   *     the white list array. If present then services are not restricted. Else
   *     the method uses leakey bucket algorithm to rate limit each request.
   *
   *   The Rate limiting is based on 'Leakey Bucket' algorithm.
   *
   *   The bucket has an overflow limit and a flush time. When the flush time is
   *   reached then the bucket becomes empty. Till the bucket is not flushed it
   *   stores each drop of requests. Once the bucket becomes full and overflows
   *   the next request has to wait for a certain time period till the bucket is
   *   empty again.
   *
   * @return bool
   *   Returns if the bucket is full or has capacity.
   */
  public function limit(Request $request);

  /**
   * Method responds if the rate limit is reached.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
   *   The proper response after checking the accept header.
   */
  public function respond();

}
