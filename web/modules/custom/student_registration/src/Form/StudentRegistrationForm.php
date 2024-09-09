<?php

namespace Drupal\student_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Author Registration form.
 */
class StudentRegistrationForm extends FormBase {

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs an AuthorRegistrationForm object.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MailManagerInterface $mail_manager, MessengerInterface $messenger) {
    $this->mailManager = $mail_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'student_registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('Stream');
    $streamOptions = [];

    foreach ($terms as $term) {
      $streamOptions[$term->tid] = $term->name;
    }
    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];

    $form['phone_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mobile Number'),
      '#required' => TRUE,
    ];

    $form['stream'] = [
      '#type' => 'select',
      '#title' => $this->t('Stream'),
      '#options' => $streamOptions,
      '#required' => TRUE,
    ];

    $form['joining_year'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Joining Year'),
      '#required' => TRUE,
      '#min' => 2000,
      '#max' => 2024,
    ];

    $form['passing_year'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Passing Year'),
      '#required' => TRUE,
      '#min' => 2004,
      '#max' => 2030,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (strlen($values['phone_number']) != 10) {
      $form_state->setErrorByName('phone_number', $this->t('Phone Number must be of 10 digits only.'));
    }

    $joining_year = (int) $values['joining_year'];
    $passing_year = (int) $values['passing_year'];

    if ($passing_year > $joining_year + 7) {
      $form_state->setErrorByName('passing_year', $this->t('The passing year should be less than 7 years.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = User::create([
      'name' => $form_state->getValue('full_name'),
      'mail' => $form_state->getValue('email'),
      'pass' => $form_state->getValue('password'),
      'status' => 0,
      'field_phone_number' => $form_state->getValue('phone_number'),
      'field_stream' => $form_state->getValue('stream'),
      'field_joining_year' => $form_state->getValue('joining_year'),
      'field_passing_year' => $form_state->getValue('passing_year'),
      'roles' => ['student'],
    ]);
    $user->save();

    $admin_email = \Drupal::config('system.site')->get('mail');
    $this->sendUserNotification($form_state->getValue('email'), $user->id(), $form_state->getValues());
    $this->sendAdminNotification($admin_email, $form_state->getValues());

    $this->messenger->addMessage($this->t('Thank you! Please check your mail for further instructions. Your account will be verified shortly.'));

  }

  /**
   * Sends notification to the user.
   *
   * @param string $email
   *   The user email address.
   * @param int $user_id
   *   The user ID.
   * @param array $user_data
   *   The user data.
   */
  protected function sendUserNotification($email, $user_id, array $user_data) {
    $module = 'student_registration';
    $key = 'user_notification';
    $params = ['user_id' => $user_id, 'user_data' => $user_data];
    $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();

    $this->mailManager->mail($module, $key, $email, $langcode, $params);
  }

  /**
   * Sends notification to the admin.
   *
   * @param string $email
   *   The admin email address.
   * @param array $user_data
   *   The user data.
   */
  protected function sendAdminNotification($email, array $user_data) {
    $module = 'student_registration';
    $key = 'admin_notification';
    $params = ['user_data' => $user_data];
    $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();

    $this->mailManager->mail($module, $key, $email, $langcode, $params);
  }

}
