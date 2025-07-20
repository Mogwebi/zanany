<?php

defined( 'ABSPATH' ) || exit;

class Play_Post_Type {

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function __construct() {
        add_action( 'init', array( $this, 'register' ) );
        add_action( 'init', array( $this, 'term' ) );
        add_action( 'play_maybe_flush_rewrite_rules', array( $this, 'maybe_flush_rewrite_rules' ) );
        add_action( 'play_flush_rewrite_rules', array( $this, 'flush_rewrite_rules' ) );
        add_filter( 'post_type_link', array( $this, 'post_type_link' ), 10, 4 );

        do_action( 'play_block_post_type_init', $this );

        function the_archive_thumbnail($before = '', $after = ''){
            $thumbnail = Play_Post_Type::instance()->get_the_archive_thumbnail();
            if ( $thumbnail ) {
                echo $before . $thumbnail . $after;
            }
        }

        function play_get_labels($singular, $plural = ''){
            return Play_Post_Type::instance()->get_labels( $singular, $plural );
        }
    }

    public function term(){
        $terms = apply_filters( 'play_taxonomy_columns', [ 'genre', 'artist', 'mood', 'activity', 'station_tag', 'product_tag', 'product_cat', 'download_category', 'download_tag' ] );

        foreach ( $terms as $key => $term ) {
            add_action( $term . '_add_form_fields', array( $this, 'edit_term_fields' ) );
            add_action( $term . '_edit_form_fields', array( $this, 'edit_term_fields' ) );
            add_filter( 'manage_edit-' . $term . '_columns', array( $this, 'term_columns' ), 10 );
            add_filter( 'manage_' . $term . '_custom_column', array( $this, 'term_column' ), 10, 3 );
        }
        
        add_action( 'created_term', array( $this, 'save_term_fields' ), 10, 3 );
        add_action( 'edit_term', array( $this, 'save_term_fields' ), 10, 3 );
        add_action( 'restrict_manage_posts', array( $this, 'extra_tablenav' ), 10, 2);
        add_filter( 'parse_query', array( $this, 'parse_filter' ) );
    }

    public function get_the_archive_thumbnail($term = 0){
        if ( ! $term && ( is_tax() || is_tag() || is_category() ) ) {
            $term = get_queried_object();
            if ( $term ) {
                $term = $term->term_id;
            }
        }

        $thumbnail = get_term_meta( $term, 'thumbnail_id', true );
        return $thumbnail ? wp_get_attachment_image( $thumbnail, '' ) : '';
    }

    public function register() {
        do_action( 'play_register_post_type' );

        if( apply_filters('play_register_station', true) ){
            $this->register_station();
        }

        if( play_get_option('register_playlist') ){
            $this->register_playlist();
        }

        $this->register_taxonomies();

        do_action( 'play_after_register_post_type' );
    }

    public function register_station() {
        register_post_type(
            'station',
            apply_filters(
                'play_register_post_type_station',
                array(
                    'labels'       => $this->get_labels( 'Station', 'Stations' ),
                    'public'       => true,
                    'has_archive'  => 'stations',
                    'show_in_rest' => true,
                    'supports'     => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'custom-fields' ),
                    'menu_icon'    => plugin_dir_url( dirname( __FILE__ ) ) . 'build/icon.station.svg',
                    'rewrite'      => array(
                        'with_front' => true,
                        'slug' => play_get_option('station_base', 'station')
                    ),
                )
            )
        );
    }

    public function register_playlist(){
        register_post_type(
            'playlist',
            apply_filters(
                'play_register_post_type_playlist',
                array(
                    'labels'       => $this->get_labels( 'Playlist', 'Playlists' ),
                    'public'       => true,
                    'has_archive'  => 'playlists',
                    'show_in_rest' => true,
                    'supports'     => array( 'title', 'editor', 'author', 'thumbnail', 'comments', 'custom-fields' ),
                    'menu_icon'    => plugin_dir_url( dirname( __FILE__ ) ) . 'build/icon.playlist.svg',
                    'rewrite'      => array(
                        'with_front' => true,
                        'slug' => play_get_option('playlist_base', 'playlist')
                    ),
                )
            )
        );
    }

    public function register_taxonomies() {
        $types = play_get_option( 'play_types', array('station') );

        register_taxonomy(
            'genre',
            apply_filters( 'play_register_post_taxonomy_genre_types', $types ),
            apply_filters(
                'play_register_post_taxonomy_genre',
                array(
                    'labels'            => $this->get_labels( 'Genre', 'Genres' ),
                    'show_in_rest'      => true,
                    'hierarchical'      => true,
                    'show_ui'           => true,
                    'show_admin_column' => true,
                    'capabilities'      => array(
                        'assign_terms'  => 'read'
                    ),
                    'rewrite'           => array(
                        'slug' => play_get_option('genre_base', 'genre')
                    ),
                )
            )
        );

        register_taxonomy(
            'station_tag',
            apply_filters( 'play_register_post_taxonomy_tag_types', array( 'station' ) ),
            apply_filters(
                'play_register_post_taxonomy_tag',
                array(
                    'labels'            => $this->get_labels( 'Tag', 'Tags' ),
                    'show_in_rest'      => true,
                    'hierarchical'      => false,
                    'show_ui'           => true,
                    'show_admin_column' => true,
                    'capabilities'      => array(
                        'assign_terms'  => 'read'
                    ),
                    'rewrite'           => array(
                        'slug' => play_get_option('tag_base', 'station-tag')
                    ),
                )
            )
        );

        if(apply_filters('play_register_audio_type', true)){
            register_taxonomy(
                'artist',
                apply_filters( 'play_register_post_taxonomy_artist_types', $types ),
                apply_filters(
                    'play_register_post_taxonomy_artist',
                    array(
                        'labels'            => $this->get_labels( 'Artist', 'Artists' ),
                        'show_in_rest'      => true,
                        'hierarchical'      => false,
                        'show_ui'           => true,
                        'show_admin_column' => false,
                        'capabilities'      => array(
                            'assign_terms'  => 'read'
                        ),
                        'sort'              => true,
                        'args'              => array(
                            'orderby' => 'term_order',
                        ),
                        'rewrite'           => array(
                            'slug' => play_get_option('artist_base', 'artist')
                        ),
                    )
                )
            );

            register_taxonomy(
                'mood',
                apply_filters( 'play_register_post_taxonomy_mood_types', $types ),
                apply_filters(
                    'play_register_post_taxonomy_mood',
                    array(
                        'labels'            => $this->get_labels( 'Mood', 'Moods' ),
                        'show_in_rest'      => true,
                        'hierarchical'      => false,
                        'show_ui'           => true,
                        'show_admin_column' => true,
                        'rewrite'           => array( 
                            'slug' => play_get_option('mood_base', 'mood')
                        ),
                    )
                )
            );

            register_taxonomy(
                'activity',
                apply_filters( 'play_register_post_taxonomy_activity_types', $types ),
                apply_filters(
                    'play_register_post_taxonomy_activity',
                    array(
                        'labels'            => $this->get_labels( 'Activity', 'Activities' ),
                        'show_in_rest'      => true,
                        'hierarchical'      => false,
                        'show_ui'           => true,
                        'show_admin_column' => true,
                        'rewrite'           => array( 
                            'slug' => play_get_option('activity_base', 'activity')
                        ),
                    )
                )
            );
        }

        // attribute taxonomies
        $attribute_taxonomies = play_get_attributes();
        if ( $attribute_taxonomies ) {
            foreach ( $attribute_taxonomies as $tax ) {
                $name = play_attribute_taxonomy_name( $tax->attribute_name, $tax->post_type );

                if ( $name ) {
                    $public          = absint( isset( $tax->attribute_public ) ? $tax->attribute_public : 1 );
                    $hierarchical    = absint( isset( $tax->attribute_hierarchical ) ? $tax->attribute_hierarchical : 1 );
                    $label           = ! empty( $tax->attribute_label ) ? $tax->attribute_label : $tax->attribute_name;
                    $taxonomy_data   = array(
                        'hierarchical'          => 1 === $hierarchical,
                        'labels'                => $this->get_labels( $label ),
                        'show_in_rest'          => true,
                        'show_ui'               => true,
                        'show_in_quick_edit'    => false,
                        'show_in_menu'          => false,
                        'query_var'             => 1 === $public,
                        'rewrite'               => false,
                        'sort'                  => false,
                        'public'                => 1 === $public,
                        'show_in_nav_menus'     => 1 === $public && apply_filters( 'play_{$tax->post_type}_attribute_show_in_nav_menus', false, $name ),
                    );

                    if ( 1 === $public && sanitize_title( $tax->attribute_name ) ) {
                        $taxonomy_data['rewrite'] = array(
                            'slug' => sanitize_title($tax->attribute_name)
                        );
                    }

                    register_taxonomy( $name, apply_filters( "play_{$tax->post_type}_taxonomy_objects_{$name}", array( $tax->post_type ) ), apply_filters( "play_{$tax->post_type}_taxonomy_args_{$name}", $taxonomy_data ) );
                }
            }
        }
    }

    public function get_labels( $singular, $plural = '' ) {
        $locale = get_locale();
        if ( $plural == '' ) {
            $plural = $singular;
        }
        $labels = array(
            'name'                       => $plural,
            'singular_name'              => $singular,
            'search_items'               => sprintf( __( 'Search %s' ), $plural ),
            'all_items'                  => sprintf( __( 'All %s' ), $plural ),
            'parent_item'                => sprintf( __( 'Parent %s' ), $plural ),
            'parent_item_colon'          => sprintf( __( 'Parent %s:' ), $plural ),
            'edit_item'                  => sprintf( __( 'Edit %s' ), $singular ),
            'update_item'                => sprintf( __( 'Update %s' ), $singular ),
            'add_new_item'               => sprintf( __( 'Add New %s' ), $singular ),
            'add_new'                    => __( 'Add New' ),
            'new_item'                   => sprintf( __( 'Add New %s' ), $singular ),
            'view_item'                  => sprintf( __( 'View %s' ), $singular ),
            'popular_items'              => sprintf( __( 'Popular %s' ), $plural ),
            'new_item_name'              => sprintf( __( 'New %s Name' ), $singular ),
            'separate_items_with_commas' => sprintf( __( 'Separate %s with commas' ), $plural ),
            'add_or_remove_items'        => sprintf( __( 'Add or remove %s' ), $plural ),
            'choose_from_most_used'      => sprintf( __( 'Choose from the most used %s' ), $plural ),
            'not_found'                  => sprintf( __( 'No %s found' ), $plural ),
            'not_found_in_trash'         => sprintf( __( 'No %s found in trash' ), $plural ),
            'menu_name'                  => $plural,
            'name_admin_bar'             => $singular
        );

        return apply_filters( 'play_' . strtolower( $singular ) . '_labels_locale', $labels, $locale );
    }

    public function edit_term_fields( $term ) {
        wp_enqueue_media();
        $thumbnail_id = 0;
        if ( isset( $term->term_id ) ) {
            $thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
        }
        $wrap   = '<div class="form-field term-thumbnail-wrap"><label>Thumbnail</label>%s</div>';
        $el     = '<img src="%s" width="60px" height="60px" style="background: #fff;"><input type="hidden" name="thumbnail_id" value="' . $thumbnail_id . '"><button type="button" class="button upload-btn">Upload</button>';
        $holder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $image  = $holder;
        if ( isset( $term->term_id ) ) {
            $wrap = '<tr class="form-field term-thumbnail-wrap"><th scope="row" valign="top"><label>Thumbnail</label></th><td>%s</td></tr>';
            if ( $thumbnail_id ) {
                $image = wp_get_attachment_thumb_url( $thumbnail_id );
            }
        }
        echo sprintf( $wrap, sprintf( $el, $image ) );
    }

    public function save_term_fields( $term_id, $tt_id = '', $taxonomy = '' ) {
        if ( isset( $_POST[ 'thumbnail_id' ] ) ) {
            update_term_meta( $term_id, 'thumbnail_id', absint( $_POST[ 'thumbnail_id' ] ) );
        }
    }

    public function term_columns( $columns ) {
        $new = array();
        foreach ( $columns as $key => $title ) {
            if ( $key == 'description' ) {
                $new[ 'thumb' ] = 'Thumbnail';
            }
            $new[ $key ] = $title;
        }

        return $new;
    }

    public function term_column( $columns, $column, $id ) {
        if ( 'thumb' === $column ) {
            $thumbnail_id = (int) get_term_meta( $id, 'thumbnail_id', true );
            $image        = '';
            if ( $thumbnail_id ) {
                $thumb = wp_get_attachment_thumb_url( $thumbnail_id );
                $image = '<img src="' . esc_url( $thumb ) . '" class="wp-post-image" height="48" width="48" />';
            }
            $columns .= $image;
        }

        return $columns;
    }

    public function extra_tablenav($type, $which){
        if($which !== 'top') return;
        if(in_array($type, play_get_option( 'play_types' ))){
            $wrap = '<select name="meta" id="filter-by-meta">%s</select>';
            $types = play_get_block_types();
            $options = '<option value="">All types</option>';
            foreach ( $types as $key => $value ) {
                $options .= sprintf('<option value="%s" %s>%s</option>', $value, (isset( $_GET['meta'] ) &&  $_GET['meta']==$value) ? 'selected' : '', $key);
            }
            echo sprintf($wrap, $options);
        }
    }

    public function  parse_filter($query) {
        global $pagenow;
        if ( is_admin() && 'edit.php' == $pagenow && isset( $_GET['meta'] ) && $_GET['meta'] != '' ) {
            $meta                              = $_GET['meta'];
            $query->query_vars['meta_key']     = 'type';
            $query->query_vars['meta_value']   = $meta;
            $query->query_vars['meta_compare'] = '=';
      }
    }

    public function post_type_link( $permalink, $post, $leavename, $sample ) {
        if ( 'station' !== $post->post_type ) {
            return $permalink;
        }

        if ( false === strpos( $permalink, '%' ) ) {
            return $permalink;
        }

        $authordata = get_userdata( $post->post_author );
        $author = $authordata->user_nicename;

        $terms = get_the_terms( $post->ID, 'genre' );
        if ( !is_wp_error($terms) && ! empty( $terms ) ) {
            $term = apply_filters( 'play_station_post_type_link_genre', $terms[0], $terms, $post );
            $genre = $term->slug;
        } else {
            $genre = apply_filters( 'play_station_post_type_link_default_genre', '-' );
        }

        $terms = get_the_terms( $post->ID, 'artist' );
        if ( !is_wp_error($terms) && ! empty( $terms ) ) {
            $term = apply_filters( 'play_station_post_type_link_artist', $terms[0], $terms, $post );
            $artist = $term->slug;
        } else {
            $artist = $author;
        }

        $find = array(
            '%year%',
            '%monthnum%',
            '%day%',
            '%hour%',
            '%minute%',
            '%second%',
            '%post_id%',
            '%genre%',
            '%author%',
            '%artist%',
        );

        $replace = array(
            date_i18n( 'Y', strtotime( $post->post_date ) ),
            date_i18n( 'm', strtotime( $post->post_date ) ),
            date_i18n( 'd', strtotime( $post->post_date ) ),
            date_i18n( 'H', strtotime( $post->post_date ) ),
            date_i18n( 'i', strtotime( $post->post_date ) ),
            date_i18n( 's', strtotime( $post->post_date ) ),
            $post->ID,
            $genre,
            $author,
            $artist,
        );

        $permalink = str_replace( $find, $replace, $permalink );

        return $permalink;
    }

    public function maybe_flush_rewrite_rules() {
        if ( 'yes' === get_option( 'play_queue_flush_rewrite_rules' ) ) {
            update_option( 'play_queue_flush_rewrite_rules', 'no' );
            $this->flush_rewrite_rules();
        }
    }

    public function flush_rewrite_rules() {
        flush_rewrite_rules();
    }

}

Play_Post_Type::instance();
