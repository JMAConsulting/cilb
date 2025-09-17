<?php
namespace Civi\Api4;

/**
 * Advimport entity.
 *
 * Provided by the Advanced Import extension.
 *
 * @package Civi\Api4
 */
class Advimport extends Generic\DAOEntity {

  // I'm not terribly familiar with api4, but my understanding is that CiviCRM
  // does default to restricting by contact_id, so users can only see their
  // imports.
  //
  // Users should be able to view their imports, but not create unless they
  // have the "import data" permission. This would work well for use-cases such
  // as using a Custom Search for doing a Bulk Update using advimport (the
  // Custom Search Action will create the advimport for the user, and bypass
  // permissions).
  public static function permissions() {
    return [
      'meta' => ['access CiviCRM'],
      'default' => ['access CiviCRM'],
      'create' => ['import contacts'],
    ];
  }

}
