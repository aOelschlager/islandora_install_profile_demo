<?php

namespace Drupal\islandora_install_profile_demo_core\EventSubscriber;

use Drupal\Core\Url;
use Drupal\islandora\IslandoraUtils;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class RouteSubscriber.
 */
class CompoundRedirectSubscriber implements EventSubscriberInterface {

  protected $utils;

  /**
   * Constructs a new RouteSubscriber object.
   */
  public function __construct(IslandoraUtils $utils) {
    $this->utils = $utils;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['kernel.request'] = ['kernelRequest'];

    return $events;
  }

  /**
   * This method is called when the kernel.request is dispatched.
   * Redirects to the first child when a compound object is viewed.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function kernelRequest(Event $event) {
    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    if ($route_name != 'entity.node.canonical') {
      return;
    }

    $node = \Drupal::routeMatch()->getParameter('node');
    if (!$node || $node->bundle() != 'islandora_object') {
      return;
    }

    $terms = $node->get('field_model')->referencedEntities();
    if (empty($terms)) {
      return;
    }

    $term = reset($terms);
    $uri = $this->utils->getUriForTerm($term);
    if (empty($uri) || $uri != "http://vocab.getty.edu/aat/300242735") {
      return;
    }
    
    $members = views_get_view_result('reorder_children', 'page_1', $node->id());
    if (empty($members)) {
      return;
    }

    $member = reset($members);
    $response = new RedirectResponse(Url::fromRoute('entity.node.canonical', ['node' => $member->nid])->toString());
    $event->setResponse($response);
  }

}
