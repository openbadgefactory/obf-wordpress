<?php
/*
 * Template Name: Earnable Badge
 */
//get_header();
ob_start();
$iniframe = badgeos_obf_earnable_badge_template_is_iframe();
if ($iniframe) {
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
        <?php wp_head(); ?> 
        <style>
            html,body,div,iframe {height:100%;}
            html {margin-top: 0px !important; }
            p {position:relative;overflow:hidden;}
            iframe {border:none;width:100%;}
            body {margin:0;padding:0;}
        </style>
</head>

<body <?php body_class('in-iframe iframe'); ?>>
<div id="page" class="hfeed site">
<div id="main" class="site-main">
<?php
  //echo $ret;
    while ( have_posts() ) : the_post();
    ?><article class="hentry"><?php
      the_content();
    ?></article><?php
    endwhile;
}
else {
  get_template_part('page');
}
if ($iniframe) {
  ?>
  </div><!-- #main -->
  </div><!-- #page -->
  <script type="text/javascript">
  jQuery(document).ready(function() {
      if(top.location != location) {
          jQuery('a, form').each(function() {
              if(!this.target) {
                  this.target = '_top';
              }
          });
      }
  });
  </script>
  </body>
  </html>
  <?php
}
$out = ob_get_clean(); // Use OB to make sure redirects in badgeos_obf_earnable_badge_apply_page work
echo $out;