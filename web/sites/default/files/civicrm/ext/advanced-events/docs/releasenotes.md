## Information

Releases use the following numbering system:
**{major}.{minor}.{incremental}**

* major: Major refactoring or rewrite - make sure you read and test very carefully!
* minor: Breaking change in some circumstances, or a new feature. Read carefully and make sure you understand the impact of the change.
* incremental: A "safe" change / improvement. Should *always* be safe to upgrade.

* **[BC]**: Items marked with [BC] indicate a breaking change that will require updates to your code if you are using that code in your extension.

## Release 2.3.1 (2025-11-27)

* Minor update. No changes.

## Release 2.3.0 (2025-03-24)

* Various updates and bugfixes.

## Release 2.2.1 (2025-01-06)

* Change ManagedEntity to update policy always (prevents conflicts with other extensions and fixes issues with them disappearing in certain circumstances).

## Release 2.2.0 (2024-12-04)

* Update searches
* Managed entity fixes
* Switch to entity framework v2
* Switch to mgd files to define cg_extend_objects

## Release 2.1.5 (2024-03-06)

* Fix [#20](https://lab.civicrm.org/extensions/civicrm-advanced-events/-/issues/20) Style issue with "Linked Events" tab on Manage event template.

## Release 2.1.4 (2024-02-14)

* Fix missing class in copy participants.

## Release 2.1.3 (2024-02-09)

* Use setting-admin mixin and managed entity for navigation menu entry.
* Fix [#19](https://lab.civicrm.org/extensions/civicrm-advanced-events/-/issues/19) Compatibility with 5.68 and earlier.

## Release 2.1.2 (2024-02-08)

* Use current permissions format in hook.

## Release 2.1.1 (2024-02-07)

* Additional fix for end dates.

## Release 2.1 (2024-02-07)

* Fix [#16](https://lab.civicrm.org/extensions/civicrm-advanced-events/-/issues/16) - End Date not copying over properly.
* Fix [#17](https://lab.civicrm.org/extensions/civicrm-advanced-events/-/issues/17) - Custom Fields not copied over when Participants are Copied.
* Fix [#18](https://lab.civicrm.org/extensions/civicrm-advanced-events/-/issues/18) - "Add new template" button does nothing?

## Release 2.0 (2024-01-10)

* Rewrite to use FormBuilder and remove legacy code.

## Release 1.2.2 (2023-03-22)

* Remove deprecated function isCampaignEnable().

## Release 1.2.1 (2023-03-20)

* CiviCRM 5.58 compatibility.

## Release 1.2 (2022-08-31)

* Add option to hide 'skip participant' button.
* Add option to set PriceFieldValues visible to main or additional participant only.

## Release 1.1.1

* PHP7.4 compatiblity. Cleanup PHP and tpl notices.

## Release 1.1

* Fix [#3](https://lab.civicrm.org/extensions/civicrm-advanced-events/-/issues/3) Copy Participants breaks on update
* Fix [#4](https://lab.civicrm.org/extensions/civicrm-advanced-events/-/issues/4) The paginator doesn't work for Event Templates
* Improve documentation.
* Use font-awesome icons and helper available from CiviCRM 5.27

## Release 1.0.2

* Fix for repeating events by end date.

## Release 1.0.1

* Fix issues with "Find Events (By Template)" not working.

## Release 1.0

* Compatibility fixes for later versions of CiviCRM (minimum version now 5.24).
* Add event template permissions.
* Make sure we always set created_id and created_date.
* Add Copy template link to event templates list.
* Add docs framework.
* Add ability to create templates from existing events.

## Release 0.10

* EventTemplate.create API now accepts event_id to create a template from an existing event.
* A new "link" is provided on the event list to "Create Template" from event.

## Release 0.9

* Drop support for CiviCRM 5.7 (minimum version is now 5.13)
* Only show one button on event repetition (the cancel button is not useful).
* Use our version of formRule instead of core version (this fixes issues on 5.13+).
* Convert to short array syntax.
* Fix php notices on event template.

## Release 0.8

* Fix copying more than 25 participants from source event

## Release 0.2

* New implementation of repeating events. Create an event template and then "Add Event" or use the repeat tab on the template.

