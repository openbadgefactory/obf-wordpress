# Clear all OBF-plugin settings sql:
DELETE FROM wp_options WHERE option_name = 'badgeos_settings';
DELETE FROM wp_options WHERE option_name = 'obf_settings';
DELETE FROM wp_options WHERE option_name = 'credly_settings';
DELETE FROM wp_posts WHERE post_type IN ('badge', 'awarding-rule', 'achievement-type');

# WARNING! WILL DELETE every png from media library:
DELETE FROM wp_posts WHERE post_type = 'attachment' AND post_mime_type='image/png' AND post_parent IN (0, 289);
# You should also delete files from wp-content/uploads to fully clear everything to a fresh-install state

# Plugin deactivate+activate restores default settings
