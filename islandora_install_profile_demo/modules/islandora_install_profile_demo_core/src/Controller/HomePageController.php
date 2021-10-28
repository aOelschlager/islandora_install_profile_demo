<?php

namespace Drupal\islandora_install_profile_demo_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class HomePageController.
 */
class HomePageController extends ControllerBase {

  /**
   * Index Controller Main action.
   *
   * @return array
   *   Return empty homepage string.
   */
  public function index(): array {
    return [
      '#theme' => 'homepage',
      '#vars' => []
    ];
  }
}
