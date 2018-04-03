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
   * Method initiates all the functional tests.
   */
  public function testFunctional() {
    $this->drupalLogin($this->adminUser);
    $this->doRateLimitConfigPageVisit();
    $this->doRateLimitBasicFormPost();
    $this->doRateLimitQueryStringFieldTest();
    $this->doRateLimitHeaderRuleTest();
  }

  /**
   * Checks if the configuration page is accessible.
   */
  public function doRateLimitConfigPageVisit() {
    $this->drupalGet('/admin/config/services/rate-limiter');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests the basic form validation.
   */
  public function doRateLimitBasicFormPost() {
    // Post the form.
    $edit = [
      'enable' => TRUE,
      'requests' => '',
      'time_cap' => '',
    ];
    $this->drupalPostForm('/admin/config/services/rate-limiter', $edit, 'Save configuration');

    // Test the two form errors.
    $this->assertSession()->pageTextContains('Allowed Requests field is required.');
    $this->assertSession()->pageTextContains('Allowed time window field is required.');
  }

  /**
   * Tests the advanced form - query string field validation.
   */
  public function doRateLimitQueryStringFieldTest() {
    $edit = [
      'override_default_types' => 'query_string',
      'query_string' => '',
    ];
    $this->drupalPostForm('/admin/config/services/rate-limiter', $edit, 'Save configuration');
    $this->assertSession()->pageTextContains('If Query String option is selected, then Query String can\'t be blank.');
  }

  /**
   * Tests the advanced form - query string field validation.
   */
  public function doRateLimitHeaderRuleTest() {
    $edit = [
      'override_default_types' => 'request_header',
      'request_header_type' => 'accept',
      'request_header_value' => '',
    ];
    $this->drupalPostForm('/admin/config/services/rate-limiter', $edit, 'Save configuration');
    $this->assertSession()->pageTextContains('If Request Header Type option is selected, then Request Header Value can\'t be blank.');

    // Check for the other option as well.
    $edit = [
      'override_default_types' => 'request_header',
      'request_header_type' => 'other',
      'request_header_name' => '',
      'request_header_value' => '',
    ];
    $this->drupalPostForm('/admin/config/services/rate-limiter', $edit, 'Save configuration');
    $this->assertSession()->pageTextContains('If Request Header Type option is selected, then Request Header Name can\'t be blank.');
    $this->assertSession()->pageTextContains('If Request Header Type option is selected, then Request Header Value can\'t be blank.');
  }

}
