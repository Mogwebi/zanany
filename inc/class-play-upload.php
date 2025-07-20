<?php

defined( 'ABSPATH' ) || exit;

class Play_Upload {

    private $user_id;

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Initialize the actions.
     */

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'remove_upload' ) );
        add_action( 'before_delete_post', array( $this, 'delete_post' ), 10, 2 );
        add_action( 'post_updated', array( $this, 'updated_post' ), 10, 3 );

        add_filter( 'wp_dropdown_cats', array( $this, 'wp_dropdown_cats_multiple' ), 10, 2 );
        add_filter( 'get_upload_edit_link', array( $this, 'get_upload_edit_link' ), 10, 1 );

        add_shortcode( 'play_upload_form', array( $this, 'upload_form_shortcode' ) );
        add_filter( 'play_modal_upload_form', array( $this, 'upload_form' ) );

        add_action( 'play_upload_stream', array( $this, 'upload_stream' ) );
        add_action( 'play_upload_featuredimg', array( $this, 'upload_featuredimg' ) );
        add_action( 'play_upload', array( $this, 'upload' ) );

        add_action( 'add_attachment', array( $this, 'add_attachment' ) );

        add_filter( 'upload_mimes', array( $this, 'mime_types' ));
        add_filter( 'wp_check_filetype_and_ext', array( $this, 'filetype_and_ext' ), 10, 4 );
        add_filter( 'wp_get_attachment_id3_keys', array( $this, 'attachment_id3_keys' ) );

        add_action( 'the_post_thumbnail_attr', array($this, 'the_post_color') );
        add_action( 'the_station_attr', array($this, 'the_post_color') );

        function play_upload_form() {
            return Play_Upload::instance()->upload_form($_REQUEST);
        }

        function play_get_post_color($post_id) {
            return Play_Upload::instance()->get_post_color($post_id);
        }

        do_action( 'play_block_upload_init', $this );
    }

    public function upload( $request ) {
        $this->save_upload( $request );
    }

    public function mime_types( $mimes ) {
        // upload file type
        if(current_user_can('manage_options')){
            $mimes['svg']  = 'image/svg+xml';
            $mimes['vtt']  = 'text/vtt';
            $mimes['xml']  = 'application/xml';
            $mimes['csv']  = 'application/csv';
            $mimes['json'] = 'application/json';
            $mimes['webp'] = 'image/webp';
        }
        return $mimes;
    }

    public function filetype_and_ext( $types, $file, $filename, $mimes ) {
        // allow select file type
        $filetype = wp_check_filetype( $filename, $mimes );
        if(current_user_can('manage_options') && in_array($filetype['ext'], array('txt','json','csv','xml','vtt','svg','webp'))){
            $filetype = wp_check_filetype( $filename, $mimes );
            $types['ext'] = $filetype['ext'];
            $types['type'] = $filetype['type'];
        }
        return $types;
    }

    public function attachment_id3_keys($fields){
        $fields['bpm'] = __('BPM', 'play-block');
        return $fields;
    }

    public function remove_upload() {
        if ( ! isset( $_REQUEST[ 'post_id' ] ) || ! isset( $_REQUEST[ 'action' ] ) || 'remove' !== $_REQUEST[ 'action' ] ) {
            return;
        }
        $user_id = get_current_user_id();
        $post_id = (int) $_REQUEST[ 'post_id' ];

        $this->remove_post( $post_id );
        
        wp_safe_redirect( get_author_posts_url( $user_id ) );
        exit();
    }

    public function delete_post($post_id, $post) {
        // delete album singles
        $posts = get_post_meta( $post_id, 'post', true );
        if(!empty($posts)){
            foreach ( array_filter( explode( ',', $posts ) ) as $id ) {
                $this->remove_post($id);
            }
        }
        // remove attachment
        $attachment_url = get_post_meta( $post_id, 'stream', true );
        $attachment_id  = attachment_url_to_postid($attachment_url);
        if($attachment_id && $this->user_can_edit( $attachment_id )){
            wp_delete_attachment( $attachment_id, true);
        }
    }

    public function updated_post($post_id, $post_after, $post_before) {
        // update singles status if private to publish. 
        // user switch post status or admin approve post
        if($post_after->post_type !== play_get_option( 'post_type' )){
            return;
        }
        $type = get_post_meta($post_id, 'type', true);
        if($type !== 'album'){
            return;
        }

        // post status change
        if($post_after->post_status == 'publish' && $post_after->post_status !== $post_before->post_status){
            $posts = get_post_meta( $post_id, 'post', true );
            if(!empty($posts)){
                foreach ( array_filter( explode( ',', $posts ) ) as $id ) {
                    if($this->user_can_edit($id)){
                        // private to publish
                        wp_update_post(array(
                            'ID' => $id,
                            'post_status' => 'publish'
                        ));
                    }
                }
            }
        }
    }

    public function remove_post( $post_id ) {
        $user_id = get_current_user_id();

        if ( !$this->user_can_edit( $post_id ) ) {
            return;
        }

        do_action( 'play_remove_upload', $post_id, $user_id );
        
        if( apply_filters('play_force_delete', true) ){
            // delete post
            wp_delete_post( $post_id );
        }else{
            wp_trash_post( $post_id );
        }
    }

    public function save_upload( $request ) {

        if ( !is_user_logged_in() ) {
            return Play_Utils::instance()->response(
                array(
                    'status' => 'error',
                    'msg'   => __( 'You need login', 'play-block' )
                )
            );
        }

        $user_id = get_current_user_id();

        $args = $this->get_upload_form_defaults();

        $pass = true;

        $ID          = ! empty( $request[ 'post_id' ] ) ? absint( wp_unslash( $request[ 'post_id' ] ) ) : 0;
        $thumbnail_id= ! empty( $request[ 'thumbnail_id' ] ) ? absint( wp_unslash( $request[ 'thumbnail_id' ] ) ) : 0;
        $title       = ! empty( $request[ 'title' ] ) ? sanitize_text_field( wp_unslash( $request[ 'title' ] ) ) : '';
        $content     = ! empty( $request[ 'content' ] ) ? wp_kses( $request[ 'content' ], array(
                'br'     => array(),
                'em'     => array(),
                'strong' => array(),
                'small'  => array(),
                'span'   => array(),
                'ul'     => array(),
                'li'     => array(),
                'ol'     => array(),
                'p'      => array(),
                'a'      => array(
                    'href' => array(),
                )
            )
        ) : '';
        $post_date   = ! empty( $request[ 'post_date' ] ) ? sanitize_text_field( wp_unslash( $request[ 'post_date' ] ) ) : '';
        $post_type   = ! empty( $request[ 'post_type' ] ) ? sanitize_text_field( wp_unslash( $request[ 'post_type' ] ) ) : 'post';
        $post_status = ! empty( $request[ 'post_status' ] ) ? sanitize_text_field( wp_unslash( $request[ 'post_status' ] ) ) : 'private';
        $type        = ! empty( $request[ 'type' ] ) ? sanitize_text_field( wp_unslash( $request[ 'type' ] ) ) : 'single';
        $media_type  = ! empty( $request[ 'media_type' ] ) ? sanitize_text_field( wp_unslash( $request[ 'media_type' ] ) ) : '';

        if ( ! $this->user_can_post_public( $type ) && ( $post_status == 'publish' ) ) {
            $post_status = 'private';
        }
        $posts   = ! empty( $request[ 'post' ] ) ? sanitize_text_field( wp_unslash( $request[ 'post' ] ) ) : '';
        $cats    = ! empty( $request[ 'cat' ] ) ? sanitize_text_field( wp_unslash( $request[ 'cat' ] ) ) : '';
        $tags    = ! empty( $request[ 'tag' ] ) ? sanitize_text_field( wp_unslash( $request[ 'tag' ] ) ) : '';
        $artists = ! empty( $request[ 'artist' ] ) ? sanitize_text_field( wp_unslash( $request[ 'artist' ] ) ) : '';

        $stream         = ! empty( $request[ 'stream' ] ) ? esc_url_raw( wp_unslash( $request[ 'stream' ] ) ) : '';
        $stream_url     = ! empty( $request[ 'stream_url' ] ) ? esc_url_raw( wp_unslash( $request[ 'stream_url' ] ) ) : '';
        $tracks         = ! empty( $request[ 'tracks' ] ) ? sanitize_text_field( wp_unslash( $request[ 'tracks' ] ) ) : '';
        $metadata       = ! empty( $request[ 'metadata' ] ) ? sanitize_text_field( wp_unslash( $request[ 'metadata' ] ) ) : '';
        $duration       = ! empty( $request[ 'duration' ] ) ? sanitize_text_field( wp_unslash( $request[ 'duration' ] ) ) : '';
        $duration       = Play_Utils::instance()->timeToMS($duration);

        $downloadable   = ! empty( $request[ 'downloadable' ] ) ? wp_unslash( $request[ 'downloadable' ] ) : '';
        $download_url   = ! empty( $request[ 'download_url' ] ) ? esc_url_raw( wp_unslash( $request[ 'download_url' ] ) ) : '';
        $purchase_title = ! empty( $request[ 'purchase_title' ] ) ? sanitize_text_field( wp_unslash( $request[ 'purchase_title' ] ) ) : '';
        $purchase_url   = ! empty( $request[ 'purchase_url' ] ) ? esc_url_raw( wp_unslash( $request[ 'purchase_url' ] ) ) : '';

        $copyright      = ! empty( $request[ 'copyright' ] ) ? sanitize_text_field( wp_unslash( $request[ 'copyright' ] ) ) : '';

        $regular_price  = ! empty( $request[ '_regular_price' ] ) ? sanitize_text_field( wp_unslash( $request[ '_regular_price' ] ) ) : '';
        $sale_price     = ! empty( $request[ '_sale_price' ] ) ? sanitize_text_field( wp_unslash( $request[ '_sale_price' ] ) ) : '';

        if ( $ID > 0 && ! $this->user_can_edit( $ID ) ) {
            return;
        }

        $error = '';

        $files = $request->get_file_params();
        if ( $ID == 0 && ( isset( $files[ 'image' ] ) && $files[ 'image' ][ "size" ] == 0 ) ) {
            $pass  = apply_filters('play_upload_thumbnail_required', false);
            $error = $args[ 'label_error_poster' ];
        }

        if ( empty( $title ) ) {
            $pass  = false;
            $error = $args[ 'label_error_title' ];
        }

        if ( empty( $stream ) && ( $type == 'single' ) ) {
            $pass  = false;
            $error = $args[ 'label_error_stream' ];
        }

        $cat    = $args[ 'cat_slug' ];
        $tag    = $args[ 'tag_slug' ];
        $artist = $args[ 'artist_slug' ];
        if ( $post_type == 'station' ) {
            $cat = 'genre';
            $tag = 'station_tag';
        } elseif ( $post_type == 'product' ) {
            $cat = 'product_cat';
            $tag = 'product_tag';
        } elseif ( $post_type == 'download' ) {
            $cat = 'download_category';
            $tag = 'download_tag';
        }

        $tag = apply_filters('play_upload_form_tag', $tag, $ID);
        $cat = apply_filters('play_upload_form_cat', $cat, $ID);
        $key = apply_filters('play_upload_form_key', 'station_key', $ID);
        $chord = apply_filters('play_upload_form_chord', 'station_chord', $ID);
        if(apply_filters('play_use_genre', true)){
            $cat = 'genre';
        }

        // exclude
        $exclude_tags = apply_filters( 'play_exclude_tags', array( 'Featured', 'Editor Choice' ) );
        $tags         = explode( ',', $tags );
        $tags         = array_map('trim', $tags);
        $tags         = array_diff( $tags, $exclude_tags );
        // only admin tags
        if(apply_filters('play_use_internal_tags', false)){
            $include_tags = [];
            $ts = get_terms( array(
                'taxonomy' => $tag,
                'hide_empty' => false,
            ) );
            foreach ($ts as $t){
                $include_tags[] = $t->name;
            }
            $tags = array_intersect($tags, $include_tags);
        }

        // youtube shorts
        if( strpos($stream, '/shorts/') !== false || $media_type == 'short' ){
            $type = 'short';
        }

        if ( $pass ) {
            // get preview thumbnail
            $old_thumbnail_id = 0;
            if($ID > 0){
                $old_thumbnail_id = get_post_thumbnail_id($ID);
            }
            
            // get thumbnail
            if ( isset( $files[ 'image' ] ) && $files[ 'image' ][ "size" ] > 0 ){
                $attach_id = $this->upload_image( 'image' );
                if ( ! is_wp_error( $attach_id ) ) {
                    $thumbnail_id = $attach_id;
                }
            }

            // update thumbnail if album thumbnail changed
            if($ID > 0 && $type == 'album' && $old_thumbnail_id !== $thumbnail_id && !empty($posts)){
                foreach ( array_filter( explode( ',', $posts ) ) as $id ) {
                    if($this->user_can_edit($id)){
                        $_id = get_post_thumbnail_id($id);
                        // only update if the single's thumbnail is same as album old thumbnail
                        if($_id == $old_thumbnail_id){
                            set_post_thumbnail($id, $thumbnail_id);
                        }
                    }
                }
            }

            $post = array(
                'ID'            => $ID,
                'post_title'    => wp_strip_all_tags( $title ),
                'post_content'  => $content,
                'post_status'   => $post_status,
                'post_author'   => $user_id,
                'post_type'     => $post_type,
                'post_date'     => $post_date,
                'post_date_gmt' => $post_date,
                'comment_status'=> get_default_comment_status($post_type),
                'tax_input'     => array(
                    $cat    => $cats,
                    $tag    => $tags,
                    $artist => explode( ',', $artists )
                ),
                'meta_input'    => array(
                    'type'           => $type,
                    'stream'         => $stream,
                    'stream_url'     => $stream_url,
                    'post'           => $posts,
                    'duration'       => $duration,
                    'downloadable'   => $downloadable,
                    'download_url'   => $download_url,
                    'purchase_title' => $purchase_title,
                    'purchase_url'   => $purchase_url,
                    'copyright'      => $copyright
                )
            );

            // update thumbnail
            if($thumbnail_id > 0){
                $post[ 'meta_input' ][ '_thumbnail_id' ] = $thumbnail_id;
            }

            // update metadata
            if ( ! empty( $metadata ) ) {
                $metadata = json_decode( $metadata );
                // waveform
                if ( ! empty( $metadata->waveform ) ) {
                    $post[ 'meta_input' ][ 'waveform_data' ] = $metadata->waveform;
                }
                // bpm
                if ( ! empty( $metadata->bpm ) ) {
                    $post[ 'meta_input' ][ 'bpm' ] = $metadata->bpm;
                }
                // key
                if ( ! empty( $metadata->key ) ) {
                    $post[ 'tax_input' ][$key] = explode(',', $metadata->key);
                }
                // chord
                if ( ! empty( $metadata->chord ) ) {
                    $post[ 'tax_input' ][$chord] = explode(',', $metadata->chord);
                }
            }
            
            $post_id = wp_insert_post( apply_filters( 'frontend_upload_post', $post, $request ) );
            
            // save tracks when bulk upload
            if ( ! empty( $tracks ) ) {
                $tracks = json_decode( $tracks );
                $posts  = [];
                foreach ( $tracks as $track ) {
                    if( empty($track->url) ){
                        continue;
                    }
                    $post = array(
                        'post_title'    => wp_strip_all_tags( $track->title ),
                        'post_status'   => $post_status,
                        'post_author'   => $user_id,
                        'post_type'     => ( play_get_option( 'post_type' ) ? play_get_option( 'post_type' ) : 'station' ),
                        'post_date'     => $post_date,
                        'post_date_gmt' => $post_date,
                        'meta_input'    => array(
                            'type'          => apply_filters( 'play_block_bulk_upload_single_type', 'single' ),
                            'stream'        => esc_url_raw( $track->url ),
                            'downloadable'  => $downloadable
                        ),
                        'tax_input'     => array(
                            $cat    => $cats
                        ),
                    );

                    // use metadata thumbnail
                    if(!empty($track->metadata->thumbnail_id)){
                        $thumbnail_id = $track->metadata->thumbnail_id;
                    }

                    if($thumbnail_id > 0){
                        $post['meta_input']['_thumbnail_id'] = $thumbnail_id;
                    }

                    if(!empty($track->metadata->waveform)){
                        $post['meta_input']['waveform_data'] = $track->metadata->waveform;
                    }

                    if(!empty($track->metadata->bpm)){
                        $post['meta_input']['bpm'] = $track->metadata->bpm;
                    }

                    if(isset($track->metadata->length_formatted)){
                        $post['meta_input']['duration'] = Play_Utils::instance()->timeToMS( $track->metadata->length_formatted );
                    }

                    if(isset($track->metadata->artist)){
                        $post['tax_input'][$artist] = explode( ',', $track->metadata->artist );
                    }

                    if(isset($track->metadata->key)){
                        $post['tax_input'][$key] = explode(',', $track->metadata->key);
                    }

                    if(isset($track->metadata->chord)){
                        $post['tax_input'][$chord] = explode(',', $track->metadata->chord);
                    }

                    if(isset($track->metadata->tag)){
                        $post['tax_input'][$tag] = $track->metadata->tag;
                    }
                    
                    $id = wp_insert_post( apply_filters( 'frontend_upload_post_track', $post ) );
                    
                    do_action( 'play_block_upload_after_insert', $user_id, $id );

                    $posts[] = $id;
                }
                $posts = implode( ',', $posts );
                update_post_meta( $post_id, 'post', $posts );
            }

            // do something if it's a Products
            if ( $post_type == 'product' ) {

                update_post_meta( $post_id, '_regular_price', $regular_price );
                update_post_meta( $post_id, '_sale_price', $sale_price );

                // save as virtual and downloable product
                update_post_meta( $post_id, '_virtual', 'yes' );
                if($downloadable !== ''){
                    update_post_meta( $post_id, '_downloadable', 'yes' );
                    // _downloadable_files
                    $file = $download_url === '' ? $stream : $download_url;
                    $item = array(
                        'id' => wp_generate_uuid4(),
                        'name' => basename( $file ),
                        'file' => $file
                    );
                    update_post_meta( $post_id, '_downloadable_files', array( $item ) );
                }else{
                    update_post_meta( $post_id, '_downloadable', 'no' );
                }

                if ( '' !== $sale_price ) {
                    update_post_meta( $post_id, '_price', $sale_price );
                } else {
                    update_post_meta( $post_id, '_price', $regular_price );
                }

                if ( ! empty( $purchase_url ) ) {
                    wp_set_object_terms( $post_id, 'external', 'product_type' );

                    update_post_meta( $post_id, '_product_url', $purchase_url );
                    update_post_meta( $post_id, '_button_text', $purchase_title );
                }
            } elseif ( $post_type == 'download' ) {

                update_post_meta( $post_id, 'edd_price', $regular_price );
                update_post_meta( $post_id, 'edd_sale_price', $sale_price );

                if($downloadable !== ''){
                    // edd_download_files
                    $file = $download_url === '' ? $stream : $download_url;
                    $item = array(
                        'index' => 0,
                        'id' => wp_generate_uuid4(),
                        'thumbnail_size' => false,
                        'attachment_id' => $this->get_attachment_id( $file ),
                        'name' => basename( $file ),
                        'file' => $file,
                        'condition' => 'all'
                    );
                    update_post_meta( $post_id, 'edd_download_files', array( $item ) );
                }
            }

            do_action( 'frontend_upload' );

            $post[ 'post_id' ]   = $post_id;
            $post[ 'permalink' ] = get_permalink( $post_id );
            $post[ 'thumbnail' ] = get_the_post_thumbnail_url( $post_id );

            if($ID == 0){
                do_action( 'play_block_upload_after_insert', $user_id, $post_id );
            }else{
                do_action( 'play_block_upload_after_save', $user_id, $post_id );
            }

            do_action( 'play_block_upload', $user_id, $post_id );
            $response = apply_filters('play_upload_saved_response', array(
                'status'   => 'success',
                'msg'      => apply_filters('play_upload_saved', play_get_text( 'upload-saved' )),
                'redirect' => $post['permalink'],
                'post_id'  => $post_id
            ));
            
            return Play_Utils::instance()->response( $response );
        } else {
            return Play_Utils::instance()->response(
                array(
                    'status' => 'error',
                    'msg'    => $error
                )
            );
        }
    }

    public function upload_stream( $request ) {
        if ( !is_user_logged_in() ) {
            return Play_Utils::instance()->response(
                array(
                    'status' => 'error',
                    'msg'   => __( 'You need login', 'play-block' )
                )
            );
        }

        $max_upload_size = wp_max_upload_size();

        $files = $request->get_file_params();

        if ( (!empty( $files ) && !empty( $files['file'] ) && $files['file'][ 'size' ] > $max_upload_size) || empty($files) ) {
            return Play_Utils::instance()->response(
                array(
                    'status' => 'error',
                    'msg'    => sprintf( __( 'Maximum upload file size: %s.', 'play-block' ), esc_html( size_format( $max_upload_size ) ) )
                )
            );
        }

        do_action( 'play_upload_stream_before' );

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $ext = pathinfo( $files['file']['name'] );
        if( $ext['extension'] === NULL || $ext['extension'] === '' ) {
            $files['file']['name'] .= apply_filters( 'play_block_upload_default_extension', '.mp3' );
        }

        $mimes = apply_filters( 'play_allowed_mime_upload_types', array(
            'mpeg|mpg|mpe'  => 'video/mpeg',
            'mp4|m4v'       => 'video/mp4',
            'ogv'           => 'video/ogg',
            'mp3|m4a|m4b'   => 'audio/mpeg',
            'aac'           => 'audio/aac',
            'wav'           => 'audio/wav',
            'ogg|oga'       => 'audio/ogg',
        ) );

        $stream_id = media_handle_upload( 'file', false, array(), array( 'test_form' => false, 'mimes' => $mimes ) );

        do_action( 'play_upload_stream_after', $stream_id );

        if ( ! is_wp_error( $stream_id ) ) {
            $metadata = wp_get_attachment_metadata( $stream_id );
            // set cover image
            $thumbnail_id = get_post_thumbnail_id($stream_id);
            $thumbnail = wp_get_attachment_image_src( $thumbnail_id, 'large' );
            if(!empty($thumbnail[0])){
                $metadata['thumbnail'] = $thumbnail[0];
                $metadata['thumbnail_id'] = $thumbnail_id;
            }
            $this->set_media_type($metadata);
            return Play_Utils::instance()->response(
                array(
                    'status'   => 'success',
                    'url'      => wp_get_attachment_url( $stream_id ),
                    'metadata' => $metadata
                )
            );
        } else {
            $err = $stream_id->get_error_message();

            return Play_Utils::instance()->response(
                array(
                    'status' => 'error',
                    'msg'    => $err
                )
            );
        }
    }

    public function upload_featuredimg( $request ){
        if(current_user_can('manage_options')){
            $file = $request->get_file_params();
            if ( isset( $file[ 'image' ] ) && $file[ 'image' ][ "size" ] > 0 ){
                // featured image
                $thumbnail_id = $this->upload_image('image');
                if ( ! is_wp_error( $thumbnail_id ) ) {
                    return Play_Utils::instance()->response(
                        array(
                            'featured_media' => $thumbnail_id
                        )
                    );
                }
            }
            return Play_Utils::instance()->response(
                array( 'error' => 'Import error, Empty image' )
            );
        }else{
            return Play_Utils::instance()->response(
                array( 'error' => 'You do not have permission to upload image' )
            );
        }
    }

    public function upload_image($file_id) {
        // image in upload form
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        return media_handle_upload( $file_id, 0 );
    }

    public function the_post_color($post_id){
        $color = $this->get_post_color($post_id);
        if(!empty($color)){
            echo sprintf('style="--color:%s"', $color);
        }
    }

    public function get_post_color($post_id){
        $color = false;
        if(apply_filters('play_enable_post_color', false)){
            $color = get_post_meta($post_id, 'color', true);
            if(empty($color)){
                $color = $this->get_thumbnail_color($post_id);
            }
        }
        return $color;
    }

    public function get_thumbnail_color($post_id){
        $color = false;
        if(apply_filters('play_enable_thumbnail_color', false)){
            $attachment_id = get_post_thumbnail_id($post_id);
            $color = get_post_meta($attachment_id, 'color', true);
            if(empty($color)){
                $file = wp_get_attachment_image_src($attachment_id);
                if ( $file ){
                    $color = $this->get_image_color( $file[0] );
                    if(!empty($color)){
                        update_post_meta( $attachment_id, 'color', $color );
                    }
                }
            }
        }
        return $color;
    }

    public function get_image_color($url){
        $response = wp_remote_get($url);
        if (!is_wp_error($response) ) {
            $str = wp_remote_retrieve_body($response);
        }else{
            return;
        }

        $img = imagecreatefromstring($str);
        $w = imagesx($img);
        $h = imagesy($img);

        $tmp = imagecreatetruecolor(1, 1);
        imagecopyresampled($tmp, $img, 0, 0, 0, 0, 1, 1, $w, $h);

        $rgb = imagecolorat($tmp, 0, 0);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        // use dark color
        $r = min($r*.75, 110);
        $g = min($g*.75, 110);
        $b = min($b*.75, 110);

        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }

    public function set_media_type(&$metadata){
        if(isset($metadata['width']) && isset($metadata['height']) && ((int)$metadata['width'] / (int)$metadata['height']) < 0.6 ){
            $metadata['media_type'] = 'short';
        }
    }

    public function get_upload_form_defaults() {
        $defaults = array(
            'post_type'          => ( play_get_option( 'post_type' ) ? play_get_option( 'post_type' ) : 'station' ),
            'cat_slug'           => 'genre',
            'tag_slug'           => 'station_tag',
            'artist_slug'        => 'artist',
            'label_error_title'  => play_get_text( 'title-required' ),
            'label_error_poster' => play_get_text( 'poster-required' ),
            'label_error_stream' => play_get_text( 'stream-required' ),
        );

        return apply_filters( 'upload_form_defaults', $defaults );
    }

    public function add_attachment($attachment_id) {
        if ( is_admin() && play_get_option( 'post_attachment' ) && ( wp_attachment_is( 'video', $attachment_id ) || wp_attachment_is( 'audio', $attachment_id ) ) ) {
            $src  = wp_get_attachment_url( $attachment_id );
            $metadata = wp_generate_attachment_metadata( $attachment_id, get_attached_file($attachment_id) );
            $this->set_media_type($metadata);
            $att  = get_post($attachment_id);
            $post_type = play_get_option( 'post_type' ) ? play_get_option( 'post_type' ) : 'station';
            $post = array(
                'post_title'    => $att->post_title,
                'post_content'  => $att->post_content,
                'post_type'     => $post_type,
                'post_status'   => 'publish',
                'comment_status'=> get_default_comment_status($post_type),
                'meta_input'    => array(
                    'type'           => isset($metadata['media_type']) ? $metadata['media_type'] : 'single',
                    'stream'         => $src,
                    'duration'       => (int)$metadata['length'] * 1000,
                )
            );

            $post = apply_filters('play_add_attachment', $post, $attachment_id);

            $post_id = wp_insert_post($post);

            if(! empty( $metadata['artist'] )){
                wp_set_object_terms($post_id, 'artist', $meta['artist']);
            }
            if(! empty( $metadata['genre'] )){
                wp_set_object_terms($post_id, 'genre', $meta['genre']);
            }
            
        }
    }

    public function wp_dropdown_cats_multiple( $output, $r ) {
        if ( isset( $r[ 'multiple' ] ) && $r[ 'multiple' ] ) {
            $output = preg_replace( '/^<select/i', '<select multiple', $output );
            $output = str_replace( "name='{$r['name']}'", "name='{$r['name']}[]'", $output );
            foreach ( array_map( 'trim', explode( ",", $r[ 'selected' ] ) ) as $value ) {
                $output = str_replace( "value=\"{$value}\"", "value=\"{$value}\" selected", $output );
            }
        }

        return $output;
    }

    public function get_upload_edit_link( $post_id = null ) {
        if ( ! is_user_logged_in() ) {
            return;
        }
        if ( play_get_option( 'page_upload' ) ) {
            return get_permalink( play_get_option( 'page_upload' ) ) . '?post_id=' . $post_id;
        }
        $url = apply_filters( 'get_endpoint_url', 'upload', '?post_id=' . $post_id, get_author_posts_url( get_current_user_id() ) );

        return apply_filters( 'upload_edit_link', $url );
    }

    public function user_can_upload() {
        $can = false;
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $role = play_get_option( 'upload_role' );
        $roles = is_array($role) ? array_filter( $role ) : array('administrator','editor','author','contributor');
        
        $user = wp_get_current_user();
        if( count( array_intersect($roles, $user->roles) ) > 0 ){
            $can = true;
        }

        return apply_filters( 'user_can_upload', $can );
    }

    public function user_can_edit($post_id){
        $can = false;
        if ( ! is_user_logged_in() ) {
            $can = false;
        }
        if ( $post_id > 0 ) {
            $author = get_post_field( 'post_author', $post_id );
            if ( (int) get_current_user_id() == (int) $author ) {
                $can = true;
            }
        }
        return apply_filters( 'user_can_edit', $can, $post_id );
    }

    public function user_can_upload_stream() {
        $can = false;
        if( play_get_option( 'post_upload' ) || 'true' === get_user_meta( get_current_user_id(), 'verified', true ) ){
            $can = true;
        }
        return apply_filters( 'user_can_upload_stream', $can );
    }

    public function user_can_upload_online() {
        return apply_filters( 'user_can_upload_online', play_get_option( 'post_upload_online' ) );
    }

    public function user_can_post_public( $type = 'single' ) {
        $public = play_get_option( 'post_public' );
        if ( $type == 'playlist' ) {
            $public = play_get_option( 'post_playlist_public' );
        }
        if ( play_get_option( 'post_verified_public' ) && 'true' === get_user_meta( get_current_user_id(), 'verified', true ) ) {
            $public = true;
        }

        return apply_filters( 'user_can_post_public', $public );
    }

    public function get_attachment_id( $url ) {
        global $wpdb;
        $results = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $url ));
        if( !empty($results) ){
            return $results[0];
        }
        return 0;
    }

    public function upload_form_shortcode() {
        return $this->upload_form($_REQUEST);
    }

    public function upload_form($request = null) {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $args = $this->get_upload_form_defaults();
        $can_upload = $this->user_can_upload();

        $post_id = isset( $request[ 'post_id' ] ) ? (int) $request[ 'post_id' ] : 0;

        if ( $post_id > 0 && !$this->user_can_edit($post_id) ) {
            return;
        }

        if ($post_id == 0 && $can_upload == false ) {
            return;
        }

        $type = 'single';
        if ( $post_id ) {
            $post       = get_post( $post_id );
            $type       = get_post_meta( $post_id, 'type', true );
            $post->type = $type ? $type : 'single';
        } else {
            $post               = new stdClass();
            $post->ID           = 0;
            $post->post_title   = '';
            $post->post_content = '';
            $post->post_date    = date( "Y-m-d" );
            $post->post_status  = 'publish';
            $post->type         = 'single';
            if ( isset( $request[ 'type' ] ) ) {
                $post->type = sanitize_text_field( $request[ 'type' ] );
            }
            $post->post_type = $args[ 'post_type' ];
        }

        $data = array(
            'post'                   => $post,
            'redirect'               => get_author_posts_url( get_current_user_id() ),
            'user_can_upload_stream' => $this->user_can_upload_stream(),
            'user_can_upload_online' => $this->user_can_upload_online(),
            'user_can_post_public'   => $this->user_can_post_public( $type ),
            'user_can_upload'        => $can_upload
        );

        return ( $post_id || isset( $request[ 'form' ] ) ) ? play_get_template_html( 'form/upload.php', $data ) : play_get_template_html( 'form/upload-start.php', $data );
    }

}

Play_Upload::instance();
