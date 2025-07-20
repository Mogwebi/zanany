<?php
/**
 * Share
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="modal-header">
	<h4 class="modal-title"><?php play_get_text('embed', true); ?></h4>
	<button class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
	<div class="share-embed">
		<iframe width="100%" height="220" scrolling="no" frameborder="no" src=""></iframe>
	</div>
	<input type="text" id="embed-code" class="input" value=""/>
	<h5><?php play_get_text('share', true); ?></h5>
	<div class="share-list">
		<a href="#" data-url="https://www.facebook.com/sharer.php?u=" target="_blank" title="Facebook">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon share-facebook"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
		</a>
		<a href="#" data-url="https://x.com/intent/tweet?url=" target="_blank" title="X">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="svg-icon share-x" viewBox="0 0 16 16"><path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.601.75Zm-.86 13.028h1.36L4.323 2.145H2.865l8.875 11.633Z"/></svg>
		</a>
	</div>
	<form><input type="text" id="share-url" class="input" value=""/></form>
</div>
