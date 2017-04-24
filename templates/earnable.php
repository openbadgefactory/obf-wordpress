<?php
/*
 * Template Name: Earnable Badge
 */
//get_header();
$fullpage = true;
if ($fullpage) {
?><!DOCTYPE html>
<!--[if IE 7]>
<html class="ie ie7" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 8]>
<html class="ie ie8" <?php language_attributes(); ?>>
<![endif]-->
<!--[if !(IE 7) & !(IE 8)]><!-->
<html <?php language_attributes(); ?>>
<!--<![endif]-->
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width">
	<title><?php wp_title( '|', true, 'right' ); ?></title>
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	<!--[if lt IE 9]>
	<script src="<?php echo get_template_directory_uri(); ?>/js/html5.js"></script>
	<![endif]-->
</head>

<body <?php body_class(); ?>>
<div id="page" class="hfeed site">
<div id="main" class="site-main">
<?php
}
$ret = badgeos_obf_earnable_badge_apply_page();
if (is_wp_error($ret)) {
  echo $ret->get_error_message();
} else {
  echo $ret;
}
if ($fullpage) {
  ?>
  </div><!-- #main -->
  </div><!-- #page -->
  </body>
  </html>
  <?php
}