INTRODUCTION
------------

It is a module that allow your website redirect any user in any node
to anywhere.

The redirects could be configured for individual nodes or by content type.


REQUIREMENTS
------------

Make sure that table ordering by weight [issue](https://www.drupal.org/project/drupal/issues/2396923) is solved or apply the [patch](https://www.drupal.org/files/issues/2396923-table_sort_0.patch).


INSTALLATION
------------

You can follow the [Drupal's](https://www.drupal.org/docs/user_guide/en/extend-module-install.html) steps to download and install a module.

Please use Composer to manage your Drupal site.

Check on the [module's page](https://www.drupal.org/project/redirect_page_by_role) the available versions.


CONFIGURATION
-------------

### Global configuration
* Go to
    * ```Admin > Configuration > Search and metadata > Redirect Page By Role```.
    * Or ```Admin > Extend``` and search for ```Redirect Page By Role```
    and click on ```Configure```.

* On **Role to bypass the redirection rules** will be listed all roles for your
website, you can choose which role(s) can bypass the redirection rules.

* On **Default redirect status** you can choose the redirect status for global
configurations.

* On **CONTENT TYPES DEFAULT SETTINGS** you can set redirection rule for each
role on each content type you have in your website.
    * You can set the priority for each role just dragging and drop the role.
    The priority is from top to bottom.

* Click on **Save configuration**.

![Redirect Page by Role global settings](https://www.drupal.org/files/project-images/Screenshot%20from%202021-09-09%2015-40-21.png)

### Node individual configuration
**Attention: Node settings have higher priority than global settings!**
* Access the **Node** you want to add a redirection rule.
* On the right side menu expand **REDIRECT PAGE BY ROLE** menu
* Clicking on the checkbox **Override default settings** will appear the
**Redirect Page By Role** node configuration menu.
* Now you can set redirection rule for each role for this specific node.
    * You can set the priority for each role just dragging and drop the role.
    The priority is from top to bottom.
* Save the node.

![Redirect Page by Role node settings](https://www.drupal.org/files/project-images/Screenshot%20from%202021-09-09%2015-43-12.png)

Enjoy your redirection rules!
