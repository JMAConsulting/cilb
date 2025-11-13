# Update Language Files

Download or update translations files for CiviCRM core and extensions.

Once installed, this extension allows you to:

* Update the translation files for CiviCRM core or extensions, by visiting either the Adminster > System Settings > Manage Extensions page or Administer > System Settings > Localization Settings.
* In Administer > System Settings > Localization: In "single language mode" this extension allows you to select/enable multiple languages (by default, it is only possible to do so if the language files are already installed).

This extension was originated during the 2014 CiviCon Sprint in Lake Tahoe. Attending a Sprint is a great way to support
our community, meet wonderful people, and have loads of fun even if you are not a programmer or implementer.
Initially developed and maintained for many years by CiviDesk, the extension is now maintained by Coop Symbiotic
and the CiviCRM community.

## Requirements

* CiviCRM 5.65 or later

## Jargon (definitions)

It's easy to get lost in translation with the terms we use:

* Localization: the process of adapting (translating) software into another language or region (locality)
* l10n: short for localization
* locale: rather synonym to translation, but a locale includes region-specific
  particularities. For example: the "English (UK)" is not really a translation
  of English, it's a locale.

## Usage

Once installed, this extension will automatically download translation files for CiviCRM core and extensions when you
access either the localization settings or extension management screens in CiviCRM administration. The localization
settings screen will also display all possible languages you can enable in CiviCRM.

You can manually trigger the update using the API3: `uplang.fetch`

The files will be downloaded to `[civicrm.l10n]`, which defaults to
`[civicrm.private]/l10n`, which is by default `wp-content/uploads/civicrm/l10n`
on WordPress or `files/civicrm/l10n` on Drupal.

For more information, see the [File System](https://docs.civicrm.org/dev/en/latest/framework/filesystem/#tip-sub-directories)
chapter from the CiviCRM Developer Guide.

## Differences with the l10nupdate extension

* Extension translation files are downloaded to the "[civicrm.l10n]" directory,
  which can be set to a directory outside the normal CiviCRM codebase, and
  therefore safer to write for the web-server and avoids translations being
  lost after CiviCRM upgrades.

* The Api3 function is called "uplang.fetch" and the "locales" parameter will
  only download the specified language (instead of that locale as well as all
  enabled locales). If no locale is specified, it will download
  all enabled locales.

* There is a button on the Extensions and Localization pages, to force an update
  of the translation files (instead of downloading automatically, but only if
  the files are older than 24h).

## Support

The latest version of this extension can be found at:  
https://lab.civicrm.org/extensions/uplang/

The issue tracker is located at:  
https://lab.civicrm.org/extensions/uplang/-/issues

## Copyright

Copyright (C) 2022-2023 Coop Symbiotic, https://www.symbiotic.coop/en
Copyright (C) 2014-2022 IT Bliss LLC, http://cividesk.com/  

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published
by the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
