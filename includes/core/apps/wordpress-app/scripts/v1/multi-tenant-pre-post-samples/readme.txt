This folder contains sample scripts that can be used to run your own functions at various points of the life-cycle of a multi-tenant site.

These scripts should go into the root of your template site.

When your site is used to create a customer site, these scripts will be run as described below:

------------------------------------------
wpcd_mt_new_site_post_install_script.sh
------------------------------------------
This is run when a site is converted to a multi-tenant site.
Then generally happens during the woo-commerce purchase process, when the admin creates a site or when an existing site is forcibly attached to a version.

------------------------------------------
wpcd_mt_upgrade_site_post_upgrade_script.sh
------------------------------------------
This is run when an existing customer site is switched to a new version.
This script can be used for both upgrades and downgrades since one of the available vars is the version you're being switched to.

------------------------------------------
wpcd_mt_alternative_backup_move_link_files.sh
------------------------------------------
This script is run in place of ours.  
It takes the place of two functions in our scripts:
 1. lf_mt_backup_existing_folders_before_site_conversion
 2. lf_mt_site_conversion_link_existing_files
These two functions are run during a new site or when switching an existing site.
They are located in 58-git_control.txt
But, you can replace these function calls by adding the lf_mt_site_conversion_link_existing_files.sh script to your template root folder.
If you do this, you are completely responsible for how files get moved from the template version to your customer site(s)!
So maybe take a look at what the existing functions are doing first before proceed.

An example of  couple of things you can use this for.
1. Instead of symlinking individual plugins and themes you can choose to symlink the entire plugins and thems folder once (each)
2. Instead of symlinking at all you can just copy the plugins and themes folders so that each site has their own.

------------------------------------------
Core scripts
------------------------------------------
Core WPCD already has some scripts that can be used in certain operations.
See the full description in our docs: https://wpclouddeploy.com/documentation/wpcloud-deploy-admin/using-post-processing-custom-bash-scripts/
