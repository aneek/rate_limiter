<?php

/**
 * @file
 * Contains \Drupal\rate_limiter\Tests\RateLimiterTest.
 */

namespace Drupal\rate_limiter\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Integration test class for the Rate Limiter module.
 *
 * @group rate_limiter
 */
class RateLimiterTest extends WebTestBase {

  /**
   * The necessary modules.
   *
   * @var array
   */
  public static $modules = ['node', 'rest', 'serialization', 'rate_limiter'];

  /**
   * Exempt from strict schema checking.
   *
   * @see \Drupal\Core\Config\Testing\ConfigSchemaChecker
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The admin user to access the configuration page.
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
   * Method tests the rate limiter admin configuration page.
   */
  public function testRateLimiterAdmin() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/services/rate-limiter');

    // Basic Form validation test.
    $edit = [];
    $edit['enable'] = TRUE;
    $edit['requests'] = '';
    $edit['time_cap'] = '';
    $this->drupalPostForm('admin/config/services/rate-limiter', $edit, t('Save configuration'));

    $this->assertText(t('Allowed Requests field is required.'), t('Allowed Requests field is validated'));
    $this->assertText(t('Allowed time window field is required.'), t('Allowed time window field is validated'));
  }

}
