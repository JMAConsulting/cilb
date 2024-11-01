<?php

namespace Drupal\cilb_user_management\Form;

class Utils {

  /**
   * Used in UserActivate and password reset form
   */
  public static function getSsnField(): array {
    return [
      '#type' => 'textfield',
      '#title' => t('Social Security Number'),
      '#attributes' => [
        'placeholder' => '###-##-####',
      ],
    ];
  }

}