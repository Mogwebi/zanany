<?php

defined( 'ABSPATH' ) || exit;

// register form
add_action( 'play_register_form', 'play_output_privacy', 10 );
if ( ! function_exists( 'play_output_privacy' ) ) {
    function play_output_privacy() {
        $policy_page_id = (int) get_option( 'wp_page_for_privacy_policy' );
        if($policy_page_id){
            $link = '<a href="'.esc_url(get_permalink($policy_page_id)).'" class="text-primary" target="_blank">'.esc_html(get_the_title($policy_page_id)).'</a>';
            echo '<p class="form-term">'.sprintf(play_get_text('register-terms'), $link).'</p>';
        }
    }
}

// Single
add_action( 'play_before_single_title', 'play_output_artist', 10 );
if ( ! function_exists( 'play_output_artist' ) ) {
    function play_output_artist() {
        $artist_sep = apply_filters('play_block_artist_sep_filter', '<span>, </span>');
        $artist_link = apply_filters('play_block_artist_link_filter', '<span> & </span>');
        if(!taxonomy_exists('artist')){
            return;
        }
        $str = get_the_term_list( get_the_ID(), 'artist', '<span class="entry-artist">', $artist_sep, '</span>' );
        if(strpos($str, $artist_sep) ){
            $str = substr_replace($str, $artist_link, strrpos($str, $artist_sep), strlen($artist_sep) );
        }
        echo $str;
    }
}

add_action( 'play_after_single_title', 'play_output_meta', 100);
if ( ! function_exists( 'play_output_meta' ) ) {
    function play_output_meta() {
        do_action( 'play_before_single_meta');
        echo '<div class="entry-meta">';
        do_action( 'play_single_meta');
        echo '</div>';
        do_action( 'play_after_single_meta');
    }
}

add_action( 'play_after_single_title', 'play_output_term', 200);
if ( ! function_exists( 'play_output_term' ) ) {
    function play_output_term() {
        do_action( 'play_before_single_term');
        echo '<div class="entry-term">';
        do_action( 'play_single_term');
        echo '</div>';
        do_action( 'play_after_single_term');
    }
}

add_action( 'play_single_meta', 'play_output_play_btn', 10 );
if ( ! function_exists( 'play_output_play_btn' ) ) {
    function play_output_play_btn() {
        do_action( 'the_play_button', get_the_ID(), 'play', '', true );
    }
}
add_action( 'play_single_meta', 'play_output_like_btn', 30 );
if ( ! function_exists( 'play_output_like_btn' ) ) {
    function play_output_like_btn() {
        do_action( 'the_like_button', get_the_ID() );
    }
}
add_action( 'play_single_meta', 'play_output_comment_btn', 35 );
if ( ! function_exists( 'play_output_comment_btn' ) ) {
    function play_output_comment_btn() {
        do_action( 'the_comment_button', get_the_ID() );
    }
}
add_action( 'play_single_meta', 'play_output_download_btn', 40 );
if ( ! function_exists( 'play_output_download_btn' ) ) {
    function play_output_download_btn() {
        do_action( 'the_download_button', get_the_ID() );
    }
}

add_action( 'before_loop_footer', 'play_output_duration', 5, 2 );
add_action( 'the_play_duration', 'play_output_duration' );
if ( ! function_exists( 'play_output_duration' ) ) {
    function play_output_duration( $post_id, $attributes = null ) {
        $duration = get_post_meta($post_id, 'duration', true);
        if($duration){
            echo sprintf( '<span class="play-duration">%s</span>', Play_Utils::instance()->duration( (int)$duration / 1000, '', true ) );
        }
    }
}

add_action( 'the_loop_date', 'play_output_datetime' );
if ( ! function_exists( 'play_output_datetime' ) ) {
    function play_output_datetime( $post_id ) {
        echo sprintf( '<span class="entry-meta-date"><span>'.play_get_text('time-ago').'</span></span>', human_time_diff( strtotime(get_the_date('', $post_id)), current_time( 'timestamp', 1 ) ) );
    }
}

add_action( 'the_loop_author', 'play_author', 10 );
if ( ! function_exists( 'play_author' ) ) {
    function play_author() {
        $artist = get_the_term_list( get_the_ID(), 'artist' );
        if ( taxonomy_exists('artist') && $artist ) {
            play_output_artist();
        } else {
            play_output_author( false );
        }
    }
}

add_action( 'the_loop_author_avatar', 'play_author_avatar', 10 );
if ( ! function_exists( 'play_author_avatar' ) ) {
    function play_author_avatar() {
        $user_id = get_the_author_meta( 'ID' );
        $html = sprintf(
            '<a href="%s" class="entry-avatar">%s</a>',
            esc_url( get_author_posts_url( $user_id ) ),
            get_avatar( $user_id, 48 )
        );
        echo $html;
    }
}


add_action( 'before_loop_footer', 'play_output_count', 10, 2 );
add_action( 'the_loop_plays', 'play_output_count', 10, 2 );
if ( ! function_exists( 'play_output_count' ) ) {
    function play_output_count( $post_id, $attributes = null ) {
        $post_id = $post_id ? $post_id : get_the_ID();
        $orderby = 'all';
        if ( isset( $attributes ) && isset( $attributes[ 'orderby' ] ) ) {
            $orderby = $attributes[ 'orderby' ];
        }

        $match = preg_match( '/' . implode( '|', array(
            'day',
            'week',
            'month',
            'year',
            'all'
        ) ) . '/', $orderby, $matches );

        if ( $match ) {
            do_action( 'the_play_count', $post_id, $matches[ 0 ] );
        }
    }
}


add_shortcode( 'play_purchase_btn', 'play_purchase_shortocde' );
function play_purchase_shortocde($attr = [], $content = null, $tag = ''){
    $id = isset( $_REQUEST[ 'id' ] ) ? $_REQUEST[ 'id' ] : ( isset($attr['id']) ? $attr['id'] : false );
    if ( $id ) {
        return play_purchase_btn($id);
    }
}

if ( ! function_exists( 'play_purchase_btn' ) ) {
    function play_purchase_btn($id) {
        $modal = '<div class="modal" id="product-modal-' . esc_attr( $id ) . '"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">'.get_the_title($id).'</h4><button class="close" data-dismiss="modal">&times;</button></div><div class="modal-body">%s</div></div></div></div>';

        $wrap = '<div class="add_to_cart_inline">%s</div>';
        // add_to_cart_button for woo default ajax
        $btn = '<a rel="nofollow" href="%s" class="no-ajax btn-purchase add_to_cart_button product_type_%s %s" data-product_id="%s" data-target="#product-modal-%s" %s>%s</a>';
        $button = '';

        $post_type = get_post_type( $id );
        // woocommerce
        if ( 'product' == $post_type && function_exists( 'wc_get_product' ) ) {
            global $product;
            $previous_product = $product;

            $product = wc_get_product( $id );
            $type = $product->get_type();

            if ( $type == 'grouped' || $type == 'variable' ) {
                ob_start();
                call_user_func('woocommerce_' . $type . '_add_to_cart');
                $options = ob_get_clean();
                $modal = sprintf($modal, $options);
            }else{
                $modal = '';
            }

            if ( '' !== $product->get_price_html() ) {
                $text = apply_filters('play_purchase_woo_btn', $product->get_price_html(), $id);
                $btn = sprintf($btn, esc_url( $product->add_to_cart_url() ), $product->get_type(), ( $type == 'simple' ? 'ajax_add_to_cart' : '' ), esc_attr( $id ), esc_attr( $id ) , ( $type == 'simple' ? '' : 'data-toggle="modal"' ), $text);
                $button = sprintf( $wrap, $btn.$modal );
            }

            $product = $previous_product;
        }
        // edd
        if ( 'download' == $post_type && function_exists( 'edd_get_purchase_link' ) ) {
            $class = 'no-ajax btn-purchase';
            $arg = array(
                'download_id' => $id,
                'class' => $class,
                'style' => ''
            );
            if(!edd_has_variable_prices($id)){
                $arg['text'] = '';
            }
            $edd_btn = edd_get_purchase_link( apply_filters('play_edd_cart_btn_arg', $arg) );

            if(edd_has_variable_prices($id)){
                $modal = sprintf($modal, preg_replace('#\s(id|for)="[^"]+"#', '', $edd_btn));
                $text = apply_filters('play_purchase_edd_btn', edd_price_range($id), $id);
                $btn = sprintf( $btn, '#', 'group', 'btn-purchase', esc_attr( $id ), esc_attr( $id ), 'data-toggle="modal"', $text );
            }else{
                $btn = $edd_btn;
                $modal = '';
            }

            $button = sprintf( $wrap, $btn.$modal );
        }

        return $button;
    }
}

add_action( 'play_cart', 'play_cart');
if ( ! function_exists( 'play_cart' ) ) {
    function play_cart($request) {
        if(!isset($request['action'])){
            return;
        }
        ob_start();
        if($request['action']=='edd_add_to_cart'){
            edd_ajax_add_to_cart();
        }
        if($request['action']=='edd_remove_from_cart'){
            edd_ajax_remove_from_cart();
        }
        $return = ob_get_clean();
        return wp_send_json(
            $return
        );
    }
}

add_filter( 'play_cart_ids', 'play_cart_ids');
if ( ! function_exists( 'play_cart_ids' ) ) {
    function play_cart_ids(){
        $arr = array();
        if( function_exists( 'edd_get_purchase_link' ) ){
            $items = edd_get_cart_contents();
            foreach ( $items as $key => $item ) {
                if(!edd_has_variable_prices($item['id'])){
                    $arr[] = (int)$item['id'];
                }
            }
        }
        return array_filter(array_values($arr));
    }
}

function play_disable_comment( $open ) {
    if ( get_post_type(get_the_ID()) === 'download' && class_exists( 'EDD_Reviews' ) ) {
        $open = false;
    }
    return $open;
}
add_filter( 'play_show_comments', 'play_disable_comment', 11, 2 );

add_action( 'play_review', 'play_review');
if ( ! function_exists( 'play_review' ) ) {
    function play_review($request) {
        ob_start();
        do_action('edd_reviews_process_review');
        $return = ob_get_clean();
        return wp_send_json(
            $return
        );
    }
}

add_action( 'after_loop_header', 'play_output_purchase_btn', 30 );
add_action( 'play_single_meta', 'play_output_purchase_btn', 50 );
if ( ! function_exists( 'play_output_purchase_btn' ) ) {
    function play_output_purchase_btn() {
        $id = get_the_ID();
        $btn = play_purchase_btn($id);
        echo $btn;
        do_action( 'the_purchase_button', $id );
    }
}

add_action( 'play_single_meta', 'play_output_more_btn', 60 );
if ( ! function_exists( 'play_output_more_btn' ) ) {
    function play_output_more_btn() {
        do_action( 'the_more_button', get_the_ID(), 'post' );
    }
}

add_action( 'play_after_user_meta', 'play_output_user_more_btn', 60 );
if ( ! function_exists( 'play_output_user_more_btn' ) ) {
    function play_output_user_more_btn($user_id) {
        do_action( 'the_more_button', $user_id, 'user' );
    }
}

add_action( 'play_single_meta', 'play_output_author', 70 );
if ( ! function_exists( 'play_output_author' ) ) {
    function play_output_author( $avatar, $echo = true ) {
        $user_id = get_the_author_meta( 'ID' );
        $html = sprintf(
            '<span class="byline">%s<span class="author vcard"><a class="url fn n" href="%s">%s</a></span>%s</span>',
            $avatar !== false ? '<span class="svg-icon">' . get_avatar( $user_id, 48 ) . '</span>' : '',
            esc_url( get_author_posts_url( $user_id ) ),
            esc_html( get_the_author() ),
            apply_filters('verified_button', $user_id)
        );
        $html = apply_filters('play_author', $html, $user_id);
        if($echo){
            echo $html;
        }else{
            return $html;
        }
    }
}

if ( ! function_exists( 'play_output_author_bio' ) ) {
    function play_output_author_bio() {
        play_get_template( 'user/user-bio.php' );
    }
}

if ( ! function_exists( 'play_output_rank' ) ) {
    function play_output_rank() {
        play_get_template( 'blocks/rank.php' );
    }
}

add_action( 'the_loop_waveform', 'play_output_waveform', 10 );
if ( ! function_exists( 'play_output_waveform' ) ) {
    function play_output_waveform() {
        play_get_template( 'blocks/waveform.php' );
    }
}

add_action( 'play_single_term', 'play_output_tag', 30 );
if ( ! function_exists( 'play_output_tag' ) ) {
    function play_output_tag() {
        $tag       = 'station_tag';
        $post_type = get_post_type( get_the_ID() );
        if ( $post_type == 'product' ) {
            $tag = 'product_tag';
        } elseif ( $post_type == 'download' ) {
            $tag = 'download_tag';
        }
        if(taxonomy_exists($tag)){
            $tags = get_the_term_list( get_the_ID(), $tag, '<ul class="entry-tag"><li class="tag">', '</li><li class="tag">', '</li></ul>' );
            if(apply_filters('play_hide_featured_tag', false)){
                $tags = preg_replace('/(<a href=".+?">)Featured(<\/a>)/i', '', $tags);
            }
            echo $tags;
        }
    }
}

if ( ! function_exists( 'play_output_activity' ) ) {
    function play_output_activity() {
        $tax = 'activity';
        if(taxonomy_exists($tax)){
            echo get_the_term_list( get_the_ID(), $tax, '<ul class="entry-activity"><li class="activity">', '</li><li class="activity">', '</li></ul>' );
        }
    }
}

if ( ! function_exists( 'play_output_mood' ) ) {
    function play_output_mood() {
        $tax = 'mood';
        if(taxonomy_exists($tax)){
            echo get_the_term_list( get_the_ID(), $tax, '<ul class="entry-mood"><li class="mood">', '</li><li class="mood">', '</li></ul>' );
        }
    }
}

add_action( 'play_single_header_end', 'play_output_editor_note', 30 );
if ( ! function_exists( 'play_output_editor_note' ) ) {
    function play_output_editor_note() {
        $txt = get_post_meta( get_the_ID(), 'editor_note', true );
        if ( $txt ) {
            echo sprintf( '<div class="editor-note"><span class="editor-note-title">%s</span> %s</div>', play_get_text( 'editor-note' ), wp_kses_post( $txt ) );
        }
    }
}

add_action( 'play_single_header_end', 'play_output_copyright', 40 );
if ( ! function_exists( 'play_output_copyright' ) ) {
    function play_output_copyright() {
        $txt = get_post_meta( get_the_ID(), 'copyright', true );
        if ( $txt ) {
            echo sprintf( '<div class="station-copyright">%s</div>', wp_kses_post( $txt ) );
        }
    }
}

if ( ! function_exists( 'play_output_info' ) ) {
    function play_output_info() {
        remove_action( 'play_single_term', 'play_output_cat', 20 );
        $id        = get_the_ID();
        $info_list = [];
        $type      = get_post_meta( $id, 'type', true );
        $duration  = get_post_meta( $id, 'duration', true );
        $bpm       = get_post_meta( $id, 'bpm', true );
        if ( $duration ) {
            $info_list[ 'duration' ] = Play_Utils::instance()->duration( (int)$duration / 1000, '', true );
        }
        if ( $bpm ) {
            $info_list[ 'bpm' ] = sprintf( '%s %s', esc_html( $bpm ), play_get_text( 'bpm' ) );
        }
        
        if ( 'playlist' === $type ) {
            $info_list[ 'playlist' ] = play_get_text( 'playlist' );
        }
        if ( 'album' === $type ) {
            $info_list[ 'album' ] = play_get_text( 'album' );
        }
        if ( in_array( $type, array( 'playlist', 'album' ) ) ) {
            $auto_type = get_post_meta( get_the_ID(), 'auto_type', true );
            if(!$auto_type ){
                $tracks                = get_post_meta( $id, 'post', true );

                $count                 = count( array_filter( explode( ',', $tracks ) ) );
                if($count > 0){
                    $info_list[ 'tracks' ] = $count . ' ' . ( $count == 1 ? play_get_text( 'track' ) : play_get_text( 'tracks' ) );
                }
            
                $duration              = 0;
                foreach ( explode( ',', $tracks ) as $track ) {
                    $single   = get_post_meta( $track, 'duration', true );
                    $duration += (int) $single;
                }

                if ( $duration > 0 ) {
                    $info_list[ 'duration' ] = Play_Utils::instance()->duration( $duration / 1000, '', true );
                }
            }
        }
        $info_list[ 'publish' ] = get_the_time( 'Y', $id );

        $cat       = 'genre';
        $post_type = get_post_type( get_the_ID() );
        if ( $post_type == 'product' ) {
            $cat = 'product_cat';
        } elseif( $post_type == 'download' ) {
            $cat = 'download_category';
        }
        $cat_list  = '';
        $cats = get_the_term_list( $id, $cat, '<span class="entry-info-cat">', '</span><span class="entry-info-cat">', '</span>' );
        if(!is_wp_error($cats)){
            $cat_list = $cats;
        }
        $start     = apply_filters( 'play_output_info_start', '', $id );
        if(apply_filters('play_output_info_author', false)){
            $start .= play_output_author(true, false);
        }
        $end       = apply_filters( 'play_output_info_end', '', $id );
        $cat_list  = apply_filters( 'play_output_info_catlist', $cat_list, $id );
        $info_list = apply_filters( 'play_output_info_list', $info_list, $id );
        $echo      = '';
        foreach ( $info_list as $key => $value ) {
            $echo .= sprintf( '<span class="entry-info-%s">%s</span>', $key, $value );
        }
        echo sprintf( '<div class="entry-info">%s%s%s%s</div>', $start, $cat_list, $echo, $end );
    }
}

add_action( 'play_single_term', 'play_output_cat', 20 );
if ( ! function_exists( 'play_output_cat' ) ) {
    function play_output_cat() {
        $cat       = 'genre';
        $post_type = get_post_type( get_the_ID() );
        if ( $post_type == 'product' ) {
            $cat = 'product_cat';
        } elseif ( $post_type == 'download' ) {
            $cat = 'download_category';
        }
        if(taxonomy_exists($cat)){
            echo get_the_term_list( get_the_ID(), $cat, '<ul class="entry-cat"><li class="genre">', '</li><li class="genre">', '</li></ul>' );
        }
    }
}

add_action( 'play_before_single_header', 'play_output_thumbnail', 10 );
if ( ! function_exists( 'play_output_thumbnail' ) ) {
    function play_output_thumbnail() {
        if ( ! has_post_thumbnail() ) {
            return;
        }
        ?>
        <figure class="post-thumbnail header-station-thumbnail" <?php do_action( 'the_post_thumbnail_attr', get_the_ID() ) ?>>
            <?php the_post_thumbnail( 'post-thumbnail' ); ?>
        </figure>
        <?php
    }
}

add_action( 'play_before_single_station', 'play_output_header', 10 );
if ( ! function_exists( 'play_output_header' ) ) {
    function play_output_header() {
        play_get_template( 'single-station/header.php' );
    }
}

add_action( 'play_content', 'play_output_content', 10 );
if ( ! function_exists( 'play_output_content' ) ) {
    function play_output_content() {
        $auto = get_post_meta( get_the_ID(), 'auto_type', true );
        if($auto){
            return;
        }
        $attr = '';
        if( apply_filters('play_content_moreless', false) ){
            $attr = sprintf( 'data-plugin="moreless" more="%s" less="%s" type="%s" title="%s"' , 
                esc_attr(play_get_text( 'show-more' )), 
                esc_attr(play_get_text( 'show-less' )), 
                apply_filters('play_content_moreless_type', 'modal'),
                esc_attr(get_the_title())
            );
        }
        echo sprintf( '<div class="station-content" %s>',  $attr);

        $content = get_the_content( '', '' );
        echo apply_filters( 'the_content', $content );

        echo '</div>';
    }
}


add_action( 'play_after_embed', 'play_output_embed_tracks', 10 );
if ( ! function_exists( 'play_output_embed_tracks' ) ) {
    function play_output_embed_tracks() {
        $auto = get_post_meta( get_the_ID(), 'auto_type', true );
        if($auto){
            $content = get_the_content('', '', get_the_ID());
            echo apply_filters( 'the_content', $content );
            return;
        }
        $items = get_post_meta( get_the_ID(), 'post', true );
        if ( ! empty( $items ) ) {
            $arg = array(
                'type'      => 'any',
                'title'     => apply_filters( 'play_title_tracks', play_get_text( 'tracks' ) ),
                'pages'     => 100,
                'pager'     => 'more',
                'orderby'   => 'album',
                'post_id'   => get_the_ID(),
                'template'  => 'templates/loop-tracks.php',
                'className' => 'block-loop-row block-loop-index album-tracks',
                'debug'     => false
            );
            do_action( 'the_loop_block', apply_filters( 'play_embed_tracks', $arg ) );
        }
    }
}

add_action( 'play_after_content', 'play_output_tracks', 10 );
if ( ! function_exists( 'play_output_tracks' ) ) {
    function play_output_tracks() {
        $auto = get_post_meta( get_the_ID(), 'auto_type', true );
        if($auto){
            $content = get_the_content('', '', get_the_ID());
            echo apply_filters( 'the_content', $content );
            return;
        }
        $items = get_post_meta( get_the_ID(), 'post', true );
        if ( ! empty( $items ) ) {
            $arg = array(
                'type'      => 'any',
                'title'     => apply_filters( 'play_title_tracks', play_get_text( 'tracks' ) ),
                'pages'     => 100,
                'pager'     => 'more',
                'orderby'   => 'album',
                'post_id'   => get_the_ID(),
                'template'  => 'templates/loop-tracks.php',
                'className' => 'block-loop-row block-loop-index album-tracks',
                'debug'     => false
            );
            do_action( 'the_loop_block', apply_filters( 'play_album_tracks', $arg ) );
        }
    }
}

add_action( 'play_after_content', 'play_output_more', 20 );
if ( ! function_exists( 'play_output_more' ) ) {
    function play_output_more() {
        $title = get_the_author();
        $query = array(
            'author'       => get_the_author_meta( 'ID' ),
            'post__not_in' => array( get_the_ID() ),
            'orderby'      => 'rand'
        );

        if ( apply_filters( 'play_more_from_artist', false ) ) {
            $sep     = apply_filters( 'play_artist_tag_sep', ' & ' );
            $artists = get_the_term_list( get_the_ID(), 'artist', '', $sep, '' );
            if(taxonomy_exists('artist') && $artists ) {
                $title   = $artists;
                $terms   = get_the_terms( get_the_ID(), 'artist' );
                $query   = array(
                    'post__not_in' => array( get_the_ID() ),
                    'orderby'      => 'rand',
                    'tax_query'    =>
                        array(
                            array(
                                'taxonomy' => 'artist',
                                'field'    => 'term_id',
                                'terms'    => wp_list_pluck( $terms, 'term_id' )
                            )
                        )
                );
            }
        }

        $types = play_get_option( 'play_types' );
        $arg   = array(
            'title'     => apply_filters( 'play_title_more_from', play_get_text( 'more-from' ) ) . ' ' . $title,
            'type'      => $types,
            'pages'     => 12,
            'pager'     => '',
            'slider'    => true,
            'query'     => $query,
            'className' => 'station-more-from',
            'debug'     => false
        );
        do_action( 'the_loop_block', apply_filters( 'play_more_filter', $arg ) );
    }
}

add_action( 'play_after_content', 'play_output_similar', 30 );
if ( ! function_exists( 'play_output_similar' ) ) {
    function play_output_similar() {
        $tax_query = array( 'relation' => 'OR' );

        $tags = get_the_terms( get_the_ID(), 'station_tag' );
        if ( !is_wp_error($tags) && ! empty( $tags ) ) {
            $tags = array_slice($tags, 0, 5);
            foreach ( $tags as $tag ) {
                $tax_query[] = array(
                    'taxonomy' => $tag->taxonomy,
                    'field'    => 'slug',
                    'terms'    => $tag->slug
                );
            }
        }

        $cats = get_the_terms( get_the_ID(), 'genre' );
        if ( !is_wp_error($cats) && ! empty( $cats ) ) {
            $cats = array_slice($cats, 0, 5);
            foreach ( $cats as $cat ) {
                $tax_query[] = array(
                    'taxonomy' => $cat->taxonomy,
                    'field'    => 'slug',
                    'terms'    => $cat->slug
                );
            }
        }

        $types = play_get_option( 'play_types' );
        $arg   = array(
            'type'      => $types,
            'pages'     => 12,
            'pager'     => '',
            'slider'    => true,
            'title'     => apply_filters( 'play_title_similar', play_get_text( 'similar' ) ),
            'query'     => array( 'tax_query' => $tax_query, 'post__not_in' => array( get_the_ID() ) ),
            'orderby'   => 'rand',
            'className' => 'station-similar',
            'debug'     => false
        );

        do_action( 'the_loop_block', apply_filters( 'play_similar_filter', $arg ) );
    }
}

add_action( 'play_after_content', 'play_output_featured', 30 );
if ( ! function_exists( 'play_output_featured' ) ) {
    function play_output_featured() {
        $meta_query = array(
            array(
                'key'     => 'post',
                'value'   => get_the_ID(),
                'compare' => 'find_in_set',
            )
        );
        $types      = play_get_option( 'play_types' );
        $arg        = array(
            'type'      => $types,
            'pages'     => 12,
            'pager'     => '',
            'slider'    => true,
            'orderby'   => 'title',
            'title'     => apply_filters( 'play_title_featured', play_get_text( 'featured' ) ),
            'query'     => array( 'meta_query' => $meta_query ),
            'className' => 'station-appear',
            'debug'     => false
        );

        do_action( 'the_loop_block', apply_filters( 'play_appear_filter', $arg ) );
    }
}

function play_single_album_post_where($where, $query){
    global $wpdb;
    foreach ( $query->meta_query->queries as $index => $meta_query ) {
        if ( isset( $meta_query[ 'compare' ] ) && 'find_in_set' == strtolower( $meta_query[ 'compare' ] ) ) {
            $regex = "#\( ({$wpdb->postmeta}.meta_key = '" . preg_quote( $meta_query[ 'key' ] ) . "')" . " AND ({$wpdb->postmeta}.meta_value) = ('" . preg_quote( $meta_query[ 'value' ] ) . "') \)#";
            $where = preg_replace( $regex, "($1 AND FIND_IN_SET($3,$2))", $where );
        }
    }
    return $where;
}
if ( ! function_exists( 'play_get_single_album' ) ) {
    function play_get_single_album($id) {
        $args = array(
            'post_type' => 'station',
            'numberposts' => 1,
            'fields' => 'ids',
            'meta_query'  => array(
                array(
                    'key'     => 'post',
                    'value'   => $id,
                    'compare' => 'find_in_set',
                )
            )
        );
        add_filter( 'posts_where', 'play_single_album_post_where', 10, 2 );
        $posts = new WP_Query($args);
        remove_filter( 'posts_where', 'play_single_album_post_where', 10, 2 );
        return($posts->posts);
    }
}

// user
add_action( 'play_user_content', 'play_user_action', 10 );
if ( ! function_exists( 'play_user_action' ) ) {
    function play_user_action() {
        global $wp;
        $endpoint = 'home';
        $user_id  = get_queried_object_id();
        foreach ( $wp->query_vars as $key => $value ) {
            // Ignore pagename param.
            if ( 'pagename' === $key || 'author_name' === $key ) {
                continue;
            }
            if ( ! empty( $key ) ) {
                $endpoint = $key;
            }

            $endpoint = apply_filters('get_user_endpoints', $endpoint);
            
            if ( has_action( 'play_user_' . $endpoint . '_endpoint' ) ) {
                do_action( 'play_user_' . $endpoint . '_endpoint', $user_id );

                return;
            }
        }

        do_action( 'play_user_' . $endpoint . '_endpoint', $user_id );
    }
}

add_action( 'play_user_home_endpoint', 'play_user_popular', 10 );
if ( ! function_exists( 'play_user_popular' ) ) {
    function play_user_popular( $user_id ) {
        $types   = play_get_option( 'play_types' );
        $arg     = array(
            'type'      => $types,
            'title'     => apply_filters( 'play_title_popular', play_get_text( 'popular' ) ),
            'pages'     => apply_filters( 'play_user_popular_pages', 10 ),
            'pager'     => '',
            'query'     => array( 'author' => $user_id ),
            'slider'    => true,
            'orderby'   => 'all',
            'user_id'   => $user_id,
            'className' => 'user-popular',
        );
        do_action( 'the_loop_block', apply_filters( 'user_home_popular_filter', $arg ) );
    }
}

add_action( 'play_user_home_endpoint', 'play_user_album', 20 );
if ( ! function_exists( 'play_user_album' ) ) {
    function play_user_album( $user_id ) {
        $types   = play_get_option( 'play_types' );
        $arg     = array(
            'type'      => $types,
            'title'     => apply_filters( 'play_title_albums', play_get_text( 'albums' ) ),
            'pages'     => apply_filters( 'play_user_albums_pages', 10 ),
            'pager'     => '',
            'slider'    => true,
            'query'     => array( 'author' => $user_id, 'meta_key' => 'type', 'meta_value' => 'album' ),
            'className' => 'user-home-album'
        );
        do_action( 'the_loop_block', apply_filters( 'user_home_album_filter', $arg ) );
    }
}

add_action( 'play_user_home_endpoint', 'play_user_playlist', 30 );
if ( ! function_exists( 'play_user_playlist' ) ) {
    function play_user_playlist( $user_id ) {
        $types   = play_get_option( 'play_types' );
        $arg     = array(
            'type'      => $types,
            'title'     => apply_filters( 'play_title_playlists', play_get_text( 'playlists' ) ),
            'pages'     => apply_filters( 'play_user_playlists_pages', 10 ),
            'pager'     => '',
            'slider'    => true,
            'query'     => array( 'author' => $user_id, 'meta_key' => 'type', 'meta_value' => 'playlist' ),
            'className' => 'user-home-playlist'
        );
        do_action( 'the_loop_block', apply_filters( 'user_home_playlist_filter', $arg ) );
    }
}

add_action( 'play_user_home_endpoint', 'play_user_single', 40 );
if ( ! function_exists( 'play_user_single' ) ) {
    function play_user_single( $user_id ) {
        $types   = play_get_option( 'play_types' );
        $arg     = array(
            'type'      => $types,
            'title'     => apply_filters( 'play_title_single', play_get_text( 'singles' ) ),
            'pages'     => apply_filters( 'play_user_singles_pages', 10 ),
            'pager'     => '',
            'slider'    => true,
            'query'     => array( 'author' => $user_id, 'meta_key' => 'type', 'meta_value' => 'single' ),
            'className' => 'user-home-single'
        );
        do_action( 'the_loop_block', apply_filters( 'user_home_single_filter', $arg ) );
    }
}

add_action( 'play_user_home_endpoint', 'play_user_post', 50 );
if ( ! function_exists( 'play_user_post' ) ) {
    function play_user_post( $user_id ) {
        $arg     = array(
            'type'      => 'post',
            'title'     => apply_filters( 'play_title_post', play_get_text( 'Posts' ) ),
            'pages'     => apply_filters( 'play_user_post_pages', 10 ),
            'pager'     => '',
            'slider'    => true,
            'query'     => array( 'author' => $user_id ),
            'className' => 'user-home-post'
        );
        do_action( 'the_loop_block', apply_filters( 'user_home_post_filter', $arg ) );
    }
}

add_action( 'play_user_home_endpoint', 'play_user_played', 50 );
if ( ! function_exists( 'play_user_played' ) ) {
    function play_user_played( $user_id ) {
        if ( get_current_user_id() !== $user_id ) {
            return;
        }

        $ids = apply_filters( 'user_played', $user_id );
        if ( empty( $ids ) ) {
            return;
        }

        $arg = array(
            'type'      => 'any',
            'title'     => apply_filters( 'play_title_recently_played', play_get_text( 'recently-played' ) ),
            'pages'     => apply_filters( 'play_user_pages', 12 ),
            'pager'     => '',
            'slider'    => true,
            'ids'       => array_reverse( $ids ),
            'orderby'   => 'post__in',
            'className' => 'user-played',
            'debug'     => false
        );
        do_action( 'the_loop_block', apply_filters( 'user_played_filter', $arg ) );
    }
}

add_action( 'play_user_stations_endpoint', 'play_user_stations', 20 );
if ( ! function_exists( 'play_user_stations' ) ) {
    function play_user_stations( $user_id ) {
        $types = play_get_option( 'play_types' );
        $arg   = array(
            'type'      => $types,
            'pages'     => apply_filters( 'play_user_pages', 12 ),
            'pager'     => 'more',
            'query'     => array( 'author' => $user_id, 'meta_key' => 'type', 'meta_value' => 'single' ),
            'className' => 'user-stations',
            'debug'     => false
        );
        add_filter( 'loop_block_content', 'upload_empty' );
        do_action( 'the_loop_block', apply_filters( 'user_stations_filter', $arg ) );
    }

    function upload_empty( $content ) {
        if ( $content ) {
            return $content;
        } else {
            return play_get_template_html( 'user/empty_upload.php' );
        }
    }
}

add_action( 'play_user_playlists_endpoint', 'play_user_playlists', 30 );
if ( ! function_exists( 'play_user_playlists' ) ) {
    function play_user_playlists( $user_id ) {
        $types = play_get_option( 'play_types' );
        $arg   = array(
            'type'      => $types,
            'pages'     => apply_filters( 'play_user_pages', 12 ),
            'pager'     => 'more',
            'query'     => array( 'author' => $user_id, 'meta_key' => 'type', 'meta_value' => 'playlist' ),
            'className' => 'user-playlists',
            'debug'     => false
        );
        add_filter( 'loop_block_content', 'playlists_empty' );
        do_action( 'the_loop_block', apply_filters( 'user_playlists_filter', $arg ) );
    }

    function playlists_empty( $content ) {
        if ( $content ) {
            return $content;
        } else {
            return play_get_template_html( 'user/empty_playlist.php' );
        }
    }
}

add_action( 'play_user_albums_endpoint', 'play_user_albums', 30 );
if ( ! function_exists( 'play_user_albums' ) ) {
    function play_user_albums( $user_id ) {
        $types = play_get_option( 'play_types' );
        $arg   = array(
            'type'      => $types,
            'pages'     => apply_filters( 'play_user_pages', 12 ),
            'pager'     => 'more',
            'query'     => array( 'author' => $user_id, 'meta_key' => 'type', 'meta_value' => 'album' ),
            'className' => 'user-albums',
            'debug'     => false
        );
        add_filter( 'loop_block_content', 'albums_empty' );
        do_action( 'the_loop_block', apply_filters( 'user_albums_filter', $arg ) );
    }

    function albums_empty( $content ) {
        if ( $content ) {
            return $content;
        } else {
            return play_get_template_html( 'user/empty_album.php' );
        }
    }
}

add_action( 'play_user_shorts_endpoint', 'play_user_shorts', 30 );
if ( ! function_exists( 'play_user_shorts' ) ) {
    function play_user_shorts( $user_id ) {
        $types = play_get_option( 'play_types' );
        $arg   = array(
            'type'      => $types,
            'pages'     => apply_filters( 'play_user_pages', 12 ),
            'pager'     => 'more',
            'query'     => array( 'author' => $user_id, 'meta_key' => 'type', 'meta_value' => 'short' ),
            'className' => 'user-shorts',
            'debug'     => false
        );
        add_filter( 'loop_block_content', 'shorts_empty' );
        do_action( 'the_loop_block', apply_filters( 'user_shorts_filter', $arg ) );
    }

    function shorts_empty( $content ) {
        if ( $content ) {
            return $content;
        } else {
            return play_get_template_html( 'user/empty_short.php' );
        }
    }
}

add_action( 'play_user_likes_endpoint', 'play_user_likes', 40 );
if ( ! function_exists( 'play_user_likes' ) ) {
    function play_user_likes( $user_id ) {
        $likes = apply_filters( 'user_likes', $user_id );
        if ( empty( $likes ) ) {
            play_get_template( 'user/empty_like.php' );
            return;
        }
        $arg = array(
            'type'      => 'any',
            'pages'     => apply_filters( 'play_user_pages', 12 ),
            'pager'     => 'more',
            'orderby'   => 'user_likes',
            'user_id'   => $user_id,
            'className' => 'user-likes',
            'debug'     => false
        );
        do_action( 'the_loop_block', apply_filters( 'user_likes_filter', $arg ) );
    }
}

add_action( 'play_user_followers_endpoint', 'play_user_followers', 50 );
if ( ! function_exists( 'play_user_followers' ) ) {
    function play_user_followers( $user_id ) {
        $follower = apply_filters( 'user_follow', $user_id );
        if ( empty( $follower ) ) {
            play_get_template( 'user/empty_follower.php' );
            return;
        }
        $arg = array(
            'type'      => 'user',
            'pages'     => apply_filters( 'play_user_pages', 12 ),
            'pager'     => 'more',
            'orderby'   => 'follow_user',
            'user_id'   => $user_id,
            'className' => 'user-followers',
            'debug'     => false
        );
        do_action( 'the_loop_block', apply_filters( 'user_follower_filter', $arg ) );
    }
}

add_action( 'play_user_following_endpoint', 'play_user_following', 60 );
if ( ! function_exists( 'play_user_following' ) ) {
    function play_user_following( $user_id ) {
        $following = apply_filters( 'user_following', $user_id );
        if ( empty( $following ) ) {
            play_get_template( 'user/empty_following.php' );
            return;
        }
        $arg = array(
            'type'      => 'user',
            'pages'     => apply_filters( 'play_user_pages', 12 ),
            'pager'     => 'more',
            'orderby'   => 'following_user',
            'user_id'   => $user_id,
            'className' => 'user-following',
            'debug'     => false
        );
        do_action( 'the_loop_block', apply_filters( 'user_following_filter', $arg ) );
    }
}

add_action( 'play_user_download_endpoint', 'play_user_download', 60 );
if ( ! function_exists( 'play_user_download' ) ) {
    function play_user_download( $user_id ) {
        $download = apply_filters( 'user_download', $user_id );
        if ( empty( $download ) ) {
            play_get_template( 'user/empty_download.php' );
            return;
        }
        $arg = array(
            'type'      => 'any',
            'pages'     => apply_filters( 'play_user_pages', 12 ),
            'pager'     => 'more',
            'orderby'   => 'user_downloads',
            'user_id'   => $user_id,
            'className' => 'user-download',
        );
        do_action( 'the_loop_block', apply_filters( 'user_download_filter', $arg ) );
    }
}

add_action( 'play_user_profile_endpoint', 'play_user_profile', 70 );
if ( ! function_exists( 'play_user_profile' ) ) {
    function play_user_profile( $user_id ) {
        echo wp_profile_form( $user_id );
    }
}

add_action( 'play_user_notifications_endpoint', 'play_user_notification', 10 );
if ( ! function_exists( 'play_user_notification' ) ) {
    function play_user_notification( $user_id ) {
        if ( get_current_user_id() !== $user_id ) {
            return;
        }
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $arg = array(
                'type'      => 'custom_type:notification',
                'pages'     => apply_filters( 'play_notifications_pages', 10 ),
                'pager'     => 'more',
                'user_id'   => $user_id,
                'order'     => 'DESC',
                'orderby'   => 'date_notified',
                'template'  => 'notification',
                'className' => 'user-notifications block-loop-row'
            );
            add_filter( 'loop_block_content', 'notification_empty' );
            do_action( 'the_loop_block', apply_filters( 'user_notifications_filter', $arg ) );
        }
    }

    function notification_empty( $content ) {
        if ( $content ) {
            return $content;
        } else {
            return play_get_template_html( 'user/empty_notification.php' );
        }
    }
}

if ( ! function_exists( 'play_user_suggested' ) ) {
    function play_user_suggested() {
        $types   = play_get_option( 'play_types' );
        $arg     = array(
            'type'      => $types,
            'pages'     => apply_filters( 'play_user_pages', 12 ),
            'pager'     => 'more',
            'orderby'   => 'user_following',
            'className' => 'user-suggested',
        );
        do_action( 'the_loop_block', apply_filters( 'user_suggested_filter', $arg ) );
    }
}

add_action( 'menu_after_login_before', 'play_menu_after_login_before', 10 );
if ( ! function_exists( 'play_menu_after_login_before' ) ) {
    function play_menu_after_login_before() {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $icon = apply_filters('icon_notification_svg', '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>');
            printf( '<ul class="nav"><li data-toggle="dropdown" id="dropdown-notification"><a href="#">%s<span class="count"></span></a></li><div class="dropdown-menu dropdown-menu-notificaitons"><div class="dropdown-notification-list"><span class="spinner"></span></div>', $icon );
            
            $link = apply_filters('get_endpoint_url', 'notifications', '', get_author_posts_url($user_id) );
            printf( '<div class="view-all-notifications"><a href="%s">%s</a></div></div></ul>', esc_url($link), play_get_text('all-notifications') );
        }
    }
}

add_action( 'play_notification', 'play_notification_dropdown' );
if ( ! function_exists( 'play_notification_dropdown' ) ) {

    function play_notification_dropdown( ) {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $arg = array(
                'type'      => 'custom_type:notification',
                'pages'     => apply_filters( 'play_notifications_dropdown_pages', 6 ),
                'pager'     => '',
                'user_id'   => $user_id,
                'order'     => 'DESC',
                'orderby'   => 'date_notified',
                'template'  => 'notification',
                'className' => 'user-notifications block-loop-row'
            );
            ob_start();
            add_filter( 'loop_block_content', 'notification_empty' );
            do_action( 'the_loop_block', apply_filters( 'user_notifications_dropdown_filter', $arg ) );
            $content = ob_get_clean();
            wp_send_json(
                array( 'content' => $content )
            );
        }
    }
}


add_action( 'play_user_upload_endpoint', 'play_user_upload', 70 );
if ( ! function_exists( 'play_user_upload' ) ) {
    function play_user_upload( $user_id ) {
        echo play_upload_form();
    }
}

if ( ! function_exists( 'the_archive_thumbnail' ) ) {
    function the_archive_thumbnail() {
        $thumbnail_id = absint( get_term_meta( get_queried_object_id(), 'thumbnail_id', true ) );
        if($thumbnail_id){
            echo wp_get_attachment_image( $thumbnail_id, '' );
        }
    }
}


add_action( 'init', 'play_init' );
function play_init() {
    // Adds custom class to the array of posts classes.
    function play_post_classes( $classes, $class, $post_id ) {
        $type = get_post_meta( get_the_ID(), 'type', true );
        $auto = get_post_meta( get_the_ID(), 'auto_type', true );
        $classes[] = $type ? 'is-'.esc_attr($type) : '';
        $classes[] = $auto ? 'is-autotype' : '';
        $classes[] = 'entry';
        return $classes;
    }
    add_filter( 'post_class', 'play_post_classes', 10, 3 );

    // remove the mediaelement
    if( apply_filters('play_remove_wp_mediaelement', true) ){
        function play_deregister_styles() {
            wp_deregister_script( 'wp-mediaelement' );
            wp_deregister_style( 'wp-mediaelement' );
        }
        add_action( 'wp_print_styles', 'play_deregister_styles', 100 );
    }

    // add private title
    if( apply_filters('play_add_private_title', true) ){
        function play_change_protected_title_prefix( $format ) {
            return '%s <i class="private" title="Private">P</i>';
        }
        add_filter( 'private_title_format', 'play_change_protected_title_prefix' );
    }
}
