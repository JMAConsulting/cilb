# CronPlus

![Screenshot](/images/screenshot.png)

CronPlus enhances native CiviCRM job Schedule using ***crontab*** expressions, to execute tasks in more accurate way. Expressions allowed can be found [here](https://en.wikipedia.org/wiki/Cron)

It fixes as well the known issue for cron execution shifting described here:
- https://civicrm.stackexchange.com/questions/12003/has-anyone-run-into-scheduled-jobs-drifting-on-centos7
- https://issues.civicrm.org/jira/browse/CRM-18671

## Requirements

### Current Release

* PHP v8.2
* CiviCRM 5.81+ and support for **Drupal 10/11** (**CronPlus 2.6.0+**)

### Older Releases

* CiviCRM 5.63+ and support for **Drupal 9/10** (**CronPlus 2.5.0+**)
* CiviCRM 5.57+ and support for **Drupal 9/10** (**CronPlus 2.4.0+**)
* CiviCRM 5.42+ and support for **Drupal 8** (**CronPlus 2.3.0+**)
* CiviCRM 5.33+ (**CronPlus 2.2.0+**)
* CiviCRM 5.24+ (**CronPlus 2.1.0+**)
* Earlier releases than CiviCRM 5.24 (**CronPlus 2.0.0**)


## Installation

1. Download this extension and unpack it in your 'extensions' directory. You may need to create it if it does not already exist, and configure the correct path in CiviCRM -> Administer -> System -> Directories.
2. If installing from git, run `composer install`.
3. Enable the extension from CiviCRM -> Administer -> System -> Extensions.

## Usage

- Once installed go to Administer / System Settings / Scheduled Jobs
- Edit or create a new Scheduled Job
- **Run frequency** field now is just a helper to complete the **Cronplus** expression, or it can be manually edited if you have experience with **crontab**
- Save it

### Important

The implementation of this type of cron relies on the assumption that there's a process running every minute that checks if there's a Job that needs to be executed.
In the CiviCRM world, this is not going to happen, because it's very unlikely (and not recommended) to setup CiviCRM cron to run every minute. More info on how to configure CiviCRM cron [here](https://docs.civicrm.org/sysadmin/en/latest/setup/jobs/).

So to meet the expectation on when a Scheduled Job needs to be executed, the assumption we make is that a Job must be executed on the next CiviCRM cron execution after the Previous Execution Datetime of the cronplus expression.

**Examples:**

Let's think in an ideal scenario where we setup CiviCRM cron every 15 minutes so it runs every hour at *xx:00*, *xx:15*, *xx:30*, *xx:45* sharp.
If we add Jobs with these expressions, they will be executed at:

- `* * * * *`: will be executed on every CiviCRM cron
- `10 * * * *`: will be executed at *00:15*, *01:15*, *02;15*, ..., *22:15*, *23:15* daily
- `* 2-6 * * SAT,SUN`: will be executed at *02:00*, *02:15*, *02:30*, ..., *6:30*, *6:45* on Saturdays and Sundays
- `* 3 1 * *`: will be executed at *03:00*, *03:15*, *03:30*, *03:45* on the 1st day of every month
- `20 10 * * MON`: will be executed at *10:30* on Mondays
- `59 23 * * MON`: will be executed at *00:00* on Tuesdays *(one of the edge cases where it doesn't respect the weekday, but it must run only 1 time per TUE)*


*Disclaimer:* these examples are **ideal**, but in the real world it's very unlikely the CiviCRM cron will be executed at exactly time set, because it gets delayed for different reasons.


## Known Issues

There's a known issue documented in ticket [#3](https://lab.civicrm.org/extensions/cronplus/-/issues/3). After installing **CronPlus**, if you install any other extension
that creates a new Scheduled Job automatically, this Job won't have any *cronplus-like* expression by default. You'll need to manually edit the Job and add one. If you don't do that, **CronPlus won't execute this Job until** the expression is completed.  

**Update**: CronPlus 2.6.0 breaks compatibility for CiviCRM under 5.81 due to [PR#29598](https://github.com/civicrm/civicrm-core/pull/29598/files/e7178d814ccd23c711ef6304a519aa49bd6e3f76).  

From v2.4.0 Cron expression for new Scheduled Tasks is inserted by default, taking a *standard* value depending on the frequency selected.  

In the other hand, when **CronPlus** is installed, it will take care of existing Scheduled Jobs, and add their expression, based on the frequency selected for each of them.  

From version 2.2.1, there's a new `System.check` that will display a WARNING if there is any active Scheduled Job without its *cronplus* expression.  


## Support and Maintenance

This extension is supported and maintained by:

[![iXiam Global Solutions](images/ixiam-logo.png)](https://www.ixiam.com)

The extension is licensed under [AGPL-3.0](LICENSE.txt).

---

These extension relies on this public libraries
- [dragonmantank/cron-expression](https://github.com/dragonmantank/cron-expression)
- [prettycron.js](https://github.com/azza-bazoo/prettycron)

See composer.json for more information about dependencies.
