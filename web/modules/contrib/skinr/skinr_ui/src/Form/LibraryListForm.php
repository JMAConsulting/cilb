<?php

namespace Drupal\skinr_ui\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;

/**
 * Provides skinr plugin installation interface.
 */
class LibraryListForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'skinr_ui_library';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Would you like to disable all skin configurations for the selected skins?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('skinr_ui.library');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Yes');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return t('No');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $theme = NULL) {
    $form['edited_theme'] = [
      '#type' => 'value',
      '#value' => $theme,
    ];

    $skin_infos = skinr_get_skin_info();
    if (empty($skin_infos)) {
      $form['skins_empty'] = [
        '#markup' => t("You don't have any skins to manage."),
      ];
      return $form;
    }

    // Confirmation form.
    $storage = &$form_state->getStorage();
    if (!empty($storage['sids'])) {
      $form['sids'] = [
        '#theme' => 'item_list',
        '#items' => array_map(function ($skin) {
          return $skin['title'];
        }, array_intersect_key($skin_infos, array_flip($storage['skin_infos']))),
      ];
      // @todo Test this works properly.
      return parent::buildForm($form, $form_state);
    }

    // Apply overridden status.
    foreach ($skin_infos as $name => $skin_info) {
      $skin_infos[$name]['status'] = skinr_skin_info_status_get($skin_info);
    }

    $groups = skinr_get_group_info();

    uasort($skin_infos, ['\Drupal\Component\Utility\SortArray', 'sortByTitleElement']);
    $form['skin_infos'] = ['#tree' => TRUE];

    // Iterate through each of the skin_infos.
    foreach ($skin_infos as $name => $skin_info) {
      $group = (string) $groups[$skin_info['group']]['title'];
      $form['skin_infos'][$group][$name] = $this->buildRow($skin_info, $theme);
    }

    // Add basic information to the fieldsets.
    foreach (Element::children($form['skin_infos']) as $package) {
      $form['skin_infos'][$package] += [
        '#type' => 'details',
        '#title' => $this->t($package),
        '#open' => TRUE,
        '#theme' => 'skinr_ui_library_details',
        '#attributes' => ['class' => ['package-listing']],
      ];
    }

    $form['#attached']['library'][] = 'skinr_ui/admin.styling';
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save configuration'),
    ];
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => t('Reset to defaults'),
    ];

    return $form;
  }

  /**
   * Build a table row for the skin info listing page.
   *
   * @param array $skin_info
   *   The list of existing skin info.
   * @param $extra
   * @param $theme
   *
   * @return array
   *   The form row for the given module.
   */
  protected function buildRow($skin_info, $theme) {
    // Grab source info.
    $info = \Drupal::service('extension.list.' . $skin_info['source']['type'])->getExtensionInfo($skin_info['source']['name']);
    $source = !empty($info['name']) ? $info['name'] : $skin_info['source']['name'];

    // Set the basic properties.
    $row['name']['#markup'] = $skin_info['title'];
    $row['description']['#markup'] = $skin_info['description'];
    $row['source']['#markup'] = $this->t('%source !type', ['%source' => $source, '!type' => $skin_info['source']['type'] == 'module' ? t('module') : t('theme')]);
    $row['version']['#markup'] = $skin_info['source']['version'];

    $theme_hooks = [];
    foreach ($skin_info['theme hooks'] as $theme_hook) {
      $theme_hooks[] = $theme_hook == '*' ? $this->t('all hooks') : $theme_hook;
    }
    $row['#theme_hooks'] = $theme_hooks;

    // Present a checkbox for enabling and indicating the status of a module.
    $status = !empty($skin_info['status'][$theme]) ? $skin_info['status'][$theme] : 0;
    $row['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => (bool) $status,
      '#disabled' => (bool) $status,
    ];

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $skin_infos = skinr_get_skin_info();
    $theme = $form_state->getValue('edited_theme');
    $theme_info = \Drupal::service('extension.list.theme_engine')->getExtensionInfo($theme);

    $triggering_element = &$form_state->getTriggeringElement();
    $reset = $triggering_element['#id'] == 'edit-reset' ? TRUE : FALSE;

    if ($reset) {
      // Reset all values to their default.
      foreach ($form_state->getValue('skin_infos') as $category => $data) {
        foreach ($data as $skin => $enabled) {
          $default_status = $skin_infos[$skin]['status'][$theme] ?? $skin_infos[$skin]['default status'];
          $form_state->setValue(['skin_infos', $category, $skin, 'enable'], $default_status);
        }
      }
    }

    if ($triggering_element['#id'] == 'edit-submit' || $reset) {
      // Make sure we don't disable skins for which configuration exists. Ask to
      // disable all related skin configurations so we can disable the skin.
      $affected_skins = [];
      $disable_sids = [];
      $rebuild = FALSE;

      foreach ($form_state->getValue('skin_infos') as $category => $data) {
        foreach ($data as $skin => $enabled) {
          $enabled = $enabled['enable'];
          $status = skinr_skin_info_status_get($skin_infos[$skin]);

          if (!empty($status[$theme]) && !$enabled) {
            // This skin is being disabled.
            $affected_skins[] = $skin;

            // Find all enabled configurations for this skin.
            // @todo
            $params = [
              'theme' => $theme,
              'skin' => $skin,
              'status' => 1,
            ];
            $sids = skinr_skin_get_sids($params);
            if (count($sids)) {
              $disable_sids += $sids;
              $rebuild = TRUE;
            }
          }
        }
      }

      if ($rebuild) {
        $storage = [
          'status' => $form_state->getValue('skin_infos'),
          'skin_infos' => $affected_skins,
          'sids' => $disable_sids,
          'reset' => $reset,
        ];
        $form_state->setStorage($storage);
        $form_state->setRebuild();
        $this->messenger()->addStatus(t('Rebuilding skins.'));
        return;
      }
    }

    $storage = &$form_state->getStorage();
    $changed_status = [];
    if (!empty($storage['sids'])) {
      // Disable any configurations for skins that are being disabled.
      \Drupal::database()->update('skinr_skins')
        ->fields(['status' => 0])
        ->condition('sid', $storage['sids'])
        ->execute();

      // Clear skinr_skin_load_multiple cache.
      drupal_static_reset('skinr_skin_load_multiple');

      foreach ($storage['skins'] as $skin) {
        $this->messenger()->addStatus(t('Disabled all skin configurations for skin %skin and theme %theme.', ['%skin' => $skin, '%theme' => $theme_info['name']]));
      }
      $changed_status = $storage['status'];
      $reset = $storage['reset'];
    }
    else {
      $changed_status = $form_state->getValue('skin_infos');
    }

    // Save new status.
    foreach ($changed_status as $category => $data) {
      foreach ($data as $skin => $enabled) {
        $enabled = $enabled['enable'];
        $status = skinr_skin_info_status_get($skin_infos[$skin]);
        if (!isset($status[$theme]) || $status[$theme] != $enabled) {
          // Update status.
          $status[$theme] = $enabled;
          skinr_skin_info_status_set($skin_infos[$skin], $status);
        }
      }
    }

    if ($reset) {
      $this->messenger()->addStatus(t("Statuses for %theme's skins have been reset to their defaults.", ['%theme' => $theme_info['name']]));
    }
    else {
      $this->messenger()->addStatus(t("Statuses for %theme's skins have been updated.", ['%theme' => $theme_info['name']]));
    }
  }

}
