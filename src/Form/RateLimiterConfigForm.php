<?php

namespace Drupal\rate_limiter\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\rate_limiter\RateLimitManager;

/**
 * Rate Limiter configuration form.
 *
 * @package rate_limiter
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
          ':input[name="limiting_rule"]' => ['value' => 1],
        ],
      ],
    ];
    $form['access']['ip_configuration']['whitelist'] = [
      '#type' => 'textarea',
      '#title' => $this->t('IP whitelist'),
      '#description' => $this->t('IP listing for white listing. List IPs in new lines.'),
      '#default_value' => unserialize($rate_limiter_config->get('whitelist')),
    ];

    $form['advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
      '#weight' => 3,
    ];
    $form['advanced_settings']['override_default_types'] = [
      '#type' => 'select',
      '#title' => $this->t('Override API Service Call determination rule'),
      '#description' => $this->t("This module checks for URL Query string %qs or Accept Headers like %ach in HTTP Request to determine whether this is an API related request or nornal Drupal's call. However, this rule can be modified by checking this option. This will merge the existing rules with the new custom added rules.", [
        '%qs' => '_format=hal_json',
        '%ach' => implode(', ', array_keys(RateLimitManager::allowedAcceptTypes())),
      ]),
      '#options' => [
        'none' => $this->t('Use Default'),
        'query_string' => $this->t('New Query String Rule'),
        'request_header' => $this->t('New Request Header Rule'),
      ],
      '#default_value' => $rate_limiter_config->get('override_default_types'),
    ];
    $form['advanced_settings']['query_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Query String'),
      '#description' => $this->t('Mention any query string as a key=value format'),
      '#placeholder' => $this->t('_format=hal_json'),
      '#default_value' => $rate_limiter_config->get('query_string'),
      '#states' => [
        'visible' => [
          ':input[name="override_default_types"]' => ['value' => 'query_string'],
        ],
      ],
    ];

    $form['advanced_settings']['request_header_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Request Header Type'),
      '#description' => $this->t("The HTTP Request header's type"),
      '#options' => [
        'accept' => $this->t('Accept'),
        'custom' => $this->t('Custom'),
      ],
      '#default_value' => $rate_limiter_config->get('request_header_type'),
      '#states' => [
        'visible' => [
          ':input[name="override_default_types"]' => ['value' => 'request_header'],
        ],
      ],
    ];

    $form['advanced_settings']['request_header_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request Header Name'),
      '#description' => $this->t("The HTTP Request header's name"),
      '#default_value' => $rate_limiter_config->get('request_header_name'),
      '#placeholder' => 'Foobar',
      '#states' => [
        'visible' => [
          ':input[name="override_default_types"]' => ['value' => 'request_header'],
          ':input[name="request_header_type"]' => ['value' => 'custom'],
        ],
      ],
    ];

    $form['advanced_settings']['request_header_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request Header Value'),
      '#description' => $this->t("The HTTP Request header's value to check for"),
      '#default_value' => $rate_limiter_config->get('request_header_value'),
      '#placeholder' => 'application/json',
      '#states' => [
        'visible' => [
          ':input[name="override_default_types"]' => ['value' => 'request_header'],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // The IP should be valid.
    if (!empty($ip_lists = $form_state->getValue('whitelist'))) {
      // Make the list as an array.
      $ip_array = explode(PHP_EOL, $ip_lists);
      // Trim each IP element.
      $ip_array = array_map('trim', $ip_array);

      // Iterate each IP and check if it's valid.
      foreach ($ip_array as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
          $invalid_ip = $ip;
          break;
        }
      }
      if (isset($invalid_ip)) {
        $form_state->setErrorByName('whitelist', $this->t('The IP @ip is not valid. Only mention a single IP address on each line.', ['@ip' => $invalid_ip]));
      }
    }

    // If API Service Call determination rule added, then there should be
    // a validation.
    $override_settings = $form_state->getValue('override_default_types');
    switch ($override_settings) {
      case 'query_string':
        // Query string validation is simple, only check for
        // 'Query String' field.
        if (empty(trim($form_state->getValue('query_string')))) {
          $form_state->setErrorByName('query_string', $this->t("If Query String option is selected, then Query String can't be blank."));
        }
        break;

      case 'request_header':
        // Two options are available here. If default 'Accept'
        // header is there, then validate single box else validate two.
        $request_header_type = $form_state->getValue('request_header_type');
        if ($request_header_type != 'accept') {
          if (empty(trim($form_state->getValue('request_header_name')))) {
            $form_state->setErrorByName('request_header_name', $this->t("If Request Header Type option is selected, then Request Header Name can't be blank."));
          }
        }
        if (empty(trim($form_state->getValue('request_header_value')))) {
          $form_state->setErrorByName('request_header_value', $this->t("If Request Header Type option is selected, then Request Header Value can't be blank."));
        }
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the previous value, and if that was set, and now it's 0
    // then reset all fields.
    $was_set = $this->config($this->configName)->get('enable');
    $new_value = $form_state->getValue('enable');
    if ($was_set == 1 && $new_value == 0) {
      // Trying to remove all settings.
      // Update all to default values.
      $this->config($this->configName)
        ->set('enable', 0)
        ->set('requests', 10)
        ->set('time_cap', 3600)
        ->set('message', '')
        ->set('limiting_rule', 0)
        ->set('whitelist', '')
        ->set('storage_option', 'cache')
        ->set('override_default_types', 'none')
        ->set('query_string', '')
        ->set('request_header_type', 'accept')
        ->set('request_header_name', '')
        ->set('request_header_value', '')
        ->save();
      parent::submitForm($form, $form_state);
    }
    else {
      // General save.
      $this->config($this->configName)
        ->set('enable', $form_state->getValue('enable'))
        ->set('requests', $form_state->getValue('requests'))
        ->set('time_cap', $form_state->getValue('time_cap'))
        ->set('message', $form_state->getValue('message'))
        ->set('limiting_rule', $form_state->getValue('limiting_rule'))
        ->set('whitelist', serialize($form_state->getValue('whitelist')))
        ->set('storage_option', 'cache')
        ->set('override_default_types', $form_state->getValue('override_default_types'))
        ->set('query_string', $form_state->getValue('query_string'))
        ->set('request_header_type', $form_state->getValue('request_header_type'))
        ->set('request_header_name', $form_state->getValue('request_header_name'))
        ->set('request_header_value', $form_state->getValue('request_header_value'))
        ->save();
      parent::submitForm($form, $form_state);
    }
  }

}
