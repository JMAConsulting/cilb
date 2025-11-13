<?php

use CRM_Advimport_ExtensionUtil as E;

/**
 * Helper functions often using when importing data.
 *
 * You probably do not want to depend on any of these functions, which are pretty experimental.
 */
class CRM_Advimport_Utils {

  /**
   * Searches for an individual using either the external_identifier, email or
   * other criteria (such as first/last name, if provided). Usually we
   * can match by email, but if not, then we try other criteria.
   *
   * As a precaution, the contact_type is required if searching using another criteria
   * than the external_identifier.
   * @param array $params
   *
   * @return mixed|null
   * @throws \CiviCRM_API3_Exception
   */
  public static function getContactByEmailOrOther($params) {
    // This is a rather reliable ID for when doing imports, so we do not require
    // the contact_type here.
    if (!empty($params['external_identifier'])) {
      $result = civicrm_api3('Contact', 'get', [
        'external_identifier' => $params['external_identifier'],
        'contact_type' => $params['contact_type'],
        'is_deleted' => 0,
        'sequential' => 1,
      ]);

      // Return the first contact (this should always be unique).
      if ($result['count'] >= 1) {
        return $result['values'][0]['contact_id'];
      }

      // If we are only searching by external_identifier, and it was not found,
      // then bail out from further checks below (avoids exception about contact_type).
      if (count($params) == 1) {
        return NULL;
      }
    }

    // An email might be shared between an individual and org, so better to enforce
    // a contact_type.
    if (empty($params['contact_type'])) {
      throw new Exception("getContactByEmailOrOther: contact_type is a required parameter");
    }

    if (!empty($params['email'])) {
      // This searches the primary email only
      $result = civicrm_api3('Contact', 'get', [
        'email' => $params['email'],
        'contact_type' => $params['contact_type'],
        'is_deleted' => 0,
        'sequential' => 1,
      ]);

      // Return the first contact, assuming the others are duplicates.
      if ($result['count'] >= 1) {
        return $result['values'][0]['contact_id'];
      }

      // Search by non-primary email
      $email = \Civi\Api4\Email::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('email', '=', $params['email'])
        ->addWhere('contact_id.contact_type', '=', $params['contact_type']) 
        ->addWhere('contact_id.is_deleted', '!=', TRUE)
        ->addOrderBy('id', 'ASC')
        ->execute()
        ->first();

      if (!empty($email['contact_id'])) {
        return $email['contact_id'];
      }
    }

    // Search using all of the other criteria provided (except email).
    // Note that we might have params with empty values, such as last_name='',
    // so in that case, we don't want to search for empty values.
    $has_other_data = FALSE;

    $email = CRM_Utils_Array::value('email', $params);
    unset($params['email']);

    foreach ($params as $key => $val) {
      if ($val && $key != 'contact_type') {
        $has_other_data = TRUE;
      }
    }

    if ($has_other_data) {
      $params['is_deleted'] = 0;
      $params['sequential'] = 1;

      $result = civicrm_api3('Contact', 'get', $params);

      // Return the first contact, assuming the others are duplicates.
      if ($result['count'] >= 1) {
        return $result['values'][0]['contact_id'];
      }
    }

    return NULL;
  }

  /**
   * Creates or updates a contact record.
   * Returns the record's ID (whether existing or created).
   * @param array $params
   * @param int $mode
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  public static function updateOrFillContact($params, $mode = CRM_Import_Parser::DUPLICATE_FILL) {
    // If we don't have a contact_id, we can create directly
    // since dupe checking should already have been done.
    if (empty($params['contact_id'])) {
      $result = civicrm_api3('Contact', 'create', $params);
      return $result['id'];
    }

    // Fetch the fields that we want to update
    $return = [
      'contact_id' => $params['contact_id'],
    ];

    foreach ($params as $key => $val) {
      $return['return.' . $key] = 1;
    }

    $contact = civicrm_api3('Contact', 'getsingle', $return);

    // We will add onto here the params we want to update
    $new_params = [
      'contact_id' => $params['contact_id'],
    ];

    if ($mode == CRM_Import_Parser::DUPLICATE_FILL) {
      foreach ($params as $key => $val) {
        if (empty($contact[$key]) && !empty($params[$key])) {
          $new_params[$key] = $val;
        }
      }
    }
    elseif ($mode == CRM_Import_Parser::DUPLICATE_UPDATE) {
      // NB: This will clear out fields, if the import has an empty value
      foreach ($params as $key => $val) {
        if (empty($contact[$key]) || $contact[$key] != $params[$key]) {
          $new_params[$key] = $val;
        }
      }
    }

    if (count($new_params) > 1) {
      $result = civicrm_api3('Contact', 'create', $new_params);
      $params['contact_id'] = $result['id'];
    }

    // This should only happen if it's an existing contact
    return $params['contact_id'];
  }

  /**
   * FIXME: return the phone array
   * @param array $params
   *
   * @return mixed|null
   * @throws \CiviCRM_API3_Exception
   */
  public static function getPhone($params) {
    $params['sequential'] = 1;
    $result = civicrm_api3('Phone', 'get', $params);

    if ($result['count'] > 1) {
      throw new Exception("Multiple phones found for: " . print_r($params, 1));
    }

    if ($result['count'] == 1) {
      return $result['values'][0]['id'];
    }

    return NULL;
  }

  /**
   * Given a set of params relevant for the given entity (ex: location_type_id,
   * phone_type_id, etc), this function assumes that there should be only one match,
   * and will return that match so that updateContactRelatedEntity() will avoid
   * creating a duplicate entry. It is useful for when re-running imports, or when
   * importing data into a database that already has data.
   *
   * Works for: Phone, Email, Address, Note.
   * @param string $entity
   * @param array $params
   *
   * @return mixed|null
   * @throws \CiviCRM_API3_Exception
   */
  public static function getContactRelatedEntity($entity, $params) {
    $params['sequential'] = 1;
    $result = NULL;

    if ($entity == 'Address') {
      // Address sometimes won't match if we 'get' with all the params.
      $result = civicrm_api3($entity, 'get', [
        'contact_id' => $params['contact_id'],
        'location_type_id' => $params['location_type_id'],
        'sequential' => 1,
      ]);
    }
    elseif ($entity == 'Phone') {
      $result = civicrm_api3($entity, 'get', [
        'contact_id' => $params['contact_id'],
        'location_type_id' => $params['location_type_id'],
        'phone_type_id' => CRM_Utils_Array::value('phone_type_id', $params),
        'sequential' => 1,
      ]);
    }
    elseif ($entity == 'Email') {
      $result = civicrm_api3($entity, 'get', [
        'contact_id' => $params['contact_id'],
        'location_type_id' => $params['location_type_id'],
        'sequential' => 1,
      ]);
    }
    elseif ($entity == 'Note') {
      $result = civicrm_api3($entity, 'get', [
        'contact_id' => $params['contact_id'],
        'sequential' => 1,
      ]);
    }
    else {
      $result = civicrm_api3($entity, 'get', $params);
    }

    if ($result['count'] > 1) {
      throw new Exception("Multiple $entity found for: " . print_r($params, 1));
    }

    if ($result['count'] == 1) {
      return $result['values'][0];
    }

    return NULL;
  }

  /**
   * Updates (or creates) an entity related to a contact (phone, address, email, note).
   * @see getContactRelatedEntity().
   * @param string $entity
   * @param array $params
   * @param int $mode
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  public static function updateContactRelatedEntity($entity, $params, $mode = CRM_Import_Parser::DUPLICATE_FILL) {
    if (empty($params['id'])) {
      $result = self::getContactRelatedEntity($entity, $params);

      if ($result && !empty($result['id'])) {
        $params['id'] = $result['id'];

        // Just a precaution
        if (!empty($params['contact_id'])) {
          unset($params['contact_id']);
        }

        if ($mode == CRM_Import_Parser::DUPLICATE_FILL) {
          $changes = [];

          // Check if the existing data has non-empty values to fill
          foreach ($params as $key => $val) {
            if (empty($result[$key])) {
              $changes[$key] = $params[$key];
            }
          }

          if (!empty($changes)) {
            $changes['id'] = $result['id'];
            civicrm_api3($entity, 'create', $changes);
          }

          return $result['id'];
        }
      }
    }

    $result = civicrm_api3($entity, 'create', $params);
    return $result['id'];
  }

  /**
   * Returns the default location type ID.
   *
   * @return int
   * @throws \CiviCRM_API3_Exception
   */
  public static function getDefaultLocationType() {
    $result = civicrm_api3('LocationType', 'getsingle', [
      'is_default' => 1,
    ]);

    return $result['id'];
  }

  /**
   * Yet another function to best-guess country names.
   *
   * Minimal for now, but will no doubt grow into a monster.
   * Todo: support translated country names.
   */
  public static function getCountryID($search, $options = []) {
    $countries = [];
    $column = 'name';

    if (!$search) {
      return null;
    }

    if (!empty($options['column'])) {
      $column = $options['column'];
    }

    CRM_Core_PseudoConstant::populate($countries, 'CRM_Core_DAO_Country', TRUE, $column, 'is_active');
    return array_search($search, $countries);
  }

  /**
   * Yet another function to best-guess state-province names. It first searches
   * by name, then by abbreviation, and then by translated name.
   * @param string $search
   * @param array $options
   *
   * @return int|string|null
   * @throws \Exception
   */
  public static function getStateProvinceID($search, $options = []) {
    $provinces = [];
    $search_by_column = ['name', 'abbreviation'];

    if (!$search) {
      return null;
    }

    // Override the search options
    if (!empty($options['column'])) {
      $search_by_column = [$options['column']];
    }

    $where_clause = '';

    if (!empty($options['country_id'])) {
      $where_clause = 'country_id = ' . $options['country_id'];
    }

    foreach ($search_by_column as $column) {
      // Using this obscure function avoids localization
      CRM_Core_PseudoConstant::populate($provinces, 'CRM_Core_DAO_StateProvince', TRUE, $column, 'is_active', $where_clause);
      $result = array_search($search, $provinces);

      if ($result) {
        return $result;
      }
    }

    // Try translations (name only, abbreviations would not make sense, english-speaking countries mostly)
    $provinces = CRM_Core_PseudoConstant::stateProvince();
    $result = array_search($search, $provinces);

    if ($result) {
      return $result;
    }

    throw new Exception('getStateProvinceID: not found');
  }

  /**
   * @param array $params
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  public static function updateRelationship($params) {
    $params['sequential'] = 1;
    $result = civicrm_api3('Relationship', 'get', $params);

    if ($result['count'] > 1) {
      throw new Exception("Multiple relationships found for: " . print_r($params, 1));
    }

    if ($result['count'] == 1) {
      // Nothing to do
      return $result['values'][0]['id'];
    }

    // Create the relationship
    $result = civicrm_api3('Relationship', 'create', $params);
    return $result['id'];
  }

  /**
   * Add contact to a group (by name, not by ID). Create the group
   * if it does not exist.
   *
   * This is to help with imports where a group name is specified in
   * a column.
   * @param int $contact_id
   * @param string $group_title
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function addContactToGroupByName($contact_id, $group_title) {
    static $cache = [];

    // Update cache and create group if it does not exist.
    if (empty($cache[$group_title])) {
      $result = civicrm_api3('Group', 'get', [
        'title' => $group_title,
        'sequential' => 1,
      ]);

      if (!empty($result['values'])) {
        $cache[$group_title] = $result['values'][0]['id'];
      }
      else {
        $result = civicrm_api3('Group', 'create', [
          'title' => $group_title,
        ]);

        $cache[$group_title] = $result['id'];
      }
    }

    $group_id = $cache[$group_title];

    // Check if the contact is already part of the group
    $result = civicrm_api3('GroupContact', 'get', [
      'group_id' => $group_id,
      'contact_id' => $contact_id,
    ]);

    if (empty($result['count'])) {
      civicrm_api3('GroupContact', 'create', [
        'group_id' => $group_id,
        'contact_id' => $contact_id,
        'status' => 'Added',
      ]);
    }
  }

  /**
   * Add contact to a tag (by name, not by ID). Create the tag
   * if it does not exist.
   *
   * This is to help with imports where a tag name is specified in
   * a column.
   * @param int $contact_id
   * @param string $tag_name
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function addContactToTagByName($contact_id, $tag_name) {
    static $cache = [];

    // Update cache and create group if it does not exist.
    if (empty($cache[$tag_name])) {
      $result = civicrm_api3('Tag', 'get', [
        'name' => $tag_name,
        'sequential' => 1,
      ]);

      if (!empty($result['values'])) {
        $cache[$tag_name] = $result['values'][0]['id'];
      }
      else {
        $result = civicrm_api3('Tag', 'create', [
          'name' => $tag_name,
        ]);

        $cache[$tag_name] = $result['id'];
      }
    }

    $tag_id = $cache[$tag_name];
    CRM_Advimport_Utils::addContactToTagByID($contact_id, $tag_id);
  }

  /**
   * Add contact to a tag (by name, not by ID). Create the tag
   * if it does not exist.
   *
   * This is to help with imports where a tag name is specified in
   * a column.
   * @param int $contact_id
   * @param string $tag_name
   *
   * @throws \CiviCRM_API3_Exception
   */
   public static function addContactToTagByID($contact_id, $tag_id) {
    // Check if the contact already had the tag
    $result = civicrm_api3('EntityTag', 'get', [
      'tag_id' => $tag_id,
      'entity_table' => 'civicrm_contact',
      'entity_id' => $contact_id,
    ]);

    if (empty($result['count'])) {
      civicrm_api3('EntityTag', 'create', [
        'tag_id' => $tag_id,
        'entity_table' => 'civicrm_contact',
        'entity_id' => $contact_id,
      ]);
    }
  }

  /**
   * Add contact to import group or tag.
   *
   * Helper function to make it easy to passthrough data from advimport's MapFields.
   * i.e. the Helper does not need to check whether to add to a group, tag or none.
   *
   * This function does not need to check if the group/tag has already been added,
   * because the group/tag is always unique for the import.
   * @param int $contact_id
   * @param array $params
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function addContactToGroupOrTag($contact_id, $params) {
    if ($params['group_or_tag'] == 'tag') {
      civicrm_api3('EntityTag', 'create', [
        'contact_id' => $contact_id,
        'tag_id' => $params['group_or_tag_id'],
      ]);
    }
    elseif ($params['group_or_tag'] == 'group') {
      civicrm_api3('GroupContact', 'create', [
        'contact_id' => $contact_id,
        'group_id' => $params['group_or_tag_id'],
      ]);
    }
  }

  /**
   * Log an import message in the temp import table.
   *
   * Using params to make it easier to passthrough info from processItem()
   * for the import_table_name, etc.
   * @param array $params
   * @param string $message
   * @param int $import_status
   */
  public static function logImportMessage($params, $message, $import_status = NULL) {
    $log = [];
    $row_id = $params['import_row_id'];
    $table_name = $params['import_table_name'];

    // Check if there are existing messages
    $old = CRM_Core_DAO::singleValueQuery("SELECT import_error FROM $table_name where `row`= %1", [
      1 => [$row_id, 'Positive'],
    ]);

    if ($old) {
      $log = json_decode($old, TRUE);
    }

    $log[] = $message;
    $log = json_encode($log);

    CRM_Logging_Schema::disableLoggingForThisConnection();

    CRM_Core_DAO::executeQuery("UPDATE $table_name SET import_error = %2 where `row`= %1", [
      1 => [$row_id, 'Positive'],
      2 => [$log, 'String'],
    ]);

    if ($import_status) {
      CRM_Core_DAO::executeQuery("UPDATE $table_name SET import_status = %2 where `row`= %1", [
        1 => [$row_id, 'Positive'],
        2 => [$import_status, 'Positive'],
      ]);
    }

    CRM_Advimport_BAO_Advimport::reEnableLogging();
  }

  /**
   * Log an import warning in the temp import table.
   *
   * Using params to make it easier to passthrough info from processItem()
   * for the import_table_name, etc.
   * @param array $params
   * @param string $warning
   */
  public static function logImportWarning($params, $warning) {
    $row_id = $params['import_row_id'];
    $table_name = $params['import_table_name'];

    Civi::log()->warning("advimport: $table_name/$row_id: $warning");
    self::logImportMessage($params, $warning, 3);
  }

  /**
   * Set the entity table and ID for the row.
   *
   * Using params to make it easier to passthrough info from processItem()
   * for the import_table_name, etc.
   *
   * This data can be used by importers to help with cleanup tasks, debugging, etc.
   * @param array $params
   * @param string $entity_table
   * @param int $entity_id
   */
  public static function setEntityTableAndId($params, $entity_table, $entity_id) {
    $row_id = $params['import_row_id'] ?? $params['importID'];
    if (isset($params['import_table_name'])) {
      $table_name = $params['import_table_name'];
      $row_column = '`row`';
    } else {
      $table_name = $params['importTempTable'];
      $row_column = '_id';
    }

    // TODO: I feel like this should not be necessary...
    CRM_Logging_Schema::disableLoggingForThisConnection();

    CRM_Core_DAO::executeQuery("UPDATE $table_name SET entity_table = %2, entity_id = %3 WHERE $row_column = %1", [
      1 => [$row_id, 'Positive'],
      2 => [$entity_table, 'String'],
      3 => [$entity_id, 'String'],
    ]);

    CRM_Advimport_BAO_Advimport::reEnableLogging();
  }

  /**
   * Given a string, return a machine name (alphanum, no special chars, max length 64).
   * @param string $string
   * @param string $fill
   *
   * @return false|string
   */
  public static function convertToMachineName($string, $fill = '_') {
    $string = mb_strtolower(trim($string));
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = preg_replace('/[^_a-z0-9]+/', $fill, $string);
    $string = substr($string, 0, 64);

    if (empty($string)) {
      return 'empty';
    }
    return $string;
  }

}
