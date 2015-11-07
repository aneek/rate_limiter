<?php
/**
 * @file
 * Contains \Drupal\rate_limiter\RateLimitMiddleware.
 */

namespace Drupal\rate_limiter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Rate Limiter Middleware.
 *
 * @package Drupal\rate_limiter
 */
class RateLimitMiddleware implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $app;

  /**
   * Rate Limiter manager instance.
   *
   * @var \Drupal\rate_limiter\RateLimitManagerInterface
   */
  protected $manager;

  /**
   * Constructs Rate Limiter Middleware.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $app
   *   The wrapper HTTP kernel
   * @param \Drupal\rate_limiter\RateLimitManagerInterface $manager
   *   The rate limiter manager interface
   */
  public function __construct(HttpKernelInterface $app, RateLimitManagerInterface $manager) {
    $this->app = $app;
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    $enabled = $this->manager->isEnabled();
    // Only run the rate limiter if it's enabled.
    if ($enabled === TRUE) {
      $service_request = $this->manager->isServiceRequest($request);
      // If this is a Web Service request then run the rate limiter service.
      if ($service_request === TRUE) {
        // Enable the Rate limiting service.
        //throw new HttpException(404, 'AAAAAA');
      }
    }
    return $this->app->handle($request, $type, $catch);
  }

  /**
   * @param string $type
   * @param string $message
   * @param int $status
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
   */
  protected function respond($type = 'json', $message = '', $status = 429) {
    if (in_array($type, ['json', 'hal_json'])) {
      return new JsonResponse(['message' => $message], $status);
    }
    else {
      return new Response($message, $status);
    }
  }
}