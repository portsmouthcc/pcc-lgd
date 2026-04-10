<?php

namespace Drupal\quick_exit_button\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides a 'Quick Exit' Block.
 *
 * @Block(
 *   id = "quick_exit_button_block",
 *   admin_label = @Translation("Quick Exit Block"),
 *   category = @Translation("Custom")
 * )
 */
class QuickExitBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new QuickExitBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the block.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    
    // Obtener la URL de salida desde la configuración.
    $button_title = $config['quick_exit_button_title'] ??  $this->t('Exit');
    $exit_url = $config['quick_exit_url'] ?? 'https://www.google.com';
    $button_classes = "button button--exit " . $config['quick_exit_button_classes'];
  
    return [
      '#type' => 'markup',
      '#markup' => '<a href="' . $exit_url . '" class="' . $button_classes . '" role="button" aria-label="' . $this->t('Exit to a safe page immediately') . '" rel="noopener noreferrer">' . $button_title . '</a>',
    ];
  }
  

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['button_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button title'),
      '#default_value' => $config['quick_exit_button_title'] ?? $this->t('Exit'),
      '#description' => $this->t('Enter the title for your exit button.'),
    ];

    $form['exit_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exit URL'),
      '#default_value' => $config['quick_exit_url'] ?? 'https://www.google.com',
      '#description' => $this->t('Enter the URL to which the quick exit button will redirect users.'),
    ];

    $form['button_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button classes'),
      '#default_value' => $config['quick_exit_button_classes'] ?? "",
      '#description' => $this->t('You can add new custom classes to button.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('quick_exit_button_title', $form_state->getValue('button_title'));
    $this->setConfigurationValue('quick_exit_url', $form_state->getValue('exit_url'));
    $this->setConfigurationValue('quick_exit_button_classes', $form_state->getValue('button_classes'));
  }

}
