<?php

namespace Drupal\granicus_signup\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Granicus settings.
 */
class GranicusSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['granicus_signup.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'granicus_signup_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('granicus_signup.settings');

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username/Account ID'),
      '#default_value' => $config->get('username'),
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#default_value' => $config->get('password'),
      '#required' => TRUE,
      '#description' => $this->t('Leave empty to keep current password'),
    ];

    $form['default_topic'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Topic Code'),
      '#default_value' => $config->get('default_topic'),
      '#required' => TRUE,
      '#description' => $this->t('The default topic code for subscriptions'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('granicus_signup.settings');

    $config->set('username', $form_state->getValue('username'));

    // Only update password if provided
    $password = $form_state->getValue('password');
    if (!empty($password)) {
      $config->set('password', $password);
    }

    $config->set('default_topic', $form_state->getValue('default_topic'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}