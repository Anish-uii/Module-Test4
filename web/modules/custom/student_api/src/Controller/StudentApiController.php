<?php

namespace Drupal\student_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a controller for the Student API.
 */
class StudentApiController extends ControllerBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a StudentApiController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * Exposes the list of students as a JSON response.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing the students.
   */
  public function listStudents() {
    $request = $this->requestStack->getCurrentRequest();
    $params = $request->query->all();

    if (!empty($params['student_stream'])) {
      $stream_name = $params['student_stream'];
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $query = $term_storage->getQuery()
        ->condition('vid', 'stream')
        ->condition('name', $stream_name)
        ->accessCheck(TRUE)
        ->execute();

      if (!empty($query)) {
        $params['student_stream'] = reset($query);
      }
      else {
        $params['student_stream'] = NULL;
      }
    }

    $query = $this->entityTypeManager->getStorage('user')->getQuery();
    $query->accessCheck(TRUE)->condition('status', 1);

    foreach ($params as $key => $value) {
      if ($value === NULL) {
        continue;
      }

      switch ($key) {
        case 'id':
          $query->condition('uid', $value);
          break;

        case 'name':
        case 'username':
          $query->condition('name', '%' . $value . '%', 'LIKE');
          break;

        case 'email':
          $query->condition('mail', '%' . $value . '%', 'LIKE');
          break;

        default:
          if (in_array($key, ['student_stream', 'joining_year', 'passing_year', 'phone_number'])) {
            $field_name = 'field_' . $key;
            $query->condition($field_name, $value);
          }
          break;
      }
    }

    $uids = $query->execute();
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($uids);

    $students = [];
    foreach ($users as $user) {
      if ($user->hasRole('student')) {
        $students[] = [
          'id' => $user->id(),
          'name' => $user->getDisplayName(),
          'username' => $user->getAccountName(),
          'email' => $user->getEmail(),
          'student_stream' => $user->get('field_student_stream')->entity->label() ?? NULL,
          'joining_year' => $user->get('field_joining_year')->value ?? NULL,
          'passing_year' => $user->get('field_passing_year')->value ?? NULL,
          'phone_number' => $user->get('field_phone_number')->value ?? NULL,
        ];
      }
    }

    return new JsonResponse($students);
  }

}
