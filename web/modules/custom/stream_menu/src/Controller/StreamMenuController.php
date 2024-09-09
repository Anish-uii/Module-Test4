<?php

namespace Drupal\stream_menu\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class used for creating stream menu.
 */
class StreamMenuController extends ControllerBase {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The path alias manager service.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $pathAliasManager;

  /**
   * Constructs a StreamMenuController object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\path_alias\AliasManagerInterface $path_alias_manager
   *   The path alias manager service.
   */
  public function __construct(LoggerInterface $logger, $path_alias_manager) {
    $this->logger = $logger;
    $this->pathAliasManager = $path_alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')->get('stream_menu'),
      $container->get('path_alias.manager')
    );
  }

  /**
   * Redirects user to their stream.
   */
  public function redirectToStream(Request $request) {
    $user_id = $this->currentUser()->id();

    $user = User::load($user_id);

    if ($user) {
      $user_data = $user->toArray();

      $this->logger->info('User entity data: @data', ['@data' => print_r($user_data, TRUE)]);

      $field_stream = $user->get('field_student_stream');

      $stream_term_id = $field_stream->target_id ?? NULL;

      if ($stream_term_id) {
        $term = Term::load($stream_term_id);

        if ($term) {
          $term_name = $term->getName();

          $path = '/taxonomy/term/' . $stream_term_id;
          $stream_url = $this->pathAliasManager->getAliasByPath($path);

          $this->logger->info('Redirecting user to stream URL: @url', ['@url' => $stream_url]);

          return new RedirectResponse($stream_url);
        }
        else {
          $this->logger->warning('Term with ID @id not found.', ['@id' => $stream_term_id]);
        }
      }
      else {
        $this->logger->warning('User does not have a valid stream term ID set.');
      }
    }
    else {
      $this->logger->warning('User entity could not be loaded for user ID @id.', ['@id' => $user_id]);
    }
    return new RedirectResponse('/');
  }

}
