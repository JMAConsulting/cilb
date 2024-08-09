<?php
use CRM_DeleteInactiveUsers_ExtensionUtil as E;
use Drupal\user\Entity\User;
use Drupal\Core\Database\Database;
use Civi\Api4\Contact;

/**
 * Job.DeleteInactiveUsers API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_job_delete_inactive_users_spec(&$spec) {}

/**
 * Job.DeleteInactiveUsers API
 * Implements a scheduled job to delete unconfirmed and inactive user accounts.
 * 
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_job_delete_inactive_users($params) {
  try {
    // Get the current date and subtract 7 days for unconfirmed emails
    $sevenDaysAgo = strtotime('-7 days');

    // Subtract 30 days for accounts that haven't had any registrations
    $thirtyDaysAgo = strtotime('-30 days');

    $connection = Database::getConnection();

    // Ensure connection is valid
    if (!($connection instanceof \Drupal\Core\Database\Connection)) {
      throw new \Exception('Database connection is not an instance of Drupal\Core\Database\Connection.');
    }

    // Query to find candidates with unconfirmed emails older than 7 days
    $query = $connection->select('user_email_verification', 'u');
    $query->fields('u', ['uid']);
    $query->join('users_field_data', 'ud', 'u.uid = ud.uid');
    $query->join('user__roles', 'ur', 'u.uid = ur.entity_id');

    $query->condition('ud.created', $sevenDaysAgo, '<')
          ->condition('u.verified', 0)
          ->condition('ur.roles_target_id', 'administrator', '!=');

    // Execute the query and fetch the results
    $result = $query->execute();
    $unconfirmedAccounts = $result->fetchCol();

    // Get matching Civi IDs
    $unconfirmedContacts = Contact::get(TRUE)
      ->addSelect('id', 'uf_match.uf_id')
      ->addJoin('UFMatch AS uf_match', 'INNER', ['uf_match.uf_id', 'IN', $unconfirmedAccounts])
      ->execute();

    $unconfirmedCiviIDs = [];

    foreach ($unconfirmedContacts as $contact) {
      $unconfirmedCiviIDs[] = $contact['id'];
    }

    // Get candidates older than 30 days without any registrations
    $unregisteredContacts = Contact::get(TRUE)
      ->addSelect('uf_match.uf_id', 'participant.event_id', 'display_name')
      ->addJoin('UFMatch AS uf_match', 'INNER', ['id', '=', 'uf_match.contact_id'])
      ->addJoin('Participant AS participant', 'EXCLUDE', ['participant.contact_id', '=', 'id'])
      ->addWhere('created_date', '>', $thirtyDaysAgo)
      ->addWhere('contact_sub_type', '=', 'Candidate')
      ->execute();

    $unregisteredAccounts = [];
    $unregisteredCiviIDs = [];
  
    foreach ($unregisteredContacts as $contact) {
      $unregisteredAccounts[] = $contact['uf_match.uf_id'];
      $unregisteredCiviIDs[] = $contact['id'];
    }

    // Merge both account lists
    $accountsToDelete = array_unique(array_merge($unconfirmedAccounts, $unregisteredAccounts));
    $civiAccountsToDelete = array_unique(array_merge($unconfirmedCiviIDs, $unregisteredCiviIDs));

    // Delete the user accounts in Drupal and CiviCRM
    foreach ($accountsToDelete as $uid) {
      $user = User::load($uid);
      if ($user) {
        $user->delete();
      }
    }

    $results = Contact::delete(TRUE)
      ->addWhere('id', 'IN', $civiAccountsToDelete)
      ->execute();

    // Set the status message
    $returnValues = "Deleted " . count($accountsToDelete) . " user accounts.";

    return civicrm_api3_create_success($returnValues, $params, 'Job', 'delete_inactive_users');
  }
  catch (Exception $e) {
    throw new API_Exception('Failed to delete inactive user accounts', ['exception' => $e->getMessage()]);
  }
}  
