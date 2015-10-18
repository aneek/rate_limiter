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
    $form['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Rate limiter'),
      '#description' => $this->t('Checking this will enable rate limiter for each service requests.'),
      '#default_value' => $rate_limiter_config->get('enable'),
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
      ->save();
    parent::submitForm($form, $form_state);
  }
}