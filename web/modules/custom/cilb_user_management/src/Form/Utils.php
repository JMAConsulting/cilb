<?php

namespace Drupal\cilb_user_management\Form;

class Utils {

  /**
   * Used in UserActivate form
   */
  public static function getSsnField(): array {
    return [
      '#type' => 'textfield',
      '#title' => t('Social Security Number'),
      '#required' => TRUE,
      // the following is taken from \Drupal\webform\Plugin\WebformElement\TextBase::prepare
      // to match how webform sets input masks
      //
      // note: the prepare implementation also sets an extra validator, but I don't think we
      // need it
      '#attributes' => [
        'data-inputmask-mask' => '999-99-9999',
        'class' => ['js-webform-input-mask'],
      ],
      '#pattern' => '^\d\d\d-\d\d-\d\d\d\d$',
      '#attached' => [
        'library' => ['webform/webform.element.inputmask'],
      ],
      // '#element_validate' => [[get_called_class(), 'validateSsnInputMask']],
    ];
  }

  /**
   * Used in password reset form
   */
  public static function getDobField(): array {
    return [
      '#type' => 'date',
      '#title' => t('Date of Birth'),
      '#required' => TRUE,
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
    ];
  }

}
