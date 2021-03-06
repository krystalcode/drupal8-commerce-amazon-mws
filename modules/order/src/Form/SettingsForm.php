<?php

namespace Drupal\commerce_amws_order\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

/**
 * Amazon MWS order settings form.
 */
class SettingsForm extends ConfigFormBase {

  const BILLING_PROFILE_SOURCE_SHIPPING_INFORMATION = 'shipping_information';
  const BILLING_PROFILE_SOURCE_CUSTOM = 'custom';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_amws_order_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['commerce_amws_order.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_amws_order.settings');

    // General settings.
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General import settings'),
      '#open' => TRUE,
    ];
    $form['general']['general_address_convert_states'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Convert US states to their 2-digit codes'),
      '#description' => $this->t('Normally, the state in USA shipping addresses is provided by Amazon MWS with its 2-digit code. However, in certain cases the full state name is given instead e.g. NEW MEXICO instead of NM. By default, addresses will be kept as they come through. When this option is check, full state names will be converted to their 2-digit codes before storing them on the website. Conversion of administrative areas in the USA only are supported at the moment.'),
      '#default_value' => $config->get('general.address_convert_states'),
    ];

    // Billing profile.
    $form['billing_profile'] = [
      '#type' => 'details',
      '#title' => $this->t('Billing profile'),
      '#open' => TRUE,
    ];
    $form['billing_profile']['billing_profile_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add a billing profile to the order'),
      '#default_value' => $config->get('billing_profile.status'),
    ];
    $form['billing_profile']['billing_profile_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('Billing profile source'),
      '#description' => $this->t("Amazon MWS does not provide detailed billing information for its orders. You can choose to use the available shipping information for creating a billing profile, or manually enter billing information that will be used for the billing profiles of all orders. The latter might be useful if you want to add Amazon's details as the billing information for all orders for accountancy purposes."),
      '#default_value' => $config->get('billing_profile.source'),
      '#options' => [
        'shipping_information' => $this->t('Shipping information'),
        'custom' => $this->t('Custom information'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="billing_profile_status"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['billing_profile']['billing_profile_custom_address'] = [
      '#type' => 'address',
      '#default_value' => $config->get('billing_profile.custom_address'),
      '#states' => [
        'visible' => [
          ':input[name="billing_profile_source"]' => ['value' => self::BILLING_PROFILE_SOURCE_CUSTOM],
        ],
      ],
    ];

    // Cron settings.
    $form['cron'] = [
      '#type' => 'details',
      '#title' => $this->t('Cron'),
    ];
    $form['cron']['cron_status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable importing orders during cron'),
      '#default_value' => $config->get('cron.status'),
    ];
    $form['cron']['cron_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit number of orders to import'),
      '#description' => $this->t('You may limit the number of orders that will be imported during each cron run. Leave empty to import all orders provided by Amazon MWS.'),
      '#default_value' => $config->get('cron.limit'),
      '#states' => [
        'visible' => [
          ':input[name="cron_status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $cron_limit = $form_state->getValue('cron_limit');
    if (!ctype_digit($cron_limit) && !empty($cron_limit)) {
      $form_state->setErrorByName(
        'cron_limit',
        $this->t('The limit of orders to import must be a positive integer number or empty.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_amws_order.settings');

    // General import settings.
    $convert_states = $form_state->getValue('general_address_convert_states');
    $config->set('general.address_convert_states', $convert_states);

    // Billing profile settings.
    $profile_status = $form_state->getValue('billing_profile_status');

    // Unset source if billing profile is disabled.
    $profile_source = NULL;
    if ($profile_status) {
      $profile_source = $form_state->getValue('billing_profile_source');
    }

    // Custom billing information.
    $profile_custom_address = NULL;
    if ($profile_source === self::BILLING_PROFILE_SOURCE_CUSTOM) {
      $profile_custom_address = $form_state->getValue('billing_profile_custom_address');
    }

    $config->set('billing_profile.status', $profile_status)
      ->set('billing_profile.source', $profile_source)
      ->set('billing_profile.custom_address', $profile_custom_address);

    // Cron settings.
    $cron_status = $form_state->getValue('cron_status');

    // Unset number limit if cron is disabled.
    // Also, empty string or 0 might be submitted via the form which would still
    // mean to import all orders. Normalize all empty values to NULL.
    $cron_limit = $form_state->getValue('cron_limit');
    if (!$cron_status || empty($cron_limit)) {
      $cron_limit = NULL;
    }

    $config->set('cron.status', $cron_status)
      ->set('cron.limit', $cron_limit);

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
