# About WPCloudDeploy #

**WPCloudDeploy is a WordPress plugin that allows you to easily deploy servers at major cloud-server providers and then install apps (such as WordPress) on those servers.  And it does it from inside the familiar WordPress wp-admin dashboard.**

![WPCloudDeploy Dashboard](https://wpclouddeploy.com/wp-content/uploads/2021/10/server-list-without-slate-theme-01.png)

The plugin makes it easy to deploy servers at well-known providers such as DigitalOcean, AWS and more.  However, the core plugin only includes support for DigitalOcean.

You can add additional support for EC2, LIGHTSAIL, LINODE, VULTR, UPCLOUD, HETZNER, EXOSCALE, GOOGLE, AZURE and ALIBABA by [purchasing a premium subscription](https://wpclouddeploy.com/pricing/). 

**It is primarily used to install and manage WordPress servers and applications.** But it also includes sample apps for just basic servers (i.e., servers with no additional apps) and OpenVPN servers.

Its extensible model allow developers to add new apps in the future.

All management features are available inside of wp-admin on your WordPress site.  Apart from the server providers, you do not need a 3rd party SaaS service to manage your servers and apps.

[View a full list of WordPress-focused features](https://wpclouddeploy.com/features/)

## Getting Started ##

Download the zip file from this site and install it like you would any other WordPress plugin.

Then, to install WordPress servers and sites, follow the [getting started documentation on the WPCloudDeploy site](https://wpclouddeploy.com/documentation/wpcloud-deploy/introduction-to-wpcloud-deploy/)

Also, please make sure you [sign up for our notifications](https://wpclouddeploy.com/mailpoet-group/basic-subscription-form/) so that you can be notified of project updates.

## Documentation ##

Full documentation is located on the WPCloudDeploy website.

[Commonly used documentation](https://wpclouddeploy.com/doc-landing/)

[All documentation](https://wpclouddeploy.com/documentation/wpcloud-deploy/introduction-to-wpcloud-deploy/)

## Plugin Features ##

Features of the plugin include:

* APP: WordPress - **EXTENSIVE** support for deploying and managing WordPress servers and sites from inside the WordPress admin dashboard. This is the primary app type that this plugin supports.  
* APP: OpenVPN - includes front-end purchase and management.
* Integrated DigitalOcean Cloud Provider.
* Extensible server provider model supports EC2, LIGHTSAIL, LINODE, VULTR, UPCLOUD, HETZNER, EXOSCALE and other cloud providers via additional plugins.

## Roadmap ##

[View the official roadmap on Trello](https://trello.com/b/pYl53rvM/wpcloud-deploy-public-roadmap)

## Requirements ##
* WordPress 5.6 or later
* PHP 7.4 (8.x is not yet supported primarily because many server provider PHP API wrappers and some of our PHP dependencies do not support it (yet).)

Once the plugin is installed please view additional changes required to PHP and web server execution timeout values under the WPCLOUDDEPLOY->SETTINGS->GENERAL SETTINGS tab.
You can also view all requirements at the bottom of our [getting started documentation](https://wpclouddeploy.com/documentation/wpcloud-deploy/introduction-to-wpcloud-deploy/) or in the [requirements documentation](https://wpclouddeploy.com/documentation/wpcloud-deploy/requirements/)

## How To Contribute ##

Have a change you would like to see incorporated?  Then create a PR (pull request) against the **dev** branch.

Have some time to help test new versions?  Download a zip of the **dev** branch and test - changes that need testing are usually listed in the CHANGE LOG section of this readme file below (under the most recent version number.)

If you'd like to contribute to translations, you can do so in our public [POEDITOR project](https://poeditor.com/join/project?hash=A5I1lpqRes).

## Premium Versions ##

A premium version of this plugin is available on the WPCloudDeploy website.  You can view the additional features offered on the [pricing page.](https://wpclouddeploy.com/pricing/)

Premium features include:

* WP Multisite support
* REDIS support for WP
* Bring your own server
* Sell server subscriptions with WOOCOMMERCE
* Sell WP Site subscriptions with WOOCOMMERCE
* Virtual Providers (support multiple accounts on each cloud server provider)
* Server Sync
* Support for additional cloud server providers: EC2, LIGHTSAIL, LINODE, VULTR, UPCLOUD, HETZNER, EXOSCALE, GOOGLE, AZURE and ALIBABA
* Powertools which include features such as summary dashboards and charts, periodic server snapshots and more.

## Creating Extensions ##

[Articles on creating WPCD extensions](https://wpclouddeploy.com/category/tutorials/)

## About Branches ##

We generally use two branches - the MAIN branch is the most stable version and is suitable for use in a production environment.

The DEV branch is usually stable but not all existing features have been fully regression tested.

If you are starting a project of your own and you'd like to start with a version containing all the latest features, you can usually use the DEV branch.  

The DEV branch is usually promoted to the MAIN branch every 30 to 60 days.

We commit EVERYTHING to our repository so there is no pesky build process necessary.  You can just download the zip from either branch and upload to your WordPress site using the ADD NEW option in the WordPress plugins screen.

## Plugin History ##

Development was started in 2019 and the first release to the public became available in March 2020.  Since then there have been numerous updates, with an average pace of one update per month.
The plugin was always open-source but until October 2021, the source was only available to purchasers.

In October 2021, Version 4.10.8 was pushed to a public Github repository.  Now, anyone can just install the core plugin and immediately get WordPress server management functions that rival SaaS systems.

Note: Even though the entire git development history isn't available on github, the change log below still reflects the full release history.

## Release Notes ##

[Friendly Release Notes](https://wpclouddeploy.com/category/release-notes/)

## Change Log ##
4.16.0
------
* New: Save any IPv6 address that DigitalOcean assigns to servers.  Request IPv6 addresses on all new DigitalOcean servers.
* New: Add install and support links to the plugin entry in the WP plugins list.
* New: Add code to display the change log directly in the plugin list when a new version is available.
* New: WPAPP - Add global option to enable delete protection on all new sites.
* New: WPAPP - Add new feature security options in SETTINGS->APP:WORDPRESS SECURITY to control who can see the EMAIL and NOTES/LABLELS/LINKS checkboxes.
* New: WPAPP - New tab to allow updating certain values in wp-config.php.
* Tweak: Make sure that certain messages only display to users who pass the wpcd_is_admin() check instead of all users.
* Tweak: WPAPP - Disable old PHP versions on new servers by default.  Add controls to allow admin to re-enable them on a server-by-server basis.
* Tweak: WPAPP - Preference ipv4 over ipv6 (update bash create-server script)
* Tweak: WPAPP - Validate certain fields on the server monit/healing tab before allowing an operation - this prevents monit from throwing an error because it cannot parse its configuration files.
* Tweak: WPAPP - Updated the list of WP versions to add wp 5.9.1.
* Tweak: WPAPP - Add new notification type to handle upcoming features in the POWERTOOLS add-on.
* Tweak: WPAPP - Display the final status of PHP versions as they are being installed when the server is provisioned.
* Tweak: WPAPP - Collect the default PHP version for the server.  Show a notice in the health column if it's not set to 7.4.
* Tweak: WPAPP - Remove extraneous text on the server backup tab.
* Tweak: WPAPP - Better validation of certain fields on the backend before a site is created.
* Tweak: WPAPP - New sites will default to allowing only minor automatic updates to core by setting the WP_AUTO_UPDATE_CORE value in wp-config.php to 'minor'.
* Tweak: WPAPP - New sites will explicitly default to 128M for WP_MEMORY_LIMIT in wp-config.php. 
* Tweak: WPAPP - New sites will explicitly default WP_MAX_MEMORY_LIMIT to 128M, down from the default of 256M because the default PHP worker memory_limit is set to 128M.
* Tweak: WPAPP - New sites will explicitly default to setting CONCATENATE_SCRIPTS to false in wp-config.php.
* Tweak: WPAPP - Add option to remove sFTP users when a site is deleted.
* Tweak: WPAPP - Add option to treat 'journalctl -xe' output as script failures (experimental).
* Tweak: WPAPP - When a site is cloned and the root domain of the clone is the one that is set up as the cloudflare temp domain, we will now automatically create the DNS for the clone at Cloudflare.
* Tweak: WPAPP - Only show the activate/deactivate option on the MISC tab when a site is disabled.  Hide all other options when the site is disabled.
* Fix: WPAPP - Staging and Cloned sites did not carry-over the metas that indicate the status of the various caches.
* Fix: WPAPP - Individual toggle switches on monit components were not working - only the 'all' switches did what they were supposed to do.
* Fix: WPAPP - Fixed an issue with filters on the server and site lists when a different language other than English is used.
* Fix: WPAPP - Typo on site TOOLS tab - reset permissions description should say 664 for files, not 644.
* Fix: WPAPP - Hide a spurious error message when scheduling a site sync process.
* Fix: WPAPP - The NEW SERVER RECORD button was not showing up in all cases where it should.
* Fix: WPAPP - The ADD NEW APP RECORD button was not showing up in all cases where it should.
* Fix: WPAPP - The default color for the PHP 8.1 label in the sites list was not consistent with the colors used for the PHP 8.0 label.
* Fix: WPAPP - The server tools tab would throw an error if you tried to set the default PHP version to 8.1.
* Fix: WPAPP - We weren't warning user when they entered the '&()' characters in certain fields where they are invalid.
* Fix: WPAPP - Some minor grammar errors.
* Dev: WPAPP - New filter to allow providers to add text to any error messages when a server immediately fails to deploy.
* Dev: WPAPP - New action hook on the copy-to-existing-site action (wpcd_app_wordpress-app_before_action_copy_to_existing_site).
* Dev: Integrate the wisdom plugin.
* Dev: Update to latest version of metabox.io components.
* Dev: Add New filter hook - wpcd_get_active_cloud_providers.
* Dev: WPAPP - Add New filter hook - wpcd_wordpress-app_show_deploy_server_button.
* Dev: WPAPP - Add filters to allow all the key fields on the create-server popup to be accessible to developers.

4.15.0
------
* New: Add support for enabling/disabling DigitalOcean image backups when the server is created.
* New: Add support for setting tags at DigitalOcean when server is created.
* New: WPAPP - Add function to update certain WP site options.
* New: WPAPP - Add function to change email address and password for an existing site user.
* New: WPAPP - Add function to add a user to a site.
* New: WPAPP - Add option for 2 minute and 3 minute linux crons.
* Tweak: WPAPP - Move the ADD WP ADMIN function to a new WP SITE USERS tab.
* Tweak: WPAPP - Change the default order of the array used to list UBUNTU versions so that 20.04 is first.
* Tweak: WPAPP - When removing a site make sure we remove entries in the wp-backup.conf and wpcron.txt files.
* Tweak: WPAPP - Updated list of WP versions.
* Tweak: WPAPP - New servers now install wp-cli 2.6; optional upgrade function to allow upgrades to wp-cli 2.6.
* Tweak: WPAPP - Disable PHPMyAdmin if certain components of the 6G and 7G firewall are enabled.
* Tweak: WPAPP - Remove some extraneous text in the 6G and 7G firewall tabs.
* Dev: WPAPP - Add_admin_user for a site action can now be called directly via an action hook.
* Dev: Update the metabox.io tabs add-on to the latest version - fixes a horizontal tab overflow issue.

4.14.0
------
* New: Add option to hide the owner column in the server list from non-admins.
* New: Add option to hide the owner column in the site list from non-admins.
* New: Add option to hide the server name data from the server compount column in the app list.
* New: WPAPP - Add function to allow server resize for the DigitalOcean provider.
* Tweak: WPAPP - Do not show the interval field when CRON is enabled.  This change makes it less confusing as to the process for changing cron intervals.
* Tweak: WPAPP - Significant changes to how crons work especially when multiple sites are installed on a server.
* Tweak: WPAPP - Updated redis script has better ability to detect when redis fails to install properly.
* Tweak: WPAPP - Move app tab permissions for teams into their own column on the edit teams screen.
* Tweak: WPAPP - Added some prophylactic checks around post types before deleting log posts.
* Tweak: WPAPP - Set individual limits for each log.
* Fix: WPAPP - Clone site tab was using the incorrect permissions name under teams.
* Fix: WPAPP - Search on ssh and other log screens would sometimes return results from other post types.
* Dev: WPAPP - Various fixes to the existing REST API endpoints.
* Dev: WPAPP - New REST API endpoints for changing domain and cloning sites.
* Other: Minimum version of WP is now 5.4.

4.13.0
------
* New: WPAPP - Add option for HTTP AUTHENTICATION for just the wp-login page.
* New: WPAPP - Add option to the TWEAKS tab to allow changing file upload size.
* New: WPAPP - New servers will install PHP 8.1 as part of the PHP suite of components.
* New: WPAPP - Explicit security options to disable any tab when the user is not an admin and not an owner of the site or server.
* New: WPAPP - Option to remove fields from the SITE SUMMARY compound column in the apps/sites list when the user is not an admin.
* New: WPAPP - Option to remove long winded CACHE explanation text on the cache tab.
* New: WPAPP - Option to remove long winded explanation text on the change domain tab.
* New: WPAPP - Option to remove additional statistics text on the site statistics tab.
* New: WPAPP - White label tab.
* New: WPAPP - Option to change some documentation links to your own link - see the white label tab.
* New: Option to remove fields from the SERVER compound column in the apps/sites list when the user is not an admin.
* Tweak: Update metabox.io components to latest versions.
* Tweak: Add two buttons to check for updates and validate licenses on the license page - better than the checkbox & save method.
* Fix: WPAPP - An issue where CRONS were firing off emails if the smtp gateway was installed. 
* Fix: WPAPP - Found a few more strings that were not being sent through the translation functions.
* Fix: WPAPP - When doing a QUICK DOMAIN change, also change the post title of the CPT record.
* Fix: WPAPP - Sometimes when running long transactions on a server record, the UI "terminal" didn't update properly from the log files.

4.12.0
------
* New: WPAPP - Add support for snapshots in notifications.
* Tweak: WPAPP - Some email notifications can now use tokens in the subject line. (Thanks vladolaru)
* Tweak: WPAPP - Make sure we run an apt update before gathering other info in script 24-server_status.
* Tweak: WPAPP - Increase the number of chars visible in the message column in the notification list - from 100 to 150 chars.
* Tweak: WPAPP - Made a protected function public so that it could be accessed elsewhere.
* Tweak: WPAPP - Remove flag that indicated support for deleting snapshots in the DigitalOcean provider - unfortunately DO does not support it reliably.
* Fix: WPAPP - An issue with WP-CLI where it threw weird compilation errors unless all linux updates were run.
* Fix: WPAPP - Upgrading the WP-CLI after initial installation would not work on servers where the root user was a sudo user instead of actual 'root'.
* Fix: WPAPP - Two fields on the settings screen would overflow their border on smaller screen sizes.
* Fix: WPAPP - An incorrect action-hook callback name.
* Fix: WPAPP - Check the value of a variable in the callbacks tab to make sure it's not a wp_error object before using it.
* Dev: WPAPP - Encodes fields using encodeURIComponent on the front-end and decodes on the backend - adds stronger support for certain special characters. (Thanks vladolaru)
* Dev: WPAPP - New logic for menu - the current menu item / screen is now highlighted.(Thanks vladolaru)
* Dev: WPAPP - Fix some incorrect return types and removes redundant use of 'echo' when using the wp_send_json_error(). (Thanks vladolaru)  
* Dev: WPAPP - Added new tests for REST API.
* Other: Include a GNU V2 license file.

4.11.1
------
* New: WPAPP - Added ability to edit the domain name in the advanced metaboxes for a site - sometimes needed by tech support.

4.11.0
------
* New: WPAPP - Foundation for REST API.
* New: WPAPP - Add options to automatically run callbacks and other actions after a server has been deployed.
* Fix: WPAPP - Deploying a server sometimes failed because SNAP REFRESH CORE returned a spurious error message from the server.
* Fix: WPAPP - Found a few more strings that were not being sent through the translation functions.
* Dev: WPAPP - Added filter wpcd_crons_needing_active_check.

4.10.10
------
* Fix: WPAPP - The server provider listed at the top of an app was not respecting the alternate provider name if it was set.
* Fix: WPAPP - Unable to get SSL certificate for Monitorix.

4.10.9
------
* Fix: WPAPP - There was an issue opening and closing ports when using servers with SUDO admins.
* Fix: WPAPP - A few strings were not being sent through the translation functions.

4.10.8
------
* Fix: WPAPP - A few strings were not being sent through the translation functions.

4.10.7
------
* Fix: WPAPP - An issue where mismatched data types prevented deleting a server or app post if the user was not an admin.
* Fix: WPAPP - A few strings were not being sent through the translation functions.
* Fix: WPAPP - Site Sync - Some domains were not being synced on a schedule even when configured to do so.

4.10.6
------
* Hotfix: Resolve a Network Activation Issue.

4.10.5
------
* New: WPAPP - Add option to schedule a daily site sync to a destination server.
* New: WPAPP - Add count of notes to server and app list.
* New: WPAPP - Add options to control who can see and use individual taxonomy items in server and app groups.
* New: WPAPP - Add global defaults for the smtp gateway
* New: WPAPP - Filters to make it easier to search the pending-task log.
* New: WPAPP - Pending task log / background log items will be automatically tagged as failed after 4 hours.
* Tweak: WPAPP - Better logging of errors if we can't get sshkey details (or server details while attempting to get sshkeys.)
* Fix: WPAPP - invalid variable when installing callbacks via the bulk actions menu.

4.10.1
------
* Tweak: WPAPP - Force a services status refresh whenever the UFW firewall toggle is flipped.
* Fix: Logic errors introduced with strict equality tests.
* Other: Clean up text on the cache tab for sites. Resolve some phpcs linting issues.
* Other: Updated the EDD license class to the latest version.

4.10.0
------
* New: WPAPP - Can now copy a site to another site, overwriting it.  Provides the basis for "staging" sites.
* New: WPAPP - Nightly callbacks now push the number of themes and plugin updates pending for each site.
* Enh: WPAPP - Added global theme and plugin exclusions when updating sites.
* Enh: WPAPP - After sites are updated, check to see if certain well-known cache plugins are installed and activated.  If so, attempt to use their wp-cli commands to flush their caches.
* Enh: WPAPP - Better support for copying sites between 'root' and 'sudo' logins. Eg: from to/from aws server with "ubuntu" users and digital-ocean servers with "root" users.
* Tweak: WAPP - Add confirmation prompts to all custom bulk actions on server and site list screens.
* Tweak: WPAPP - Started the process of removing extraneous text from tab screens, especially above buttons. Only a couple of tabs have been done in this version.  We'll do additional ones in future versions.
* Tweak: WPAPP - Update list of WP versions to show the latest minor versions.
* Fix: WPAPP - An issue when listing backups and there was no backup folder created yet.
* Fix: WPAPP - Site Sync tab would throw an sprintf error when we were trying to log errors
* Fix: WPAPP - When searching and replacing using wp-cli, force search of ALL tables with the wp prefix instead of just the ones registered with wpdb.
* Fix: WPAPP - Remove reference to an undeclared variable in a tab file.
* Fix: WPAPP - An issue where the server name in the filter on the server and site screens did not show the proper provider name when an alternate name was set up in the provider settings screen.

4.9.0
------
* New: WPAPP - Options to update plugins and themes on sites including under the bulk actions menu.
* Tweak: WPAPP - include the MYSQL diskspace used when showing diskspace used in a site's statistics tab.
* Tweak: WPAPP - change the format of the output string used when showing diskspace used in a site's statistics tab.
* Tweak: WPAPP - store the last value used when manually pruning backups at the server level.
* Tweak: WPAPP - logic when showing/hiding the BULK TRASH option for sites (needed as part of the option to bulk update themes and plugins on sites)
* Tweak: Add bulk options to pending log list to change state.
* Tweak: WPAPP - Remove a header on the FAIL2BAN tab to free up a bit of real estate on the screen.
* Fix: WPAPP - resolve references to some stray domain variables in the server services.php file.
* Fix: Add WPAUTOP to body fields in command log and error log screens.
* Fix: WPAPP - The pending log CRON loop needed to be smarter about identifying and handling events on server posts vs app posts.
* Fix: VPN & BASIC SERVER - Some text fields in settings would overflow the container.  Now set to 100% max width by default.

4.8.2
------
* New: WPAPP - Options to turn off and turn on a server.
* Dev: WPAPP - Return the snapshot id to the calling program when a snapshot is registered.
* Dev: WPAPP - Add internal option to register the web server used on new server records.

4.8.1
------
* New: Add concept of "Feature Flags" for server providers.  Current feature flags relate to backups, snapshots and tags.
* Tweak: WPAPP - Show the number of non-security updates to health column on server list screen.
* Fix: WPAPP - Correct the spelling of a word in a message shown when deploying new servers.
* Dev: Add New Filter to allow devs to hook into the MISC tab on the settings screen: wpcd_settings_after_misc_tab.

4.8.0
------
* New: Support for snapshots in the core provider and implemented in the DigitalOcean provider.  Other providers will be supported at a later date.
* New: WPAPP - Allow downloads of additional server logs related to upgrades / unattended upgrades.
* New: WPAPP - Give user the option to run server updates almost immediately by scheduling a one-off cron process to run updates within 1 minute of the user's request.
* New: WPAPP - Add option to install callbacks from the server list BULK ACTIONS drop-down.
* New: WPAPP - Add option to soft reboot servers from the server list BULK ACTIONS drop-down.
* New: WPAPP - Add option to remove the Malware Scanner Metas.
* Tweak: WPAPP - The RUN NOW feature on the server callback tab has been modified to schedule a background process to run within 1 minute of the user's request.  This avoids the user having to potentially experience an AJAX error.
* Tweak: WPAPP - When removing CALLBACKS, make sure to remove the associated META as well.
* Tweak: WPAPP - Added server dates to the output at each feedback point when installing a new server.
* Tweak: WPAPP - Added checkpoint groups in BASH script #1 to help when the script runs multiple times on the same server.
* Fix: WPAPP - some logic errors when showing/hiding the BULK TRASH option for servers
* Fix: Allow admin to increase timeout used when provisioning a server on very very slow VMs (such as AWS Lightsail in Mumbai).

4.7.2
------
* Fix: An issue with SITE SYNC scripts that prevented sites from working when pushed to a server (#233)
* Fix: Spelling of the WPCloudDeploy product name in certain source code files
* Tweak: Add special validation rules for the server name when using the HiVelocity Servers

4.7.1
------
* Fix: WPAPP - (Security) Escape link and link description fields on app screens.
* Fix: WPAPP - (Security) Validate some URL fields to be valid URL format on the front-end.
* Fix: WPAPP - (Security) Make sure to validate SLACK's SSL certificate.
* Fix: WPAPP - (Security) Prevent a password from being entered into the log tables.

4.7.0
------
* Fix: WPAPP - Cloning a domain did not include all the new NGINX directives we added to new sites in V4.6.x
* Fix: WPAPP - Adding a new subsite domain (in multisite) did not include all the new NGINX directives we added to new sites in V4.6.x
* Fix: WPAPP - Changing a domain from a TLD to a subdomain would mangle the PHP configuration files.
* Fix: WPAPP - server and site owners could not be updated unless the advanced metaboxes option was turned on.
* Fix: WPAPP - resolved an issue where auto-ssl wasn't toggling the meta values on the post record even though ssl was successful.  The SELL SITES WITH WOOCOMMERCE plugin needs to be updated as well to be compatible with this fix.
* Fix: WPAPP - capitalization issue on a message on the CREATE NEW WORDPRESS SITE popup screen.
* Fix: WPAPP - A minor issue with the server crm notifications
* Fix: WPAPP - An issue when users try to logout from a wpapp they would get an error. Resolved by increasing the amount of login page visits allowed in 1 second. Applies to new sites only but NGINX config can be updated manually on existing sites if desired.
* Fix: WPAPP - An issue when creating a new server that threw a "no such file or directory" error in the bash script. (#229)
* Fix: WPAPP - An issue where a password error thrown when creating an sFTP user was not being trapped properly by a bash script. (#230)
* Fix: VPNAPP - An issue where the WooCommerce module in the prototype VPN APP was affecting the output of the WC WPAPP CART if the VPN APP was also enabled.
* Dev: Update PHPSecLib to latest major version - now using the 3.x branch instead of 2.x.
* Dev: Reformat files using PHPcs and fix some of the more basic formatting and wp standards issues that were identified.
* Dev: Add ability to cache other items in server providers via filter.  Started process of standardizing caching functions in server providers by introducing new functions in the parent provider class.
* Misc: WPAPP - Update list of WP versions.

4.6.7
------
* Tweak: WPAPP - Remove the "Show Old Logs" dropdown from the server list.  Can still be shown by turning on an option in settings.
* Tweak: WAPPP - Remove the "Install WordPress" link under the title column.  It's redundant.  But it can still be shown by turning on an option in settings.
* Fix: WPAPP - Fail2ban - check an array to make sure it's not empty before attempting to iterate through it - this prevents a PHP warning in debug.log
* Fix: WPAPP - An issue with restarting NGINX with wildcard certificates
* Fix: VPNAPP - Call the proper function to get the list of providers
* Fix: BASICSERVER - Call the proper function to get the list of providers

4.6.6
------
* Fix: WPAPP - Enabling Memcached was causing websites to crash.
* Fix: WPAPP - Sending emails from the server and site screens was allowed even if the FROM name and reply-to email addresses were blank.

4.6.5
------
* Tweak: WPAPP - Minor descriptive text update on the LetsEncrypt SNAP upgrade screen.
* Fix: WPAPP - An upgrade process reported failure even when it was actually successful.

4.6.4
------
* Tweak: Better text and prompts when scheduling emails from the servers and sites.
* Tweak: Warn when sending emails from servers and sites and the reply-to email address or recipient name is blank.
* Fix: WPAPP - Hide an irrelevant error message that was displayed during the 7G upgrade process when a file wasn't present.

4.6.3
------
* New: Add option to remove Digital Ocean as a provider.
* New: WPAPP - 7G Firewall
* Tweak: WPAPP - Add upgrade routine to make sure that the 7G firewall files are installed.
* Fix: WPAPP - Make sure that the NGINX server can still restart after a site has been added even if the 7G files aren't yet present.

4.6.2
------
* Tweak: WPAPP - Do not disable ignore_user_abort php function on new installs.
* Tweak: WPAPP - Some nginx zip parameters related to caching.
* Dev: Add the bash scripts for the 7G Firewall. UI to come later.

4.6.0 / 4.6.1
------
* New: WPAPP - Basic support for multisite with subdirectories (requires new version of MULTISITE add-on).
* New: WPAPP - Basic support for wildcard ssl for multisite (requires new version of MULTISITE add-on).
* New: WPAPP - New backup option to schedule periodic backups of important configuration files every four hours.
* New: WPAPP - Option to set up simple URL redirection rules for sites.
* New: WPAPP - Option to enable HTTP2 for sites. 
* New: WPAPP - Admins now have the option to change PHP workers without logging into the server via ssh.
* New: WPAPP - A UI is now provided for many commonly used NGINX tweaks.
* New: WPAPP - Option to install GoAccess.
* New: WPAPP - Option to set a root password.
* New: WPAPP - New permissions for 5 server tabs - fail2ban, upgrade, ssh_console, users & sshkeys.
* New: WPAPP - WP-CONFIG option to remove certain server tabs from the screen even when the user is an author.  This helps in SaaS situations where the post author is the buyer but you still don't want them to access those tabs.
* New: WPAPP - Add option to hide the NOTES column in general tab in the SERVER screen.
* New: WPAPP - Add option to hide the instance id from the server list screen. Useful for SaaS situations where you don't want the customer to know the ID for security reasons. Will still always show for admins.
* New: WPAPP - Show initial user id and password under the SITE MISC tab.
* New: Add admin-only notes section for servers so that non-admin users can have notes as well.
* Tweak: WPAPP - If a site is disabled, show a disabled message in most site tabs instead of allowing the admin to perform operations that might fail because of missing nginx config files.
* Tweak: WPAPP - Add option to select drop-down for WP 5.6.2
* Tweak: WPAPP - Make sure domain names are always lowercase when changing domains or cloning a site.
* Tweak: WPAPP - Lock down the ability to change advanced php.ini options to system admins.
* Tweak: WPAPP - Lock down the ability to reset the restricted set of PHP functions to system admins.
* Tweak: WPAPP - When setting a "common" php option, validate it against a known good list.
* Tweak: WPAPP - Hovering over the "Install WordPress" button now has a better background color that matches the WPCD brand.
* Tweak: WAPPP - Make the Apps On Server link on the app screen a button instead of just a link.
* Tweak: WPAPP - Add option to remove the email gateway.
* Tweak: Better error reporting from DigitalOcean when a machine size is not available in a region.
* Tweak: Make sure that non-admin users can see the description, links and other similar tabs on the server screen.
* Tweak: Add disk size to the digital ocean sizes select drop-down.
* Fix: WPAPP - Sometimes a required entry for page caching was not automatically added to the wp-config.php file.
* Fix: WPAPP - Prophylactic code added to the bash scripts to prevent user names from being greater than 32 chars
* Fix: WPAPP - A function was returning incorrect data about the existence of a domain
* Fix: WPAPP - The primary server callback might sometimes fail if there's a space in the UPTIME data.
* Fix: WPAPP - The toggle for turning on and off the UFW firewall never really worked - the toggle logic was actually inversed.
* Fix: WPAPP - Silly bug in the push-commands.php file where we used add_action instead of do_action.
* Fix: WAPP - When changing the PHP version on the SERVER TOOLS tab, we never set a meta that showed the new version.

4.5.5
------
* Tweak:  WPAPP - Add option to control whether or not to automatically delete DNS entry at Cloudflare when a site is deleted.
* Tweak: WPAPP - Add option to select drop-down for WP 5.6.0 (instead of just 'latest')
* Fix: WPAPP - An edge case where the DNS entry at Cloudflare was not being deleted when a site was deleted.

4.5.4
------
* Tweak: Always clear ALL provider caches when saving settings. This can help prevent confusion after entering invalid api keys but can slow down refreshing the settings screen if there are a lot of providers..

4.5.3
------
* New: WPAPP - Add option to restart PHP services for the server.
* Tweak: WPAPP - Delete DNS from cloudflare when deleting site from the site list.
* Tweak: WPAPP - Added up_time and cpu_usage (since server start) to the push data received from the server every 24 hours. Requires re-installation of server callbacks but is not mandatory if you do not need these meta items.
* Fix: WPAPP - Another edge case fix for downloading log files.

4.5.2
------
* Fix: WPAPP - fix issue where domain change would mangle the new domain nginx configuration files in certain edge cases.

4.5.1
------
* New: Option to change the description of a provider
* New: WPAPP - Functions to allow deleting DNS entries at cloudflare - used by the WC add-ons.
* Dev: WPAPP - Action hooks before deleting a site.

4.5.0
-----
* New: WPAPP - Cloudflare integration for temporary domains
* ENH: Unlimited Server "Size" Labels
* Dev: Internal Support For Additional WC Related Functions
* Dev: Added support for background pending tasks
* Fix: WPAPP - Some servers would not notify the plugin after a reboot because network services were not started.

4.4.2
------
* Fix: WPAPP - cron processes weren't loading plugins which meant that some wp crons ran only once and then exited.
* Fix: WPAPP - domain change sometimes left some stray ssh related folders around.

4.4.1
------
* Dev: Make a private function public so that it can be called from certain add-ons.
* Dev: Remove a WC related JS file that was no longer needed - moved it to its add-on instead.

4.4.0
------
* New: WAPPP - Add option in a SERVER's POWER tab to schedule a soft server restart.
* Tweak: WPAPP - When downloading log files use .txt extensions instead of .log. Some server configurations and firewalls block files with .log extensions.
* Fix: WPAPP - An issue with the backupV2 script caused the restore process to not work properly.
* Fix: WPAPP - Number of parameters in an action hook was incorrect.
* Fix: WPAPP - Extraneous error message when a non-admin performed an action on a site without having permissions to the server.
* Fix: WPAPP - Remove an extraneous message in debug.log when no teams were set up.
* Fix: WPAPP - Fix REST PERMISSIONS CALLBACK warning introduced in WP 5.5.
* Other: WPAPP - Breakout the WooCommerce functionality into it's own add-on to reduce the code size of the core plugin.
* Other: WPAPP - Add code to support certain future WooCommerce enhancements.

4.3.0
------
* New: WPAPP - Healing/Monit
* New: Centralized notifications
* New: Preliminary support for Ubuntu 2004 LTS
* New: WPAPP - Add option to allow each server to connect with a custom ssh key-pair after being provisioned.
* New: WPAPP - Simple db search and replace
* New: WPAPP - Install PHP 8.0 on new servers and make sure related scripts account for it.
* New: WPAPP - Add an option to the SITE Backups tab to delete all local backups for the site
* New: WPAPP - Add an option to the SITE Backups tab to prune local backups for the site 
* New: WPAPP - Add options to restore just wp-config or the nginx configuration file.
* New: WPAPP - Add an option to never save local backups.  While this is not a recommended practice, some users have requested it to save disk space.
* New: WPAPP - Add an option to the SERVER Backups tab to delete all local backups for all sites on the server
* New: WPAPP - Add an option to the SERVER Backups tab to prune local backups for all sites on the server
* New: WPAPP - Add disk-free, free-ram, restart status, malware scan results & security updates information to the server list
* New: WPAPP - Add a "System Users" tab to allow SERVER admins the ability to set a password/key and break them out of their jails
* New: WPAPP - Add an option under the server tools tab to test REST API callbacks into the plugin.
* Tweak: Certain metaboxes should not be editable by regular users - only admins should be able to edit them.
* Tweak: Add drop-down filter fields to the VIRTUAL PROVIDERS screen.
* Tweak: Prevent certain data from being logged to debug.log. Prior to this change we were only cleaning the data before logging it to the logs in the database).
* Tweak: Moved the help screen to its own dedicated menu option.
* Tweak: WPAPP - Warn when using problematic characters in passwords and other fields while deploying new servers and sites.
* Tweak: WPAPP - Limit domain names to 32 chars to prevent some nasty side-effects related to usernames in the bash scripts.  Will need to rework some things for longer domain names in the future.
* Tweak: WPAPP - Added additional feedback points for when the server provisioning process might have failed or is taking an inordinately long time.
* Tweak: WPAPP - Added new action hooks that are available after a server is deployed or a site is provisioned.
* Tweak: WPAPP - Modify the reboot server option to explicitly shutdown the MYSQL service first before rebooting.
* Tweak: WPAPP - Better column wrapping on server and site detail screens below 1600px width.
* Dev: Quite a few new hooks and filters as well as a type of "custom fields" function to make linking front-end and back-end bash scripts easier for 3rd party developers.
* Fix: WPAPP - Make sure that when a user requests an operation on an sFTP user, that the user belongs to the designated site (security)
* Fix: WPAPP - Deal with consistent false positives on 3 files in the malware scanner 
* Fix: WPAPP - Make sure new servers have UFW installed on it before attempting to configure it.
* Fix: WPAPP - Fixed an issue where we weren't detecting when a particular cron was inactive and failed to warn the user about the potential issue.
* Fix: WPAPP - Sometimes deleting an sFTP user left stray metas behind which caused them to still show up in certain drop-downs.

4.2.1
------
* Fix: WPAPP - link was getting duplicated in the settings screen.
* Tweak: WPAPP - Added a filter for said link - wpcd_cloud_provider_settings_important_private_key_notes

4.2.0
------
* New: WPAPP - Add option to reset file permissions for a site.
* New: WPAPP - Added a new POWER tab to allow restarting of servers without needing to log into the providers' console.
* New: WPAPP - Added charts to the server statistics tab.
* Tweak: Update the EDD software licensing class library to the latest version to support WP native auto-updates and tweaked our code to work with the new class library.
* Tweak: Add warning if default permalinks are in use.
* Tweak: Add option to hide the "local host" warning.
* Tweak: WPAPP - Show the monthly cost and other additional information in the server sizes dropdown for digitalocean.
* Tweak: WPAPP - Do NOT show digitalocean server sizes that are marked as "unavailable".
* Tweak: WPAPP - Change the size of the default PHP buffers for new sites.
* Tweak: WPAPP - Change the version numbers in the drop-down list for WordPress when creating a new site.
* Tweak: WPAPP - Update the description for the private key in the settings screen.
* Tweak: WPAPP - Add the --skip-plugins argument to many of the wp cli calls.  This prevents plugins from loading and reduces conflicts that could cause wp-cli to fail.  Most wp-cli commands don't need the plugins loaded.
* Tweak: WPAPP - Add additional internal fields to the internal data metabox on the SERVER detail screen so that we can easily re-create a server record if it's accidentally deleted.
* Fix: Weird conflict with WooCommerce.  If WC was installed BEFORE WPCD, activating WPCD resulted in a WSOD because the "rwmb_meta" did not exist.
* Fix: WPAPP - When changing domains or cloning a site, make sure that database is set to use HTTP if certificate cannot be automatically issued.
* Fix: WPAPP - An issue where we were not checking to see if a valid array or object was returned from a function call before using said object/array.
* Security Fix: WPAPP - Prevent logging of sFTP passwords to database logs

4.1.0
------
* New: Enforce requirement for PHP 7.4.
* New: WPAPP - Option to select language when installing WordPress
* Tweak: Security - improve encryption algorithm by randomizing the initialization vector.
* Tweak: Remove delete links from virtual cloud provider screens if the virtual provider is in use on any servers.
* Tweak: Add warning on settings screen to any provider that is in use by a server.
* Tweak: Make a virtual cloud provider title a required field.
* Tweak: Security - warn user if they are using the example security key from our documentation.
* Tweak: WPAPP - security - Check for permissions before popping up form for creating server and site.  Before we would check security after the form was submitted.
* Tweak: WPAPP - security - Check for basic level permissions earlier in the ajax_app() function.
* Tweak: WPAPP - Make sure that, when installing REDIS we request version specific php modules instead of the meta package (which could install unwanted packages).
* Tweak: WPAPP - Make sure that the INSTALL WORDPRESS buttons do not show up on records that are in the Trash.
* Tweak: WPAPP - Make sure that the INSTALL WORDPRESS buttons do not show up on records that are in the Trash.
* Fix: Security - Escape output when viewing ssh logs to prevent potential Unauthenticated Stored Cross-Site Scripting exploits.
* Fix: Security - Escape output when viewing error logs to prevent potential Unauthenticated Stored Cross-Site Scripting exploits.
* Fix: Security - Escape output when viewing command logs to prevent potential Unauthenticated Stored Cross-Site Scripting exploits.
* Fix: Security - Escape certain output on the app detail screen to prevent potential Unauthenticated Stored Cross-Site Scripting exploits.
* Fix: Security - Check ajax permissions when purging logs.
* Fix: Security - Remove passwords from log tables.
* Fix: Use the function get_active_providers in place of get_providers in a number of places across all apps.
* Fix: Wrapped various output vars in certain template files with esc_url and esc_html commands.
* Fix: A few text strings were not wrapped in translation function calls.
* Fix: WPAPP - security - wrap all the fields used for creating a new site with escapeshellargs.
* Fix: WPAPP - Security - use escapeshellargs on all sftp parameters.
* Fix: WPAPP - When copying a site to another server, remove any app post records for that server that references that site.  This prevents duplicate app records for the site that point to the same server.
* Fix: WPAPP - Remove extra request for the mbstring php module when installing new servers.
* Fix: WPAPP - Misc fixes to the 08-backup.sh bash script for removing orphaned backups and showing orphaned backups.

4.0.1
------
* New: WPAPP - Add server-level tool to remove php 8.0 RC01 and update image magic php module to use php version-specific packages.
* New: WPAPP - Add server-level tool to reset default PHP version for the server.
* Fix: WPAPP - Update 72-destination BASH script to use php version-specific packages for imagick module.

4.0.0
------
* New: WPAPP - Allow admins to configure purchases of WP cloud servers in WooCommerce & WooCommerce Subscriptions (Experimental)
* New: Introduction of Cloud Provider Aliases (Experimental)
* New: Introduction of CUSTOM SERVER feature (Experimental)
* New: Added Malware scanning with LMD and CLAMAV
* New: Fail2Ban Command Line Script
* New: WPAPP - Infrastructure to handle server callbacks. First callback implemented that pushes data from the server to the console daily - reporting on updates that might be needed and whether the server requires a reboot.
* New: WPAPP - The server list screen now shows up to four sites that is located on the server.
* New: WPAPP - The server detail screen has a new tab that shows the list of sites on the server.
* New: WPAPP - The site list now offers options for filtering by app groups, whether the site is enabled/disabled, page cache status, object cache status and the version of PHP in use.
* New: WPAPP - The WordPress user profile now shows the list of servers and apps that a user is assigned to (if they are part of a team).
* New: The teams list now shows team members and their permissions directly in the list.  
* New: The settings screen has a TOOLS tab with a couple of options that can be triggered when directed by technical support.
* New: WPAPP - Teams now have a new permission that controls whether a user can update site PHP options.
* New: WPAPP - Add a link next to the trash link in the apps list to remove a site.
* Tweak: WPAPP - Remove data input fields from the screen after the server deployment process has started.
* Tweak: WPAPP - Remove data input fields from the screen after the wpapp deployment process has started.
* Tweak: WPAPP - Do not allow the install button to be clicked until all fields have been filled in (when deploying a new wp site).
* Tweak: WPAPP - Searching now searches the long and short server descriptions.
* Tweak: WPAPP - Server and app groups now show the colors in the main list.
* Tweak: WPAPP - JS scripts now check to make sure that fields contain valid information before attempting to creating a new WP site.
* Tweak: WPAPP - If the folder that contains the bash scripts cannot be accessed via http calls, a message is usually displayed.  Admins can now immediately ask for a recheck instead of waiting 12 hours for it to be done automatically.
* Tweak: WPAPP - When pushing a site to a new server, add some interim feedback messages.
* Tweak: WPAPP - When changing the domain for a site, we now backup the database, make the domain change in the database and rename among other things just to prevent conflicts in the future.
* Tweak: WPAPP - Applications filter bar now spans two lines since we have so many filters.
* Tweak: WPAPP - The server name at the top of the application screen metabox is now a link that takes you directly to the server.
* Tweak: WPAPP - There is now a new link at the top of the application screen metabox that takes you directly to the apps on the server.
* Tweak: WPAPP - Added another backup check for NGINX status when using the REFRESH SERVICES STATUS button.
* Tweak: WPAPP - Add warning when restoring a server from trash.
* Tweak: WPAPP - If a server has been restored from trash, add a message to the server list indicating that there is no live server and the link has been broken between the post and the server.
* Fix: The app record could not be deleted when the Trash link was used.  
* Fix: WPAPP - The data in the drop-downs on the filter bar for servers and sites did not respect the teams restrictions.
* Fix: WPAPP - an issue where an extra comma caused an error in PHP 7.2 and earlier (it worked in PHP 7.3 and later).
* Fix: WPAPP - added check in bash script #2 to ensure that no fields were left blank before attempting to create a new wp site.
* Fix: WPAPP - added check in bash script #6 to ensure that no required fields were left blank before creating or modifying sftp users.
* Fix: WPAPP - a few strings were not being wrapped with the WP translation functions.
* Fix: WPAPP - incorrect team showing in app list if the team array meta is empty (vs not being present which worked just fine).  The team array meta could be present but empty if a site is cloned.
* Fix: WPAPP - Cloning multisite WP installs did not copy the multisite meta indicator.
* Fix: WPAPP - When pushing a site to a new server, an IPV6 bug with certbot needed some special processing to workaround. Without this work-around, NGINX servers failed to restart when sites were pushed to an existing server with existing sites.
* Fix: WPAPP - Do not allow sites to be cloned over to an existing site.
* Fix: WPAPP - Do not allow domain changes that matches an existing site.
* Fix: WPAPP - Make sure that NGINX restarts when the server restarts - in the past this was not always the case.
* Fix: WPAPP - Prepare the MONITORIX script for Ubuntu 20.04.
* Dev: New filter hooks in the settings screen required to support new providers such as UpCloud.

3.0.2
------
* New: WPAPP - Added tool to clean up metas on server records if a process ever gets "stuck".
* Tweak: WPAPP - Add WordPress 5.5.1 to the drop-down list of WordPress versions.
* Fix: WPAPP - Do not allow blank data to be used when creating a WP site.

3.0.1
------
* Fix: Updated the POT file.
* Fix: WPAPP: Remove the use of the word "Provisioning" and replace with "Deploy".
* Fix: WPAPP - The Install WordPress link under the TITLE in the servers list needed to be removed while the server was being deployed or otherwise engaged.

3.0.0
------
* New: Add option to reset the list of restricted php functions for a site.
* New: Add option to remove the general settings tab.  This helps make the first tab the CLOUD PROVIDERS tab.
* Tweak: Numerous styling changes to the settings screen. Make the FIRST WORDPRESS SITE section more prominent.
* Tweak: Do not show the server sizes drop-down in the settings screen if the api key field is blank and if certain wp-config vars are not set.
* Tweak: Add an animated loader icon to the server and site provisioning screens while waiting for the install process to get started.
* Tweak: WPAPP - Numerous changes to the UI including the introduction of vertical tabs on the SITE detail screen as the default view.
* Tweak: WPAPP - Do not show the INSTALL WORDPRESS button while the server is being provisioned.
* Tweak: Remove the additional "handlebars" that WP 5.5 added to the metaboxes on the settings screen.
* Tweak: Remove permission list from the screen.  This isn't really necessary for your average admin to see.  Can be re-enabled with a WP-CONFIG entry.
* Security: WPAPP - Updated the list of restricted PHP functions - added new ones and removed a couple that popular plugins rely on.
* Tweak: WPAPP - remove setting the php session.name and sessions.referer_check as part of our default installation.  Too many plugins didn't like it.
* Fix: Missing translation text on the settings screen.
* Fix: WPAPP - an error that was showing up as raw html during the site creation process is now handled better.

2.9.0
------
* New: WPAPP - add option to scheduled backups that automatically deletes remote backups when local backups are deleted.
* New: Now includes a pre-built POT file for language translations.
* New: Load language translations from the plugin languages folder if a language mo file does not yet exist under the standard WP folder.
* Tweak: WPAPP: Lock down PHP further by disallowing some functions.
* Tweak: WPAPP: Change the way we detect if a crucial folder is readble - remove use of 'exec' function.
* Fix: Digital Ocean API now requires a waiting period after starting up a server image in order to stabalize before sending any ssh commands. 
* Fix: WPAPP - Security - PHP OPENBASEDIR was not being set in some instances.
* Fix: WPAPP - Respect team settings when populating the destination server under the COPY TO SERVER tab.
* Fix: WPAPP - Make sure that no servers show up under the COPY TO SERVER tab if the user isn't on a server team.
* Fix: WPAPP - Error out if the target server and source server are the same when using the COPY TO SERVER function.
* Fix: WPAPP - Error out if the destination server or source server is one where the user is not allowed access when using the COPY TO SERVER function.
* Fix: WPAPP - Copying a multisite installation to a new server did not copy all the ssl certificates.
* Fix: WPAPP - Changing a domain did not change all the folder permissions for the new nginx user which left them inaccessible.
* Fix: WPAPP - Cloning a site did not carry over the teams information.  
* Fix: WPAPP - Copying a site to a new server did not carry over the team information.
* Fix: WPAPP - Enabling the native LINUX cron did not fire cron for all the subsites.
* Fix: WPAPP - Remove PHP 7.0 as an option since those files aren't even being installed on new servers.
* Fix: WPAPP - Empty labels for fields in tabs were throwing notice/warning errors in the error log.
* Dev: WPAPP - Add extensible upgrade infrastructure so that when upgrade scripts are written they can be easily integrated into the codebase.
* Dev: Stamp the server and app records with the plugin version.

2.8.0
------
Skipped.

2.7.0
------
* New: WPAPP - Added option to remove site and all its local backups.  Before, removing a site did not remove the associated local backups.
* New: WPAPP - Updated backup bash script with new functions for pruning backups without running a backup process.  Command-line only functions (no UI).
* New: WPAPP - Updated backup bash script with new functions for identifying backups that exist without a corresponding site and for deleting those backups.  Command-line only functions (no UI).
* New: WPAPP - Updated backup bash script with option to delete all local backups for a site or for all sites.  Command-line only functions (no UI).
* New: WPAPP - Added server option to install Monitorix.
* New: WPAPP - Add an nginx rule to block brute force attempts on the wp-login screen. Only applies to new sites - older sites will need to have the rule manually added.
* New: WPAPP - Add some security related nginx rules to prevent access to .log files, files whose name start with a period, executing non-php scripts, executing php scripts in the upload folder, downloading files from an updraft folder if present.  Only applies to new sites - older sites will need to have the rules manually added.
* New: WPAPP - Add option to remove MemCached from the server if it's been installed.  Before you could only restart it or clear the cache - now you can remove the entire thing if it's no longer being used.
* New: WPAPP - Added the PHP version to the app list.
* New: WPAPP - Added a statistics tab to the server where a single button will run the df (disk free), vnstat and top command and display the results in a tab.
* Tweak: Hide sensitive passwords/ids in the settings screen by default.  User can click icon to display existing passwords. 
* Tweak: WPAPP - Added the PHP version to the app list.
* Tweak: WPAPP - Added cache status to the app list.
* Tweak: WPAPP - A few of the bash scripts needed to be tweaked to accomodate a future monit integration.
* Tweak: WPAPP - Added new WPCD logo to the popup used when adding a server or site.
* Tweak: WPAPP - Tightened up the conditions for showing a button on the SSH tab on the server screen.
* Tweak: WPAPP - Change the text of the ADD NEW button at the top of the apps list to say ADD NEW APP RECORD so that users don't believe that they can use it for a new WP install there.
* Tweak: WPAPP - Added new button on the settings welcome screen to link to quick-start documentation.
* Tweak: WPAPP - Show the provider at the top of the the main app metabox
* Tweak: WPAPP - Change the redis plugin used - now using the Till Kruss plugin.
* Tweak: WPAPP - Updated the list of WP versions
* Tweak: WPAPP - Remove VNSTAT statistics from the APP screen since it's data is aggregated server level data.  But we also added an option in the settings screen to put it back there if you wish.
* Fix: WPAPP - Enabling multisite SSL would always show as successful even when it sometimes failed.
* Fix: WPAPP - Email gateway would not show error message if a test email failed to be sent. 
* Fix: WPAPP - The page cache confirmation message always said "cache has been disabled" even when the action was to "enable" it.
* Fix: WPAPP - Typo on the Email Gateway screen - extra parentheses.
* Fix: WPAPP - Issue with storing custom bucket names for the all-sites backup.
* Fix: WPAPP - Issue with storing retention days for the all-sites backup.
* Fix: WPAPP - PHP Warning about invalid commands or index for certain backup operations - it didn't hurt anything but was just annoying to see in the log files.
* Fix: WPAPP - Fixes an issue with the 6G firewall where if there's a colon in the URL you get an http forbidden error.  This would affect common scenarios where you have redirects in the url such as this: http://video02.wpvix.com/logout?redirect_to=http://video02.wpvix.com/login.
* Fix: WPAPP - Issue in a server-sync script where when the script failed it would leave behind a lock file instead of cleaning it up - this prevented future syncs from running.
* Fix: WPAPP - Make sure sudo is used when running the VNSTAT command on a site.
* Dev: WPAPP - Added filter to allow logo to be replaced when adding a server or site - wpcd_popup_header_logo

2.6.0
------
* New: Add custom links to servers and sites.  This is useful if you want one-click access from the dashboard to related assets such as those located in manageWP, MainWP or even your git systems.
* New: WPAPP - Add an SSH command tab to the server detail screen.
* New: WPAPP - Add an option on the server screen to install an email gateway.  Helps with sending emails when no email plugin is installed on WordPress sites. (Experimental Feature)
* New: A label is now added to the server and app title columns when deletion protection is turned on.
* New: WPAPP - Added new wp-config option to hide help tab in the settings screen (WPCD_HIDE_HELP_TAB).
* Improved: Free up space on the application list by moving the data in the ip, provider and region columns to the server column.  Added settings option to split out into their own columns for users that prefer that.
* Improved: Add option to show the TEAMS column on the servers and site lists.  Now the default is to NOT show them since most users do not use teams.
* Improved: Added real links to documentation on the help tab on the settings screen.
* Tweak: WPAPP - Add our specific styling to the INSTALL WORDPRESS button so that when admin skins are applied to wp-admin, they button does not look grayed out.  The default is now white on green.
* Fix: WPAPP - Throw an error if adding an sFTP user that already exists in another site.
* Fix: WPAPP - The disabled label was not showing up on the site list unless the option to show the app description label was also turned on.
* Dev: WPAPP - New filter hook wpcd_settings_help_tab_text
* Doc: Added Documentation Item: Bootstrapping A WordPress Server With Our Scripts

2.5.0
------
* New: Notes and descriptions for servers and apps.
* New: Add custom WP STATES for the servers and apps.
* New: Show the server type as a state label on the server list screen if the server type is a WordPress server.
* New: Remove the "private" wp state label from the servers and apps list to make space for additional states.
* New: WPAPP - Copy sites between servers with root access
* New: WPAPP - Full server backup - setup a backup of all sites on the server.  This means that new sites will automatically be backed up.
* Improved: WPAPP - Manual backups can now be sent to a different bucket or folder.
* Improved: WPAPP - When the linux cron is enabled, it now applies to each subsite in a multisite installation.
* Improved: WPAPP - Added some third party repositories to unattended upgrades process.
* Improved: WPAPP - Remove teams from certain tables if a team is deleted.
* Improved: WPAPP - Add better error messages when certain things fail during server provisioning
* Improved: WPAPP - Remove error message about "send mail not installed" when installing a WordPress site - this was just annoying even though it didn't hurt anything.
* Fix: WPAPP - The "Save Team" text was showing up in some unexpected places.
* Dev: WPAPP - Added a couple of new filters:  wpcd_wpapp_show_install_wp_link & wpcd_wpapp_show_install_wp_link

2.4.0
------
* New: Teams Feature
* New: Add option to remove the OWNERS column from the apps screen - helps save real estate when there aren't multiple owners
* New: Multisite - add a column on the SITES screen so that admins can quickly see how many servers a sub-site has deployed.
* New: APP deletion protection
* New: Server deletion protection
* New: Add BACK TO LIST buttons on the detail custom post type screens - applies to servers, apps, teams, logs.
* New: WPAPP - Now includes a health check to make sure that certain critical files are accessible. Otherwise a warning is shown.
* New: WPAPP - New installs now create default entries for server groups and app groups
* Tweak: WPAPP - Set better line heights on input elements on popup screens so that when they wrap on smaller screens they don't smush against the elements above them.
* Tweak: WPAPP - Strip 'www', 'http', 'https' prefixes when they are entered as part of the domain name.  Affects site creation, cloning and domain name changes.
* Tweak: WPAPP - Make the WordPress version number a dropdown list when installing a new WordPress site.
* Tweak: Add a WP state label showing the application type next to the app title.
* Tweak: Allow for editing of the post title on the server custom post type screens.
* Tweak: Allow for editing of the post title on the app custom post type screens.
* Fix: WPAPP - Bug where firefox and other browsers could not be used to deploy a new site.

2.3.0
------
* New: Add notes fields on the provider settings screen - sometimes you just need to have a reminder about which api key you're using or which ssh key you're using.
* Tweak: Some web server configs did not like the use of colons in command names or file names so replaced those with three dashes instead.
* Tweak: WPAPP - add password instructions to install wordpress popup
* Fix: Command log screen - Need to check to make sure the parent post is valid before attempting to retrieve values from it.
* Fix: Make sure a returned item is an object before attempting to call a method on it.
* Fix: WPAPP - Column name being returned from an action hook function was incorrect.
* Fix: WPAPP - JS script not loading because it was attempting to load a 'select2' dependency that didn't exist.
* Fix: WPAPP - Remove SENDMAIL install from scripts because it was not interfacing properly with WordPress.
* Fix: WPAPP - check to see if an array var is set before attempting to use it.  Set a default value if its not set.
* Dev: Updated required plugins from metabox.io

2.2.0
------
* New: WPAPP - Add support scripts for the Multisite add-on
* New: WPAPP - Add links to wp-admin and the front-end directly in the site list
* New: WPAPP - Updated bash scripts
* Tweak: Encrypt API key when its stored at rest in the database. (Before we were only encrypting ssl keys).
* Tweak: Functions and options to only show (in relevant places) providers that have been configured with their api keys.
* Tweak: Add options for entries in wp-config to override some labels and items on the screen when running on multisite.
* Tweak: WPAPP - Style INSTALL buttons.
* Tweak: Don't show Digital Ocean backup provider unless configured in wp-config.
* Tweak: Add a series of wp-config options to show / hide items - see wp-config documentation on our site for more info.
* Fix: WPAPP - Command name when creating a new WP site was being duplicated when the domain name was the same.  
* Fix: Error being thrown in the debug.log file about a missing title. 
* Fix: Some menu item strings were not being translated.
* Fix: Updated a BASH script for server sync. Some cloud server providers left the authorized keys files with weird line endings which caused issues when we tried to add additional keys to that file.
* Fix: A couple dozen minor fixes related to terminology, variables and such.
* Dev: WPAPP - Add new filter wpcd_app_type_column_contents.
* Dev: WPAPP: - Add new filter wpcd_private_ssh_key_settings_desc.

2.1.0
------
* Numerous improvements and bug fixes to the WP APP.
* First PUBLIC release of the plugin.

2.0.0
------
* New App:  Deploy WordPress servers and applications
* Numerous fixes and improvements to code and infrastructure

1.3.0
------
* Added SSH Log screen
* Added error log screen
* Set scripts version at the product subscription level
* Move all items into its own menu (partial)
* New app: Basic Server 
* Revamp how applications get installed on the server after its provisioned.
* Lots and lots of code cleanup.

1.2.0
------
* Added basic versioning to the VPN app.

1.1.0
------
* Some code cleanup and rationalization

1.0.0
-----
* Initial internal release.

### Third Party Plugins & Libraries Used  ###
* Metabox.io (required_plugins)
* EDD Software Licensing (vendor)
* PHP Sec Lib (vendor)
* Chart.js (assets/js)
* Select2 (assets/js)
* magnific-popup (assets/js, assets/css)

### Running the test suite

1. Set up a localhost MySQL database with the credentials defined in `wp-tests-config.php`.
2. Run `composer install` to install all required dependencies
3. Run `./vendor/bin/phpunit` to run PHPUnit test suite.
4. Run `./vendor/bin/phpunit-watcher watch` to run test suite and 
   automatically re-run test suite whenever PHP files change.
