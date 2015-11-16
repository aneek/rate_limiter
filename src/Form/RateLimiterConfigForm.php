<?php
/**
 * @file
 * Contains \Drupal\rate_limiter\Form\RateLimiterConfigForm.
 */

namespace Drupal\rate_limiter\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\rate_limiter\RateLimitManager;

/**
 * Rate Limiter configuration form.
 *
 * @package Drupal\rate_limiter\Form
 */
class RateLimiterConfigForm extends ConfigFormBase {

  /**
   * The form id of this form.
   *
   * @var string
   */
  private $formId = 'rate_limiter_configuration';

  /**
   * The Rate Limiter Configuration name.
   *
   * @var string
   */
  private $configName = 'rate_limiter.settings';

  /**
   * Constructs a SiteInformationForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [$this->configName];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->formId;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $rate_limiter_config = $this->config($this->configName);

    $form['basic'] = [
      '#type' => 'details',
      '#title' => $this->t('General Configuration'),
      '#open' => TRUE,
      '#weight' => 1,
    ];

    $form['basic']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Rate limiter'),
      '#description' => $this->t('Checking this will enable rate limiter for each service requests.'),
      '#default_value' => $rate_limiter_config->get('enable'),
    ];

    $form['basic']['requests'] = [
      '#type' => 'number',
      '#title' => $this->t('Allowed Requests'),
      '#description' => $this->t('The number of allowed requests.'),
      '#attributes' => [
        'min' => 1,
        'step' => 1,
      ],
      '#default_value' => $rate_limiter_config->get('requests'),
      '#required' => TRUE,
    ];

    $form['basic']['time_cap'] = [
      '#type' => 'number',
      '#title' => $this->t('Allowed time window'),
      '#description' => $this->t('The number of requests allowed in the given time window.'),
      '#attributes' => [
        'min' => 60,
        'step' => 60,
      ],
      '#field_suffix' => $this->t('<em>In seconds</em>'),
      '#default_value' => $rate_limiter_config->get('time_cap'),
      '#required' => TRUE,
    ];

    $form['basic']['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Response Message'),
      '#description' => $this->t('This message will be shown as response when the limit is reached.'),
      '#maxlength' => 255,
      '#size' => 100,
      '#default_value' => $rate_limiter_config->get('message'),
    ];

    $form['access'] = [
      '#type' => 'details',
      '#title' => $this->t('Access Rules'),
      '#open' => TRUE,
      '#weight' => 2,
    ];

    $form['access']['limiting_rule'] = [
      '#type' => 'radios',
      '#title' => $this->t('Rate limiting rule'),
      '#description' => $this->t('Select whether rate limit all requests or selective requests.'),
      '#options' => RateLimitManager::availableLimitOptions(),
      '#required' => TRUE,
      '#default_value' => $rate_limiter_config->get('limiting_rule'),
    ];

    // Only show the conditional fields when IP based rate limiting is selected.
    $form['access']['ip_configuration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('IP based rate limiting'),
      '#weight' => 1,
      '#states' => [
        'visible' => [
          ':input[name="limiting_rule"]' => array('value' => 1),
        ],
      ],
    ];

    $form['access']['ip_configuration']['whitelist'] = [
      '#type' => 'textarea',
      '#title' => $this->t('IP whitelist'),
      '#description' => $this->t('IP listing for white listing. List IPs in new lines.'),
      '#default_value' => unserialize($rate_limiter_config->get('whitelist')),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config($this->configName)
      ->set('enable', $form_state->getValue('enable'))
      ->set('requests', $form_state->getValue('requests'))
      ->set('time_cap', $form_state->getValue('time_cap'))
      ->set('message', $form_state->getValue('message'))
      ->set('limiting_rule', $form_state->getValue('limiting_rule'))
      ->set('whitelist', serialize($form_state->getValue('whitelist')))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
