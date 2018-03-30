<?php

namespace Drupal\Tests\rate_limiter\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Ensure that the Rate Limiting configuration form works properly.
 * 
 * @group rate_limiter
 */
class RateLimiterConfigFormTest extends BrowserTestBase {
  
  /**
   * The modules.
   * 
   * @var array
   */
  protected static $modules = ['node', 'rest', 'serialization', 'rate_limiter'];

  /**
   * The demo user.
   *
   * @var \Drupal\user\UserInterface
   */
  private $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
    ]);
  }

  /**
   * Checks if the configuration page is accessible for the user with the given permission. 
   */
  public function testRateLimitConfigPageVisit() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/services/rate-limiter');
    $this->assertSession()->statusCodeEquals(200);
  }
}