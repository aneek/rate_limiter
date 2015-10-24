<?php
/**
 * @file
 * Contains \Drupal\rate_limiter\RateLimitManagerInterface.
 */

namespace Drupal\rate_limiter;

use Symfony\Component\HttpFoundation\HeaderBag;

/**
 * Provides an interface to RateLimitmanager.
 *
 * @package Drupal\rate_limiter
 */
interface RateLimitManagerInterface {

  /**
   * Method checks if the Rate Limiter is enabled or not.
   *
   * @return bool
   *   Returns TRUE if Rate Limiter is enabled or FALSE.
   */
  public function isEnabled();

  /**
   * Method checks if the request from service or simple text/html request.
   *
   * It is assumed that if a URI call has "text/html" in the header then the
   * request is a usual call to the Drupal site. This should not be a service
   * call since the service calls mainly accepts different "Accept" headers.
   *
   * @param \Symfony\Component\HttpFoundation\HeaderBag $header
   *
   * @return bool
   *   Returns TRUE if this is a service request or FALSE.
   */
  public function isServiceRequest(HeaderBag $header);

  /**
   * Method scans the Accept header present in Request header.
   *
   * @param \Symfony\Component\HttpFoundation\HeaderBag $header
   *
   * @return string
   *   The accept header type.
   */
  public function acceptType(HeaderBag $header);

  /**
   * Method returns the Rate limiting Rule.
   *
   * The rule contains below options.
   *   - Rate limit all request.
   *   - Rate limit based on IP address.
   *
   * @return int
   *   The rate limiting rule.
   */
  public function getRateLimitingRule();

  /**
   * This method limits the rate of accessing service endpoints.
   *
   * While limiting the service calls this method follows the below execution
   * steps.
   *   - Determines the rate limiting rule.
   *   - Once the rule is determined then calls the appropriate method to store
   *     rate limiting values.
   *   - If the rate limiting threshold is exceeded then a boolean value is
   *     returned. In case of threshold overflow this returns TRUE else FALSE.
   *   - Checks if the cooling time for next request is reached then resets the
   *     rate limiting counter.
   *
   * @return mixed
   */
  public function limitRequests();
}