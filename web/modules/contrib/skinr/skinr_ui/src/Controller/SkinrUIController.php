<?php

namespace Drupal\skinr_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\skinr\SkinInterface;

/**
 * Returns responses for devel module routes.
 */
class SkinrUIController extends ControllerBase {

  /**
   *
   */
  public function library($theme = NULL) {
    $theme = $theme ?: $this->config('system.theme')->get('default');
    return \Drupal::formBuilder()->getForm('Drupal\skinr_ui\Form\LibraryListForm', $theme);
  }

  /**
   * Performs an operation on the skin entity.
   *
   * @param \Drupal\skinr\SkinInterface $skin
   *   The skin entity.
   * @param string $op
   *   The operation to perform, usually 'enable' or 'disable'.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect back to the skin listing page.
   */
  public function performOperation(SkinInterface $skin, $op) {
    $skin->$op()->save();

    if ($op == 'enable') {
      $this->messenger()->addStatus($this->t('Skin %label has been enabled.', ['%label' => $skin->label()]));
    }
    elseif ($op == 'disable') {
      $this->messenger()->addStatus($this->t('Skin %label has been disabled.', ['%label' => $skin->label()]));
    }

    return $this->redirect('skinr_ui.list');
  }

}
