<?php

namespace Drupal\cilb_admin_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class AdminDashboardController extends ControllerBase {

  public function dashboardPage() {
    $config = $this->config('cilb_admin_dashboard.settings');
    $links_config = $config->get('links') ?: [];

    $links = [];
    foreach ($links_config as $link) {
      if (preg_match('#^(https?://|//)#', $link['url'])) {
        $url = Url::fromUri($link['url']);
      }
      else {
        $path = ltrim($link['url'], '/');
        $url = Url::fromUserInput('/' . $path);
      }
      $links[] = Link::fromTextAndUrl($this->t($link['title']), $url);
    }

    return [
      '#theme' => 'item_list',
      '#items' => $links,
      '#title' => $this->t('Admin Dashboard'),
    ];
  }

}
