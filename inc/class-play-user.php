<?php

defined( 'ABSPATH' ) || exit;

class Play_User {

    protected static $_instance = null;
    private $users_can_register = null;
    private $user_id;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function __construct() {

        $this->user_id = get_current_user_id();

        add_action( 'init', array( $this, 'add_endpoint' ) );
        add_filter( 'query_vars', array( $this, 'add_vars' ) );

        add_filter( 'get_user_endpoints', array( $this, 'get_user_endpoints' ), 10, 1 );
        add_filter( 'get_endpoint_url', array( $this, 'get_endpoint_url' ), 10, 3 );
        add_filter( 'wp_nav_menu_objects', array( $this, 'nav_menu_items' ), 5, 2 );
        add_filter( 'pass_user_endpoints', array( $this, 'pass_user_endpoints' ), 10, 3 );

        add_filter( 'init', array( $this, 'user_base' ), 0 );
        add_action( 'the_user_links', array( $this, 'user_links' ) );
        add_filter( 'comment_author', array( $this, 'comment_author' ) );
        add_filter( 'get_comment_author_url', array( $this, 'user_uri' ), 10, 1 );

        // add_filter( 'login_url', array( $this, 'login_url' ), 10, 2 );
        add_action( 'admin_head-nav-menus.php', array( $this, 'add_menu_meta_boxes' ) );

        add_filter( 'show_admin_bar', array( $this, 'admin_bar' ), 9999 );

        add_filter( 'template_include', array( $this, 'author_template' ), 99 );
        add_filter( 'theme_page_templates', array( $this, 'add_template_to_select' ), 10, 4 );

        add_action( 'admin_init', array( $this, 'no_dashboard' ) );

        add_filter( 'nav_count', array( $this, 'nav_count' ), 10, 2 );

        do_action( 'play_block_user_init', $this );
    }

    /**
     * user base
     */
    public function user_base() {
        global $wp_rewrite;
        $user_base = trim( play_get_option( 'user_base' ) );
        $wp_rewrite->author_base = apply_filters( 'user_base', $user_base ? $user_base : 'user' );
    }

    public function no_dashboard() {
        if ( ! current_user_can( apply_filters( 'hide_admin_role', 'publish_posts' ) ) && play_get_option( 'hide_admin' ) && ! defined( 'DOING_AJAX' ) ) {
            wp_redirect( home_url() );
            exit;
        }
    }

    /**
     * get user endpoints
     */
    public function get_user_endpoints( $id = null ) {
        $endpoints = array(
            'stations' => array(
                'id' => 'stations',
                'name' => play_get_text( 'stations' ),
                'public' => true
            )
        );

        if(apply_filters('play_register_audio_type', true)){
            $endpoints['albums'] = array(
                'id' => 'albums',
                'name' => play_get_text( 'albums' ),
                'public' => true
            );
        }

        if(apply_filters('play_register_video_type', false)){
            $endpoints['shorts'] = array(
                'id' => 'shorts',
                'name' => play_get_text( 'shorts' ),
                'public' => true
            );
        }

        $epts = array(
            'playlists' => array(
                'id' => 'playlists',
                'name' => play_get_text( 'playlists' ),
                'public' => true
            ),
            'likes' => array(
                'id' => 'likes',
                'name' => play_get_text( 'likes' ),
                'public' => true
            ),
            'followers' => array(
                'id' => 'followers',
                'name' => play_get_text( 'followers' ),
                'public' => true
            ),
            'following' => array(
                'id' => 'following',
                'name' => play_get_text( 'following' ),
                'public' => true
            ),
            'download' => array(
                'id' => 'download',
                'name' => play_get_text( 'downloads' ),
                'public' => false
            ),
            'profile' => array(
                'id' => 'profile',
                'name' => play_get_text( 'profile' ),
                'public' => false
            ),
            'upload' => array(
                'id' => 'upload',
                'name' => play_get_text( 'upload' ),
                'public' => false
            ),
            'notifications' => array(
                'id' => 'notifications',
                'name' => play_get_text( 'notifications' ),
                'public' => false
            ),
            'logout' => array(
                'id' => 'logout',
                'name' => play_get_text( 'logout' ),
                'public' => false
            ),
            'delete-my-account' => array(
                'id' => 'delete-my-account',
                'name' => play_get_text( 'delete-account' ),
                'public' => false
            ),
        );


        $endpoints = apply_filters( 'user_endpoints', array_merge($endpoints, $epts) );

        $end = null;
        if($id){
            $end = $id;
        }

        // rewrite endpoints
        foreach ( $endpoints as $key => $var ) {
            $endpoint = play_get_option($key.'_endpoint');
            if($endpoint && isset($endpoints[$key]['id'])){
                $endpoints[$key]['id'] = $endpoint;
                if($id && $endpoint === $id){
                    $end = $key;
                }
            }
        }

        if($end){
            return $end;
        }

        return $endpoints;
    }

    /**
     * add query vars
     */
    public function add_vars( $vars ) {
        if ( is_author() ) {
            $_vars = $this->get_user_endpoints();
            foreach ( $_vars as $key => $var ) {
                $vars[] = isset($var['id']) ? $var['id'] : $key;
            }
        }

        return $vars;
    }

    /**
     * add user endpoints
     */
    public function add_endpoint() {
        $_vars = $this->get_user_endpoints();
        foreach ( $_vars as $key => $var ) {
            if ( ! empty( $key ) ) {
                $endpoint = isset($var['id']) ? $var['id'] : $key;
                add_rewrite_endpoint( $endpoint, EP_AUTHORS );
            }
        }

        if ( stristr( $_SERVER[ 'REQUEST_URI' ], 'logout' ) ) {
            wp_logout();
            wp_redirect( home_url() );
            exit;
        }

        if ( stristr( $_SERVER[ 'REQUEST_URI' ], 'delete-my-account' ) ) {
            if (is_user_logged_in()) {
                require_once(ABSPATH.'wp-admin/includes/user.php');
                $user_id = get_current_user_id();
                wp_delete_user($user_id);
                wp_logout();
                wp_safe_redirect( site_url() );
                exit;
            }
        }
    }

    /**
     * get endpoint url
     */
    public function get_endpoint_url( $endpoint, $value = '', $permalink = '' ) {
        if ( ! $permalink ) {
            $permalink = get_permalink();
        }

        if ( get_option( 'permalink_structure' ) ) {
            if ( strstr( $permalink, '?' ) ) {
                $query_string = '?' . parse_url( $permalink, PHP_URL_QUERY );
                $permalink    = current( explode( '?', $permalink ) );
            } else {
                $query_string = '';
            }
            $url = trailingslashit( $permalink ) . $endpoint . '/' . $value . $query_string;
        } else {
            $url = add_query_arg( $endpoint, $value, $permalink );
        }

        if ( (int) play_get_option( 'page_upload' ) && $endpoint === 'upload' ) {
            $url = get_permalink( play_get_option( 'page_upload' ) );
        }

        return apply_filters( 'user_endpoint_url', $url, $endpoint, $value, $permalink );
    }

    /**
     * comment author url
     */
    public function comment_author( $author ) {
        global $comment;
        if ( $comment->user_id ) {
            $author = '<a href="' . get_author_posts_url( $comment->user_id ) . '">' . esc_html( $author ) . '</a>';
        }

        return $author;
    }

    /**
     * login url
     */
    public function login_url( $login_url, $redirect ) {
        if ( play_get_option( 'login_page' ) ) {
            return apply_filters( 'play_login_url', get_permalink( play_get_option( 'login_page' ) ), $redirect );
        }

        return apply_filters( 'play_login_url', $login_url, $redirect );
    }

    public function add_template_to_select( $post_templates, $wp_theme, $post, $post_type ) {
        $post_templates[ 'author.php' ] = __( 'User', 'play-block' );

        return $post_templates;
    }

    public function author_template( $template ) {
        if ( is_author() || ( get_page_template_slug() === 'author.php' ) ) {
            $new_template = Play_Utils::instance()->locate_template( 'author.php' );
            if ( ! empty( $new_template ) ) {
                return $new_template;
            }
        }

        return $template;
    }

    public function user_links( $user ) {
        $facebook  = get_user_meta( $user->ID, 'facebook', true );
        $twitter   = get_user_meta( $user->ID, 'twitter', true );
        $youtube   = get_user_meta( $user->ID, 'youtube', true );
        $instagram = get_user_meta( $user->ID, 'instagram', true );
        $whatsapp  = get_user_meta( $user->ID, 'whatsapp', true );
        $snapchat  = get_user_meta( $user->ID, 'snapchat', true );
        $tiktok    = get_user_meta( $user->ID, 'tiktok', true );
        $url       = $user->user_url;
        $el        = '';
        if ( ! empty( $url ) ) {
            $link_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon feather feather-globe"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>';
            $link_icon = apply_filters( 'play_user_link_icon', $link_icon );
            $el        .= sprintf( '<li class="website-link"><a href="%s" rel="me" title="%s" target="_blank">%s</a></li>', esc_url( $url ), esc_url( $url ), $link_icon );
        }
        if ( ! empty( $facebook ) ) {
            $facebook_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon feather feather-facebook"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>';
            $facebook_icon = apply_filters( 'play_user_facebook_icon', $facebook_icon );
            $el            .= sprintf( '<li class="social-facebook"><a href="%s" rel="me" title="%s" target="_blank">%s</a></li>', esc_url( $facebook ), esc_url( $facebook ), $facebook_icon );
        }
        if ( ! empty( $twitter ) ) {
            $twitter_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-twitter-x" viewBox="0 0 16 16"><path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.601.75Zm-.86 13.028h1.36L4.323 2.145H2.865l8.875 11.633Z"/></svg>';
            $twitter_icon = apply_filters( 'play_user_twitter_icon', $twitter_icon );
            if (filter_var($twitter, FILTER_VALIDATE_URL) == false) {
                $twitter = 'https://twitter.com/'.$twitter;
            }
            $el           .= sprintf( '<li class="social-twitter"><a href="%s" rel="me" title="%s" target="_blank">%s</a></li>', esc_url( $twitter ), esc_url( $twitter ), $twitter_icon );
        }
        if ( ! empty( $tiktok ) ) {
            $tiktok_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="svg-icon" viewBox="0 0 16 16"><path d="M9 0h1.98c.144.715.54 1.617 1.235 2.512C12.895 3.389 13.797 4 15 4v2c-1.753 0-3.07-.814-4-1.829V11a5 5 0 1 1-5-5v2a3 3 0 1 0 3 3z"/></svg>';
            $tiktok_icon = apply_filters( 'play_user_snapchat_icon', $tiktok_icon );
            $el            .= sprintf( '<li class="social-tiktok"><a href="%s" rel="me" title="%s" target="_blank">%s</a></li>', esc_url( $tiktok ), esc_url( $tiktok ), $tiktok_icon );
        }
        if ( ! empty( $youtube ) ) {
            $youtube_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon feather feather-youtube"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon></svg>';
            $youtube_icon = apply_filters( 'play_user_youtube_icon', $youtube_icon );
            $el           .= sprintf( '<li class="social-youtube"><a href="%s" rel="me" title="%s" target="_blank">%s</a></li>', esc_url( $youtube ), esc_url( $youtube ), $youtube_icon );
        }
        if ( ! empty( $instagram ) ) {
            $instagram_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="svg-icon feather feather-instagram"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>';
            $instagram_icon = apply_filters( 'play_user_instagram_icon', $instagram_icon );
            $el             .= sprintf( '<li class="social-instagram"><a href="%s" rel="me" title="%s" target="_blank">%s</a></li>', esc_url( $instagram ), esc_url( $instagram ), $instagram_icon );
        }
        if ( ! empty( $whatsapp ) ) {
            $whatsapp_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" class="svg-icon" viewBox="0 0 448 512"><path fill="currentColor" d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"></path></svg>';
            $whatsapp_icon = apply_filters( 'play_user_whatsapp_icon', $whatsapp_icon );
            $el            .= sprintf( '<li class="social-whatsapp"><a href="%s" rel="me" title="%s" target="_blank">%s</a></li>', esc_url( $whatsapp ), esc_url( $whatsapp ), $whatsapp_icon );
        }
        if ( ! empty( $snapchat ) ) {
            $snapchat_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="svg-icon" viewBox="0 0 16 16"><path d="M15.943 11.526c-.111-.303-.323-.465-.564-.599a1.416 1.416 0 0 0-.123-.064l-.219-.111c-.752-.399-1.339-.902-1.746-1.498a3.387 3.387 0 0 1-.3-.531c-.034-.1-.032-.156-.008-.207a.338.338 0 0 1 .097-.1c.129-.086.262-.173.352-.231.162-.104.289-.187.371-.245.309-.216.525-.446.66-.702a1.397 1.397 0 0 0 .069-1.16c-.205-.538-.713-.872-1.329-.872a1.829 1.829 0 0 0-.487.065c.006-.368-.002-.757-.035-1.139-.116-1.344-.587-2.048-1.077-2.61a4.294 4.294 0 0 0-1.095-.881C9.764.216 8.92 0 7.999 0c-.92 0-1.76.216-2.505.641-.412.232-.782.53-1.097.883-.49.562-.96 1.267-1.077 2.61-.033.382-.04.772-.036 1.138a1.83 1.83 0 0 0-.487-.065c-.615 0-1.124.335-1.328.873a1.398 1.398 0 0 0 .067 1.161c.136.256.352.486.66.701.082.058.21.14.371.246l.339.221a.38.38 0 0 1 .109.11c.026.053.027.11-.012.217a3.363 3.363 0 0 1-.295.52c-.398.583-.968 1.077-1.696 1.472-.385.204-.786.34-.955.8-.128.348-.044.743.28 1.075.119.125.257.23.409.31a4.43 4.43 0 0 0 1 .4.66.66 0 0 1 .202.09c.118.104.102.26.259.488.079.118.18.22.296.3.33.229.701.243 1.095.258.355.014.758.03 1.217.18.19.064.389.186.618.328.55.338 1.305.802 2.566.802 1.262 0 2.02-.466 2.576-.806.227-.14.424-.26.609-.321.46-.152.863-.168 1.218-.181.393-.015.764-.03 1.095-.258a1.14 1.14 0 0 0 .336-.368c.114-.192.11-.327.217-.42a.625.625 0 0 1 .19-.087 4.446 4.446 0 0 0 1.014-.404c.16-.087.306-.2.429-.336l.004-.005c.304-.325.38-.709.256-1.047Zm-1.121.602c-.684.378-1.139.337-1.493.565-.3.193-.122.61-.34.76-.269.186-1.061-.012-2.085.326-.845.279-1.384 1.082-2.903 1.082-1.519 0-2.045-.801-2.904-1.084-1.022-.338-1.816-.14-2.084-.325-.218-.15-.041-.568-.341-.761-.354-.228-.809-.187-1.492-.563-.436-.24-.189-.39-.044-.46 2.478-1.199 2.873-3.05 2.89-3.188.022-.166.045-.297-.138-.466-.177-.164-.962-.65-1.18-.802-.36-.252-.52-.503-.402-.812.082-.214.281-.295.49-.295a.93.93 0 0 1 .197.022c.396.086.78.285 1.002.338.027.007.054.01.082.011.118 0 .16-.06.152-.195-.026-.433-.087-1.277-.019-2.066.094-1.084.444-1.622.859-2.097.2-.229 1.137-1.22 2.93-1.22 1.792 0 2.732.987 2.931 1.215.416.475.766 1.013.859 2.098.068.788.009 1.632-.019 2.065-.01.142.034.195.152.195a.35.35 0 0 0 .082-.01c.222-.054.607-.253 1.002-.338a.912.912 0 0 1 .197-.023c.21 0 .409.082.49.295.117.309-.04.56-.401.812-.218.152-1.003.638-1.18.802-.184.169-.16.3-.139.466.018.14.413 1.991 2.89 3.189.147.073.394.222-.041.464Z"/></svg>';
            $snapchat_icon = apply_filters( 'play_user_snapchat_icon', $snapchat_icon );
            $el            .= sprintf( '<li class="social-snapchat"><a href="%s" rel="me" title="%s" target="_blank">%s</a></li>', esc_url( $snapchat ), esc_url( $snapchat ), $snapchat_icon );
        }
        if ( ! empty( $el ) ) {
            echo sprintf( '<ul class="user-links">%s</ul>', apply_filters( 'user_social', $el ) );
        }
    }

    /**
     * admin bar
     */
    public function admin_bar() {
        $show = false;
        if ( play_get_option( 'show_admin_bar' ) && current_user_can( 'administrator' ) ) {
            $show = true;
        }

        return apply_filters( 'play_admin_bar', $show );
    }

    /**
     *
     */
    public function user_uri( $uri ) {
        global $comment;

        if ( empty ( $comment )
             or ! is_object( $comment )
             or empty ( $comment->comment_author_email )
             or ! $user = get_user_by( 'email', $comment->comment_author_email )
        ) {
            return $uri;
        }

        return get_author_posts_url( $user->ID );
    }

    public function nav_count( $user_id, $endpoint ) {
        $count = '';
        switch ( $endpoint ) {
            case 'stations':
            case 'product':
            case 'downloads':
            case 'playlists':
            case 'albums':
            case 'shorts':
                $type = 'single';
                if ( 'playlists' === $endpoint ) {
                    $type = 'playlist';
                }
                if ( 'albums' === $endpoint ) {
                    $type = 'album';
                }
                if ( 'shorts' === $endpoint ) {
                    $type = 'short';
                }
                $post_type = play_get_option( 'play_types' );
                if ( in_array( $endpoint, array( 'station', 'product', 'download' ) ) ) {
                    $post_type = $endpoint;
                }
                $args = array(
                    'post_type'      => $post_type,
                    'author'         => $user_id,
                    'post_status'    => 'publish',
                    'meta_key'       => 'type',
                    'meta_value'     => $type,
                    'posts_per_page' => - 1
                );

                $query = new WP_Query( $args );
                $count = $query->found_posts;
                break;
            case 'followers':
                $count = count( apply_filters( 'user_follow', $user_id ) );
                break;
            case 'following':
                $count = count( apply_filters( 'user_following', $user_id ) );
                break;
            case 'likes':
                $count = count( apply_filters( 'user_likes', $user_id ) );
                break;
            case 'download':
                $count = count( apply_filters( 'user_download', $user_id ) );
                break;
            default:
                # code...
                break;
        }

        $count = apply_filters( 'play_nav_count', $count, $user_id, $endpoint, $this );

        if ( $count !== '' ) {
            $count = '<span>' . Play_Utils::instance()->format_count( $count ) . '</span>';
        }

        return $count;
    }

    public function pass_user_endpoints( $endpoint, $user_id, $item = NULL ) {
        $pass = false;

        // pass logout
        if ( in_array($endpoint, array('logout','delete-my-account')) ) {
            $pass = true;
        }

        // other user can not see
        if ( get_current_user_id() !== $user_id && in_array( $endpoint, array( 'profile', 'upload', 'download', 'notifications' ) ) ) {
            $pass = true;
        }

        // user can use the upload and station
        $role = play_get_option( 'upload_role' );
        $roles = is_array($role) ? array_filter( $role ) : array('administrator','editor','author','contributor');
        
        $user = get_userdata($user_id);
        $can  = false;
        
        if($user && count( array_intersect($roles, $user->roles) ) > 0 ){
            $can = true;
        }

        if ( !$can && in_array( $endpoint, apply_filters('play_pass_user_endpoints', array('stations','albums','upload', 'product') ) ) ) {
            $pass = true;
        }

        if ( get_current_user_id() !== $user_id && $item && $item->object == 'page' ) {
            $pass = true;
            if( in_array('menu-public', $item->classes) ){
                $pass = false;
            }
        }

        return apply_filters( 'pass_user_endpoint', $pass, $endpoint, $user_id, $item );
    }

    public function nav_menu_items( $items, $args ) {
        global $wp_rewrite;
        foreach ( $items as $key => $item ) {
            if ( strpos( $item->url, '%site_url%' ) !== false ) {
                $item->url = str_replace( array( 'http://', 'https://' ), '', $item->url );
                $item->url = str_replace( '%site_url%', site_url(), $item->url );
            }

            if ( strpos( $item->url, '%user_base%' ) !== false ) {
                $item->url = str_replace( '%user_base%', $wp_rewrite->author_base, $item->url );
            }

            if ( strpos( $item->title, '%avatar%' ) !== false ) {
                if ( is_user_logged_in() ) {
                    $user            = wp_get_current_user();
                    $item->classes[] = 'menu-avatar';
                    $item->title     = '<span class="user-display-name">' . $user->display_name . '</span>' . get_avatar( $user->ID );
                }else{
                    $item->title = '';
                }
            }

            $icon = apply_filters('icon_cart_svg', '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-shopping-cart"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>');

            if ( class_exists( 'Easy_Digital_Downloads' ) ) {
              if ( strpos( $item->url, '%edd-cart%' ) !== false ) {
                $item->url        = edd_get_checkout_uri();
                $item->classes[]  = 'menu-cart';
                $item->title      = sprintf( '%s <span class="cart-quantity edd-cart-quantity">%s</span>', $icon, esc_attr( edd_get_cart_quantity() ) );
              }
            }

            if( class_exists( 'WooCommerce' ) ){
              if ( strpos( $item->url, '%woo-cart%' ) !== false ) {
                $item->url        = wc_get_cart_url();
                $item->classes[]  = 'menu-cart';
                $item->title      = sprintf( '%s <span class="cart-quantity woo-cart-quantity">%s</span>', $icon, esc_attr( wc()->cart->get_cart_contents_count() ) );
              }
            }

            if ( in_array( $args->theme_location, array( 'user', 'after_login' ) ) ) {
                $user_id = get_queried_object_id();
                // reset to current user_id
                if ( false === is_author() || 'after_login' === $args->theme_location ) {
                    if ( is_user_logged_in() ) {
                        $user_id = get_current_user_id();
                    }
                }
                $user     = get_userdata( $user_id );
                $endpoint = basename( $item->url );
                $pass     = $this->pass_user_endpoints( $endpoint, $user_id, $item );

                if ( $pass ) {
                    if ( 'logout' === $endpoint && 'after_login' === $args->theme_location ) {
                        $item->classes[] = 'no-ajax';
                        $item->url       = str_replace( '%user%', $user->user_nicename, $item->url );
                        continue;
                    }
                    unset( $items[ $key ] );
                    continue;
                }

                global $wp;
                if ( $user && 'user' === $args->theme_location ) {
                    $active          = isset( $wp->query_vars[ $endpoint ] ) ? 'active' : '';
                    $count           = apply_filters( 'nav_count', $user_id, $endpoint );
                    $item->title     .= $count;
                    $item->classes[] = 'sub-ajax ' . $active;
                    $item->url       = str_replace( '%user%', $user->user_nicename, $item->url );
                }
            }

            if ( strpos( $item->url, '%user%' ) !== false ) {
                if ( is_user_logged_in() ) {
                    $user      = wp_get_current_user();
                    $item->url = str_replace( '%user%', $user->user_nicename, $item->url );

                    if( (is_ssl() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] == $item->url ){
                        $item->classes[] = 'current-menu-item';
                    }
                } else {
                    $item->url = get_the_permalink( play_get_option( 'page_login' ) );
                }
            }
        }

        return $items;
    }

    public function add_menu_meta_boxes() {
        add_meta_box( 'user_endpoints_nav_link', __( 'User endpoints' ), array(
            $this,
            'menu_links'
        ), 'nav-menus', 'side', 'low' );
    }

    /**
     * Output menu links.
     */
    public function menu_links() {
        // Get items from account menu.
        $endpoints = $this->get_user_endpoints();

        $endpoints = apply_filters( 'user_menu_items', $endpoints );

        global $wp_rewrite;
        
        ?>
        <div id="posttype-user-endpoints" class="posttypediv">
            <div id="tabs-panel-user-endpoints" class="tabs-panel tabs-panel-active">
                <ul id="user-endpoints-checklist" class="categorychecklist form-no-clear">
                    <?php
                    $i = - 1;
                    foreach ( $endpoints as $key => $var ) :
                        if($key == 'delete-my-account'){
                            continue;
                        }
                        $endpoint = isset($var['id']) ? $var['id'] : $key;
                        $name = isset($var['name']) ? $var['name'] : $var;
                        $url = $this->get_endpoint_url( $endpoint, '', get_author_posts_url( 0 ) . '%user%' );
                        $url = str_replace('/'.$wp_rewrite->author_base.'/', '/%user_base%/', $url);
                        $url = str_replace( site_url(), '%site_url%', $url );

                        ?>
                        <li>
                            <label class="menu-item-title">
                                <input type="checkbox" class="menu-item-checkbox"
                                       name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-object-id]"
                                       value="<?php echo esc_attr( $i ); ?>"/> <?php echo esc_html( $name ); ?>
                            </label>
                            <input type="hidden" class="menu-item-type"
                                   name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-type]" value="custom"/>
                            <input type="hidden" class="menu-item-title"
                                   name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-title]"
                                   value="<?php echo esc_html( $name ); ?>"/>
                            <input type="hidden" class="menu-item-url"
                                   name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-url]"
                                   value="<?php echo esc_url( $url ); ?>"/>
                            <input type="hidden" class="menu-item-classes"
                                   name="menu-item[<?php echo esc_attr( $i ); ?>][menu-item-classes]"/>
                        </li>
                        <?php
                        $i --;
                    endforeach;
                    ?>
                </ul>
            </div>
            <p class="button-controls">
                <span class="list-controls">
                    <a href="<?php echo esc_url( admin_url( 'nav-menus.php?page-tab=all&selectall=1#posttype-user-endpoints' ) ); ?>"
                       class="select-all"><?php esc_html_e( 'Select all' ); ?></a>
                </span>
                <span class="add-to-menu">
                    <button type="submit" class="button-secondary submit-add-to-menu right"
                            value="<?php esc_attr_e( 'Add to menu' ); ?>" name="add-post-type-menu-item"
                            id="submit-posttype-user-endpoints"><?php esc_html_e( 'Add to menu' ); ?></button>
                    <span class="spinner"></span>
                </span>
            </p>
        </div>
        <?php
    }

}

Play_User::instance();
