<?php

namespace Civi\Api4\Action\Cilb;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * run with cv api4 on the command line
 *
 * e.g.
 * CILB_CLEAN_SLATE_SAFETY=off cv api4 Cilb.cleanSlate
 *
 * This should delete all contacts and participants except those with a
 * uf_match record to a Drupal administrator
 *
 *  */
class CleanSlate extends AbstractAction {

  public function _run(Result $result) {
    if (getenv('CILB_CLEAN_SLATE_SAFETY') !== 'off') {
      throw new \CRM_Core_Exception("This action will destroy your database. It wont run unless you know what you are doing.");
    }

    // work out who to keep
    $adminUserIds = $this->getDrupalAdminUserIds();

    $adminContactIds = \Civi\Api4\UFMatch::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('uf_id', 'IN', $adminUserIds)
      ->execute()
      ->column('contact_id');

    $adminContacts = \Civi\Api4\Contact::get(FALSE)
      ->addWhere('id', 'IN', $adminContactIds)
      ->execute();

    if (!$adminContacts->count()) {
      throw new \CRM_Core_Exception("No Drupal admin user contacts found. Something is probably wrong - stopping before we delete everyone");
    }

    // we have to delete UFMatch records first
    \Civi\Api4\UFMatch::delete(FALSE)
      ->addWhere('contact_id', 'NOT IN', $adminContactIds)
      ->execute();

    // dont delete contacts with FinancialTrxns
   /* $contactsWithLineItems = \Civi\Api4\LineItem::get(FALSE)
      ->addSelect('contribution_id.contact_id')
      ->addGroupBy('contribution_id.contact_id')
      ->execute()
      ->column('contribution_id.contact_id');*/

    // cant delete org 1
    $dontDelete = array_merge([1], $adminContactIds);

    // Delete the participant records first
    \Civi\Api4\Participant::delete(FALSE)
      ->addWhere('contact_id', 'NOT IN', $dontDelete)
      ->execute();

    // bye bye - dont try this at home
    \Civi\Api4\Contact::delete(FALSE)
      ->setUseTrash(FALSE)
      ->addWhere('id', 'NOT IN', $dontDelete)
      ->execute();

    // Delete all activities of type Phone Call, Email, Letter, Application, Note
    \Civi\Api4\Activity::delete(FALSE)
      ->addWhere('activity_type_id:name', 'IN', [
        'Phone Call',
        'Email',
        'Letter',
        'Application',
        'Note',
      ])
      ->execute();

    // TO CHECK: how effective is the cascading delete?

    // now delete Drupal users
    $this->deleteDrupalUsersExcept($adminUserIds);
  }

  protected function deleteDrupalUsersExcept(array $userIdsToKeep) {
    $query = \Drupal::entityQuery('user');
    $query->accessCheck(FALSE);
    $query->condition('uid', $userIdsToKeep, 'NOT IN');
    $toDelete = $query->execute();

    foreach ($toDelete as $id) {
      $user = \Drupal\user\Entity\User::load($id);
      $user->delete();
    }
  }

  protected function getDrupalAdminUserIds(): array {
    $query = \Drupal::entityQuery('user');
    $query->accessCheck(FALSE);
    $query->condition('status', 1)
      ->condition('roles', 'administrator');
    return $query->execute();
  }

}
