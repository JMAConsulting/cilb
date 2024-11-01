<?php

namespace Drupal\mask;

/**
 * The mask class.
 */
class Mask {

  /**
   * Return the jQuery Mask CDN Url.
   *
   * @return string
   *   The CDN url.
   */
  public static function getCdnUrl(): string {
    return 'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.15/jquery.mask.min.js';
  }

}
