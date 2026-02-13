<?php

namespace Drupal\granicus_signup\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Provides a form for Granicus email signup.
 */
class SignupForm extends FormBase {

  /**
   * The config factory.

   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The HTTP client.

   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger factory.

   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new SignupForm object.

   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'granicus_signup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Enter your email address'),
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Subscribe'),
    ];

    $form['#attached']['library'][] = 'granicus_signup/granicus_signup';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');

    // Validate email format
    if (!\Drupal::service('email.validator')->isValid($email)) {
      $form_state->setErrorByName('email', $this->t('Please enter a valid email address.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('granicus_signup.settings');
    $email = $form_state->getValue('email');

    $username = trim($config->get('username'));
    $password = trim($config->get('password'));
    $topic = trim($config->get('default_topic'));

    // Check if credentials are configured
    if (empty($username) || empty($password)) {
      $this->loggerFactory->get('granicus_signup')->error('Granicus credentials are not configured');
      $this->messenger()->addError($this->t('Subscription service is temporarily unavailable.'));
      return;
    }

    $authHeader = 'Basic ' . base64_encode($username . ':' . $password);

    try {
      // Build XML
      $xml = new \SimpleXMLElement('<subscriber/>');
      $xml->addChild('email', $email);

      $topicsNode = $xml->addChild('topics');
      $topicsNode->addAttribute('type', 'array');

      $topicNode = $topicsNode->addChild('topic');
      $topicNode->addChild('code', $topic);

      $xmlString = $xml->asXML();

      // Make API request
      $response = $this->httpClient->post('https://api.govdelivery.com/api/account/UKPORTSMOUTH/subscriptions', [
        'headers' => [
          'Content-Type' => 'application/xml; charset=utf-8',
          'Authorization' => $authHeader,
        ],
        'body' => $xmlString,
        'timeout' => 15,
      ]);

      $status_code = $response->getStatusCode();

      if ($status_code == 200 || $status_code == 201) {
        $this->messenger()->addMessage($this->t('Thank you for subscribing!'));
        $this->loggerFactory->get('granicus_signup')->notice('Successfully subscribed email: @email', ['@email' => $email]);
      }
      else {
        $this->messenger()->addError($this->t('There was a problem with your subscription.'));
        $this->loggerFactory->get('granicus_signup')->error('API returned status code: @code for email: @email', [
          '@code' => $status_code,
          '@email' => $email,
        ]);
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while processing your request.'));
      $this->loggerFactory->get('granicus_signup')->error('Exception: @message', ['@message' => $e->getMessage()]);
    }

    $form_state->setRebuild(TRUE);
  }

}