<?php

namespace Drupal\quick_exit_button\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure quick exit settings for this site.
 */
class QuickExitSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['quick_exit.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quick_exit_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('quick_exit.settings');

    $form['exit_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exit URL'),
      '#default_value' => $config->get('exit_url'),
      '#description' => $this->t('Specify the URL where users will be redirected when they click the quick exit button.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('quick_exit.settings')
      ->set('exit_url', $form_state->getValue('exit_url'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
