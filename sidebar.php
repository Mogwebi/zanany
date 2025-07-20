<?php
/**
 * Sidebar
 */

defined( 'ABSPATH' ) || exit;

$sidebar_id = ffl_sidebar();

if( !$sidebar_id ){
	return;
}

if( get_post_field('post_content', $sidebar_id) === '' ){
	return;
}

?>

<div class="sidebar">
	<div class="sidebar-inner">
	<?php
		do_action( 'play_before_sidebar');
	?>
	<?php
		ffl_the_content($sidebar_id);
	?>
	<?php
		do_action( 'play_after_sidebar');
	?>
	</div>
</div>
