<?php
/**
 * Empty
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="user-placeholder">
	<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="feather feather-list"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
<?php 
$user_id = get_queried_object_id();

if( get_current_user_id() == $user_id ){
?>
		<p><?php play_get_text('no-short', true); ?></p>
<?php
}else{
?>
		<p><?php play_get_text('no-short-alt', true); ?></p>
<?php
}
?>
</div>
