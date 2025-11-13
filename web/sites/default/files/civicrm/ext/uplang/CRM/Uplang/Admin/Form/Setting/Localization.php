<?php

use CRM_Uplang_ExtensionUtil as E;

class CRM_Uplang_Admin_Form_Setting_Localization {

  /**
   * @see uplang_civicrm_buildForm().
   */
  public static function buildForm(&$form) {
    // Replace the drop-down list of locales with all possible locales
    if ($form->getElement('lcMessages')) {
      // Mostly copied from CRM_Admin_Form_Setting_Localization::buildQuickForm()
      $locales = CRM_Contact_BAO_Contact::buildOptions('preferred_language');
      $domain = new CRM_Core_DAO_Domain();
      $domain->find(TRUE);

      // Populate default language drop-down with available languages
      $lcMessages = [];

      // If multilingual, hide those locales from the list (array_diff)
      if ($domain->locales) {
        foreach ($locales as $loc => $lang) {
          if (substr_count($domain->locales, $loc)) {
            $lcMessages[$loc] = $lang;
          }
        }
      }

      $form->addElement('select', 'lcMessages', E::ts('Default Language'), $locales);
      $form->addElement('select', 'addLanguage', E::ts('Add Language'), array_merge(['' => E::ts('- select -')], array_diff($locales, $lcMessages)));

      // This replaces the uiLanguages select element with one which has all available languages even if they are not already downloaded.
      // If you enable a language this extension will download it.
      $uiLanguagesSetting = \Civi\Core\SettingsMetadata::getMetadata(['name' => ['uiLanguages']], NULL, TRUE)['uiLanguages'];
      $uiLanguagesSetting['options'] = $locales;
      $uiLanguagesSetting['html_attributes']['class'] = $uiLanguagesSetting['html_attributes']['class'] . ' big';
      $form->add($uiLanguagesSetting['html_type'], $uiLanguagesSetting['name'], $uiLanguagesSetting['title'], $uiLanguagesSetting['options'], $uiLanguagesSetting['is_required'] ?? FALSE, $uiLanguagesSetting['html_attributes']);
    }

    self::addRefreshButton('form-top', $form);
  }

  public static function addRefreshButton($region, &$page) {
    try {
      $mtime = CRM_Uplang_Utils::getLastUpdateTime();
      $status = E::ts('Last Update: %1', [1 => date('Y-m-d H:i', $mtime)]);
      $page->assign('uplangStatus', $status);
    }
    catch (Exception $e) {
      $page->assign('uplangStatus', E::ts('Error: %1', [1 => $e->getMessage()]));
    }

    CRM_Core_Region::instance($region)->add([
      'template' => 'CRM/Uplang/Form/Refresh.tpl',
    ]);

    Civi::resources()->addScriptFile('uplang', 'js/uplang.js');
  }

}
