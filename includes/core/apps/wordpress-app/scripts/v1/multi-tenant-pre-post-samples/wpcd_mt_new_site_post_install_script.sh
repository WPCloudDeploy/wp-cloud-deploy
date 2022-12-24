################################################################################# 
# wpcd_mt_new_site_post_install_script.sh
# This is run when a site is converted to a multi-tenant site.
# Then generally happens during the woo-commerce purchase process, when the admin creates a site or when an existing site is forcibly attached to a version.
#
# If used, this file should be in the root folder of your template site.
################################################################################# 

# You have access to the full suite of vars that are run in the mt_convert_site() 
# function located in script #58.
# 
# These include:
# - $domain - The domain being converted to multi-tenant or upgraded.
# - $mt_template_domain - This is the template domain from which the version being applied was pulled.
# - $mt_version_folder - the folder where the versioned files are stored.
# - $mt_domain_files_folder - the location of the domain files (a replacement for "$domain/html")
# - $git_tag - the version the site is being upgraded to (or if a new site, the version being applied to the new site)
# - $user_name - the restricted user name under which the site is being run for web.

# Here's an example of something you do for new mt sites.
# Restrict the number of post versions allowed (set var in wp-config.php)
#    su - "$user_name" -c "wp --skip-plugins --no-color config set WP_POST_REVISIONS 5"
#
# Another example - automatically remove items from the trash after 7 days.
#    su - "$user_name" -c "wp --skip-plugins --no-color config set EMPTY_TRASH_DAYS 7"
#
# Add meta values to store in the file /etc/wpcd/$domain/$domain. You can then retrieve these later.
#    gf_set_domain_metadata "MT_TAG_VERSION" $git_tag $domain
#
# Other ideas of things you can do:
# - Do database things if you don't want to use a mu_plugin.
# - Add a custom wp-config.php file to your template site and then use this script to insert a reference to it in the site wp-config.php
#
# Stub command so you can see it when it runs even if you make no changes.
echo
echo "#######################################################"
echo "After new site install custom script running here..."
echo "Nothing to do."
echo "#######################################################"
echo