<?php
/**
 * comment
 */

defined( 'ABSPATH' ) || exit;

global $post, $withcomments;

$withcomments = 1;
$post_before = $post;
$post        = get_post( $post_id );
setup_postdata( $post );
comments_template();
$post   = $post_before;

?>
