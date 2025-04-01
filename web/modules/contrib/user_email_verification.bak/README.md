CONTENTS OF THIS FILE
---------------------
 * Introduction
 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Use case

INTRODUCTION
------------
Module allows:
* to have Email verification
* to type password on registration
* to be logged in right after registration

If user do not verify the Email in a certain time interval account will be
blocked.

REQUIREMENTS
------------
* User (core)

RECOMMENDED MODULES
-------------------
* Token https://www.drupal.org/project/token

INSTALLATION
------------
Install as you would normally install a contributed Drupal module. Visit
https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
for further information.

CONFIGURATION
-------------
* Go to: _Manage_ -> _Configuration_ -> _People_ -> _Account settings_
  `/admin/config/people/accounts` page:
* In **Registration and cancellation** section:
  * Set "Who can register accounts?" = "Visitors"
  * Uncheck "Require email verification when a visitor creates an account"
    checkbox
* In **Emails** section:
  * Add [user:verify-email] to the "Welcome (no approval required)"
  mail to send to the user the Email verification link
* Click "Save configuration" button
* Go to: _Manage_ -> _Configuration_ -> _People_ -> _User Email Verification_
  `/admin/config/people/user-email-verification` page:
  * Set "Skip roles" - the roles which shouldn't verify the Email
  * Set "Verification time interval" (in seconds) - the time for user to verify
  the Email, **when this time is over - user account will be blocked**
  * Set "Send reminder" - how many times user will be notified
  (with Verification mail) during "Verification time interval"
    * Customize "Verification mail subject" and "Verification mail body"
    if "Send reminder" was set
  * Check "Enable extended verification period" if you'd like to provide an
  extra time to the user to verify the Email and activate blocked account
    * Set "Extended verification time interval" (in seconds) - the time for
    user to verify Email and unblock account, **when this time is over - user
    account will be removed or blocked, depends on "When cancelling a user
    account" setting**
    * Customize "Mail subject" and "Mail body"
* Click "Save configuration" button
* To have ability to translate emails subject & body:
  * Enable **Configuration Translation** core module
  * On module configuration page `/admin/config/people/user-email-verification`
    new tab **Translate user email verification** will appear
  * Use this tab to add translations

USE CASE
--------
You want to have user Email verified and login the user right after
registration was successfully completed (default Drupal Email verification
doesn't allow to login the user right after registration).
