# Clear all OBF-plugin settings sql:
DELETE FROM wp_options WHERE option_name = 'badgeos_settings';
DELETE FROM wp_options WHERE option_name = 'obf_settings';
DELETE FROM wp_options WHERE option_name = 'credly_settings';
DELETE FROM wp_posts WHERE post_type IN ('badge', 'awarding-rule', 'achievement-type');
# Plugin deactivate+activate restores default settings
