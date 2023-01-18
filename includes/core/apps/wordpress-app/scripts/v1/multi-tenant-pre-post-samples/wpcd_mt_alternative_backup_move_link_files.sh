################################################################################# 
# wpcd_mt_alternative_backup_move_link_files.sh
# This is run for new multi-tenant sites as well as for version upgrades on
# existing sites.
#
# If used, this file should be in the root folder of your template site.
################################################################################# 

# This script is run in place of ours.
# It takes the place of two functions in our scripts:
#  1. lf_mt_backup_existing_folders_before_site_conversion
#  2. lf_mt_site_conversion_link_existing_files
# These two functions are run during a new site or when switching an existing site.
# They are located in 58-git_control.txt
# But, you can replace these function calls by adding the lf_mt_site_conversion_link_existing_files.sh script to your template root folder.
# If you do this, you are completely responsible for how files get moved from the template version to your customer site(s)!
# So maybe take a look at what the existing functions are doing first before proceed.

# An example of  couple of things you can use this for.
#  1. Instead of symlinking individual plugins and themes you can choose to symlink the entire plugins and thems folder once (each)
#  2. Instead of symlinking at all you can just copy the plugins and themes folders so that each site has their own.

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
#
# Do NOT put this empty file into your template folder.  If you do, none of our critical functions will run.
# You must add code that replaces our functions!
