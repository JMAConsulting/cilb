# Advanced Import

Aims to fill the gap for the following type of needs:

* Imports that can be configured/programmed once, then let CiviCRM administrators handle the regular imports of data.
* Easy workflows that don't ask too many questions (field mappings, file/format options).
* Support imports from various data sources, such as Excel, OpenDocument or custom formats (ex: fixed-width text files).
* Easy review of errors, re-importing only rows that had errors (once the issue that caused the error is fixed).
* Tracks which row of data modified which CiviCRM entity (ex: helps to review that the import worked as expected).
* Supports large files (as long as your server allows uploading them, it has been tested with Excel files with an average of 50,000 rows).
* Can regularly run imports using a Scheduled Job, assuming a custom 'helper' that fetches data from a remote location (ex: from a third-party service) or a specific location on disk.
* The contact import from core can have a column called "tags" (the name must be exact), with a list of either tag names or IDs (separated by ";"), in order to add multiple tags at once.
* The API3 Contribution/Participant import can match contacts based on their `external_identifier`.

Out of the box, this extension supports importing APIv3 entities (similar to [csvimport](https://github.com/eileenmcnaughton/nz.co.fuzion.csvimport/), although csvimport has been more extensively tested).

This extension is intended as a base extension so that implementors can write their own import scripts, without re-inventing the wheel every time (error handling, file parsing, etc). It also provides a few implementations out of the box (Phone2Action, Stripe Subscriptions, APIv3).

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Acknowledgements

This extension is heavily inspired and often blatantly copies code from from the excellent [csvimport](https://github.com/eileenmcnaughton/nz.co.fuzion.csvimport) extension by Eileen McNaughton. This extension also re-uses code from CiviCRM core.

## Requirements

* PHP 7.4 or later
* CiviCRM 5.60 or later

## Usage

After enabling the extension, go to: Administer > Advanced Import. The menu
item is visible to users with the "import contacts" permission.

### Contact + Activity

* Export the CSV from your third party service (eg. "Phone2Action").
* In Advimport, the Contact + Activity import will let you select an activity type during the import process.
  For example, you may want to create a "Phone2Action" activity type beforehand.
  It will not be created automatically. You can also create various activity types if you have different kind of actions and want to track them with different activity types.
* You can select the type of contact matching (currently "email" or "display name").

### Stripe Subscriptions

A bit experimental, test on a dev environment first!

* Setup the Stripe payment processor in CiviCRM (under Administer > System Settings > Payment Processors)
* Currently Advimport only supports a single Stripe payment processor. If you have a few, you may want to disable them during the import process.

This is a bit experimental and leverages the Stripe extension API. So far it
was tested in situations where the memberships had already been imported. The
Stripe extension correctly imported the subscriptions and tied the recurring
payment to the membership.

### Stripe Failed Events

Exprimental - to help with debugging failed webhook events. Also useful when
there was a problem and Stripe has stopped retring to deliver events.

## Installation

Install like any CiviCRM extension.

## Implementing a custom importer

0- Create your own CiviCRM custom extension using `civix`

```bash
civix generate:module myextension
```

For more information about civix, see: https://docs.civicrm.org/dev/en/latest/extensions/

1- Implement the hook_civicrm_advimport() hook in your custom CiviCRM extension.

Example:

```php
/**
 * Implements hook_civicrm_advimport_helpers()
 */
function myextension_civicrm_advimport_helpers(&$helpers) {
  $helpers[] = [
    'class' => 'CRM_Myextension_Advimport_Example',
    'label' => E::ts('Example data'),
    'type' => '[see below]',
  ];
}
```

The `type` can be either absent, 'report-batch-update' or 'search-batch-update'. By default a helper will be displayed on the main Advimport screen, but if it's either a report or a custom search bulk-update, it makes it possible to expose advimport features as a custom search result task, or a task on a report. It's a bit experimental, but this is particularly useful for special kinds of bulk updates, data cleanup, etc. If you are using this, we would be curious to hear more about your use-cases. Currently it's mainly being used by an organization that needed a very simple and specific way to handle bulk membership renewals (see below for an example implementation, also grep the code for todo/fixme). The custom search implementation has only been tested on a custom search, not on the regular/advanced search.

2- Implement the helper class:

```php
<?php

use CRM_Myextension_ExtensionUtil as E;

class CRM_Myextension_Advimport_Example extends CRM_Advimport_Helper_PHPExcel {

  /**
   * Returns a human-readable name for this helper.
   */
  function getHelperLabel() {
    return E::ts("MyExtension - Example import");
  }

  /**
   * By default, a field mapping will be shown, but unless you have defined
   * one in getMapping() - example later below - you may want to skip it.
   * Displaying it is useful for debugging at first.
   */
  function mapfieldMethod() {
    return 'skip';
  }

  /**
   * Import an item gotten from the queue.
   *
   * This is where, in custom PHP import scripts, you would program all
   * the logic on how to handle imports the old fashioned way.
   */
  function processItem($params) {
    // At some point, we will have automatic validation on 'mandatory'
    // and validation rule (positive, string, etc).
    if (empty($params['custom_11'])) {
      throw new Exception("Favourite Colour is a mandatory field.");
    }

    if ($params['custom_11'] == 'Rainbow') {
      // Logs a warning, to flag entries for review
      // (you could also manually add a group/tag or create an activity, depending on your workflow,
      // but very often, admins just want a quick way to glance over some types of changes)
      CRM_Advimport_Utils::logImportWarning($params, "Verify that the contact has a Unicorn Certification 2.0");
    }

    // This example import expects the contact to exist already,
    // so if it's not the case, we need to flag it as an error.
    // Exceptions are caught by advimport, and admins can later review.
    $contact = civicrm_api3('Contact', 'getsingle', [
      'external_identifier' => $params['external_identifier'],
      'return.id' => 1,
      'return.custom_11' => 1,
    ]);

    if ($contact['custom_11'] != $params['custom_11']) {
      civicrm_api3('Contact', 'create', [
        'id' => $contact['id'],
        'custom_11' => $params['custom_11'],
      ]);

      // As a design choice for this example importer, we are adding contacts
      // to a group/tag (depending on the admin's choice when they started the
      // import) only if they had data that has changed.
      CRM_Advimport_Utils::addContactToGroupOrTag($contact['id'], $params);
    }

    // This associates the imported row with the contact that we matched on.
    // If we had created a contact, we would have used the `$result['id']` from
    // the Contact.create above.
    // This is optional and only to help with debugging. It helps to know which
    // contact was matched, so that we can quickly manually double-check the changes.
    CRM_Advimport_Utils::setEntityTableAndId($params, 'civicrm_contact', $contact['id']);
  }

}
```

### Implementing a "map fields" step

Optional - it lets admins map their fields during the import. For example, if admins upload Excel files, but they sometimes have small variants in the column headers, they will have to either make sure to name their columns correctly (which they will forget to do 99% of the time, leading to frustrations), or double-check the mapping during the import.

```php
  /**
   * Available fields.
   */
  function getMapping(&$form) {
    $map = [
      'external_identifier' => [
        'label' => 'External ID',
        'field' => 'external_identifier',
      ],
      'custom_11' => [
        'label' => 'Favourite Colour',
        'field' => 'custom_11',
      ],
      'date' => [
        'label' => 'Start Date',
        'field' => 'date',
        'aliases' => ['Date'],
        'description' => 'The start date in format d/m/Y',
        // If true, it allows the user to bulk update this field from the 'review data' interface
        'bulk_update' => true,
      ],
    ];

    return $map;
  }
```

Specifically, given this structure:

```
[
   <key> => [
     'label' => <label>,
     'field' => <field>,
     'aliases' => [<alias>, ...]
   ]
]
```

and an input column header value `<header>` then the following happens:

1. On the mapping screen there's a HTML Select element for each source column. The `<header>` is used for the label you see.

2. The HTML Options elements within the Select show the `<label>`s and their value attribute is the `<field>`

3. The form defaults are set by simplified comparisons (i.e. lowercase and replace any non alpha charaters with `_`) as follows:

4. If the `<header>` matches the `<label>`, the `<key>` is used.

5. If the `<header>` matches the `col_<label>`, the `<key>` is used.

6. If the `<header>` matches any `<alias>`, the `<key>` is used.

What this means is that `<key>` **must** equal `<field>`. So a real world example might be:

```
[
  'first_name' => [ 'label' => 'First Name', 'field' => 'first_name', 'aliases' => ['Given Name'] ],
]
```

This would map input column with a header of `Given Name` or `First Name` or (`given name` or `first/name` or `first_name`) to `first_name`.

You can also call the parent function and change some labels, add aliases, remove fields, etc.

```php
  /**
   * Available fields.
   */
  function getMapping(&$form) {
    $map = parent::getMapping($form);

    $map['external_identifier']['label'] = 'External ID';
    $map['external_identifier']['aliases'] = ['DonorID'];

    unset($map['not_relevant_field']);

    return $map;
  }
```

To save time with writing the PHP code for field mappings, you can also use the `Advimport.MapGenerator` API call from the CiviCRM API Explorer. Copy paste a row of headers (separated by a comma) into the `input` field, and it will generate the PHP code for you.

### Asking questions to the user during the import

An import type may want to ask the user to fill-in import-specific fields.

For example: the phone2action import allows the user to specific whether they want to create activities or tag contacts.

The code is still a bit WIP, but you can see the phone2action helper for an example. The api3 importer is also a more complicated example.

### Post-import task

```php
  /**
   * Post-import
   *
   * As the name implies, this is called after the import has
   * finished running. It is added as an item on the queue.
   * A use-case for this, could be to cleaup/tag leftover data after
   * an import (assuming the import linked the entity_ids with
   * each row, then you could check which entities were not updated).
   *
   * The params include the advimport_id, so you can lookup any
   * necessary data (such as the table_name) in there.
   */
  function postImport($params) {

  }
```

### Example implementation of a bulk-update search task

```
class CRM_Myextension_Advimport_MembershipRenewal extends CRM_Advimport_Helper_SearchBatchUpdate {

  public function getDataFromQuery($contact_ids = []) {
    $values = [];

    // Fetch the data from a Custom Search (which defines our columns)
    $search = new CRM_Myextension_Search_Form_Member($values);
    $sql = $search->all(0);
    $sql .= ' AND contact_a.id IN (' . implode($contact_ids, ',') . ')';

    $dao = CRM_Core_DAO::executeQuery($sql);

    $data = $dao->fetchAll();
    $headers = array_keys($data[0]);

    // Still not sure if this should be done systematically or leave it to the implementation
    foreach ($data as &$row) {
      $search->alterRow($row);
    }

    return [$headers, $data];
  }

  public function processItem($params) {
    // import implementation
  }

}

```

## Known issues

* APIv3 import field mapping behaves oddly when reloading an existing import (api3_entity is shown twice).
* Stripe Subscription import might timeout if there are a lot of subscriptions to import (tested so far on an account with 250 subscriptions).
* If you are running out of memory while importing large Excel files, try copy-pasting the actual rows/columns of data in a new Excel file, and clear all formatting. Sometimes Excel files will have blank rows/columns outside the actual relevant range, which then fills up the data extraction process with empty data.

## Roadmap

The roadmap is guided by the needs of the organizations sponsoring the development of this extension.

Some features likely to make it sooner than later:

* Add built-in support for various third-party providers (ex: exports from payment-processors, advocacy tools, etc). We should provide turn-key easy imports for well-known third-parties.
* Admin settings (or permission, Issue #19) to enable/disable which import types are available.
* Allow to acknowledge warnings/errors, so that administrators can keep track of the review of errors/warnings.
* Select a dedupe rule while importing, not only contacts, but also contributions, activities, memberships.
* Provide an interface to replace Batch Contribution/Membership data entry (without the batch integration). This would let people upload partial data, fill in the gaps (ex: set the financial type, date..), run a validation then import (which might require having the concept of "actions" while on the "view data" screen).
* (brainstorm) Report data cache. Some reports can be very slow to generate. Use advimport temp tables as a cache? Use-case for this is probably somewhat limited, and could be difficult to apply in a generic way with the reporting UI, unless we provide a new UI to view the cached report. Not many benefits of coupling with this extension, unless it's to re-use the "view data" UI.

Pull-requests or sponsors are more than welcomed for these features. I do not currently have a need for them, but I assume they would be useful.

## Support

Please post bug reports in the issue tracker of this project:  
https://lab.civicrm.org/extensions/advimport/issues

Commercial support is available through Coop SymbioTIC:  
https://www.symbiotic.coop/en
