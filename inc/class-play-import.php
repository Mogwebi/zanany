<?php

defined( 'ABSPATH' ) || exit;

class Play_Import {

    protected static $_instance = null;
    private $max_import = 10000;
    private $error = '';
    private $count = 0;
    private $update = 0;
    private $api_youtube = 'https://www.googleapis.com/youtube/v3/';
    private $setting_page;

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
        $this->setting_page = apply_filters('play_import_page_url', 'edit.php?post_type=station');
        add_action( 'admin_menu', array( $this, 'add_options_link' ) );

        add_action( 'play_import_tab_file', array( $this, 'play_import_file_page' ) );
        add_action( 'play_import_tab_youtube', array( $this, 'play_import_youtube_page' ) );

        do_action( 'play_block_import_init', $this );
    }

    public function add_options_link() {
        add_submenu_page( $this->setting_page, esc_html__( 'Import', 'play-block' ), esc_html__( 'Import', 'play-block' ), 'manage_options', 'play-import', [$this, 'admin_page']);
    }

    public function admin_page(){
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'file';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import' ); ?></h1>
            <nav class="nav-tab-wrapper">
                <?php
                $tabs = [
                    'file'  => [
                      'title' => __( "File" ),
                    ],
                    'youtube'  => [
                      'title' => __( "Youtube import" )
                    ]
                ];

                $tabs = apply_filters('play_import_tabs', $tabs);

                foreach ( $tabs as $tab_id => $tab ) {
                    $admin_url = admin_url( 'edit.php' );
                    $tab_url = add_query_arg(
                        array(
                            'post_type' => 'station',
                            'page' => 'play-import',
                            'tab'  => $tab_id,
                        ),
                        $admin_url
                    );
                    $active = ( $active_tab === $tab_id )
                        ? ' nav-tab-active'
                        : '';

                    echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $tab['title'] ) . '</a>';
                }
                ?>
            </nav>
            <?php

            do_action( 'play_import_tab_top_' . $active_tab );

            do_action( 'play_import_tab_' . $active_tab );

            do_action( 'play_import_tab_bottom_' . $active_tab  );

            ?>
        </div>
        <?php
    }

    public function play_import_youtube_page(){
        ?>
        <form enctype="multipart/form-data" method="post">
        <table class="form-table">
            <tbody>
                <?php
                $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'station';
                $url = '';
                if(isset($_REQUEST['url'])){
                    $url = $_REQUEST['url'];
                    $this->importYoutube($url);
                }
                ?>
                <tr>
                    <th><?php esc_html_e('Youtube API Key','play-block'); ?></th>
                    <td>
                        <input type="text" name="youtube_api_key" value="<?php echo esc_attr(isset($_REQUEST['youtube_api_key']) ? $_REQUEST['youtube_api_key'] : play_get_option( 'youtube_api_key' )); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('URL','play-block'); ?></th>
                    <td>
                        <input type="text" name="url" value="<?php echo esc_attr($url); ?>" class="regular-text" placeholder="http://">
                        <p class="description"><?php esc_html_e('Support Youtube video, playlist, channel and handle URL.','play-block'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Import to post type','play-block'); ?></th>
                    <td>
                        <select name="type">
                            <?php
                            foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $id => $type ) {
                                if ( ! empty( $type->labels->name ) ) {
                                    echo sprintf('<option value="%s">%s</option>', esc_attr($id), esc_html($type->labels->name));
                                }
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <input type="submit" value="<?php esc_attr_e('Continue','play-block'); ?>" class="button button-primary">
                        <input type="hidden" name="step" value="1">
                        <p>
                        <?php 
                            if( isset($_REQUEST['step']) ){
                                echo sprintf( __('<p><strong>%s</strong> items imported, <strong>%s</strong> items updated. </p> %s'), esc_html($this->count - $this->update), $this->update, $this->error);
                            }
                        ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        </form>
        <?php
    }

    public function play_import_file_page(){
        $steps = array(
            array(
                'header' => __( 'Upload file', 'play-block' ),
                'title' => __( 'Import data from a file', 'play-block' ),
                'subtitle' => __( 'This tool allows you to import (or merge) data to your site from a file.', 'play-block' ),
            ),
            array(
                'header' => __( 'Column mapping', 'play-block' ),
                'title' => __( 'Map fields', 'play-block' ),
                'subtitle' => __( 'Select fields from your file to map against data fields, or to ignore during import.', 'play-block' ),
            ),
            array(
                'header' => __( 'Import', 'play-block' ),
                'title' => __( 'Importing', 'play-block' ),
                'subtitle' => __( 'Your data are now being imported...', 'play-block' ),
            )
        );
        $active = isset($_REQUEST['step']) ? (int)$_REQUEST['step'] : 0;
        $step  = $active + 1;
        ?>
        <div class="import-wrap">
            <ul class="progress">
                <?php
                    foreach($steps as $key => $val){
                        echo sprintf('<li class="%s">%s</li>', esc_attr($active > $key ? 'done' : ($active == $key ? 'active' : '')), $val['header'] );
                    }
                ?>
            </ul>
            <div class="import-dialog">
                <form enctype="multipart/form-data" method="post">
                    <header class="import-header">
                        <h2><?php echo $steps[$active]['title']; ?></h2>
                        <p class="description"><?php echo $steps[$active]['subtitle']; ?></p>
                    </header>
                    <div class="import-content">
                        <table class="form-table">
                            <tbody>
                                <?php
                                $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'station';

                                if(isset($_REQUEST['file'])){
                                    $url = $_REQUEST['file'];
                                    $response = wp_remote_get($url);
                                    $file_type = wp_remote_retrieve_header($response,'content-type');
                                    $data = wp_remote_retrieve_body($response);
                                }

                                if($active == 0){ ?>
                                <tr>
                                    <th><?php esc_html_e('Choose a file','play-block'); ?></th>
                                    <td>
                                        <input type="text" name="file"><button type="button" class="button upload-btn">Upload</button>
                                        <p class="description"><?php esc_html_e('Maximum size: ','play-block'); echo size_format(wp_max_upload_size()); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Import to post type','play-block'); ?></th>
                                    <td>
                                        <select name="type">
                                            <?php
                                            foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $id => $post_type ) {
                                                if ( ! empty( $post_type->labels->name ) ) {
                                                    echo sprintf('<option value="%s">%s</option>', esc_attr($id), esc_html($post_type->labels->name));
                                                }
                                            }
                                            ?>
                                            <option value="user">Users</option>
                                        </select>
                                    </td>
                                </tr>
                                <?php }
                                // mapping
                                if($active == 1){
                                    $content  = '<input type="hidden" name="file" value="'.esc_attr($url).'">';
                                    $content .= '<input type="hidden" name="type" value="'.esc_attr($type).'">';
                                    // Map start from field
                                    if( in_array($file_type, array('application/json', 'application/xml', 'text/xml')) && !isset($_REQUEST['mapping']) ){

                                        $obj = $this->mapData($data, $file_type);
                                        $fields = $this->flatObject($obj);
                                        $fields = array_keys($fields);
                                        $options = '';
                                        foreach($fields as $field){
                                            $options .= sprintf('<option value="%s">%s</option>', esc_attr($field), esc_html($field));
                                        }
                                        $step = 1;
                                        $content .= sprintf('<tr><th>%s</th><td><select name="map_start">%s</select></td></tr>', __('Map data from','play-block'), $options);
                                        $content .= '<input type="hidden" name="mapping" value="1">';
                                    }else{
                                        $columns = array();
                                        // json xml
                                        if(in_array($file_type, array('application/json', 'application/xml', 'text/xml'))){
                                            $map_start = $_REQUEST['map_start'];
                                            $obj = $this->mapData($data, $file_type, $map_start);
                                            if(is_array($obj)){
                                                $obj = $obj[0];
                                            }
                                            $columns = array_keys($this->flatObject($obj));
                                            $content .= '<input type="hidden" name="map_start" value="'.esc_attr($map_start).'">';
                                        }

                                        // csv text
                                        if($file_type == 'text/csv'){
                                            // parse csv
                                            $url = Play_Utils::instance()->getPath($url);
                                            $file = fopen($url, 'r');
                                            if ( false !== $file ) {
                                                $columns = fgetcsv($file);
                                                fclose($file);
                                            }
                                        }

                                        $content .= sprintf('<tr><th>Required field<br><em><small>Ignore import for an empty field.</small></em></th><td> <select name="map_required">%s</select></td></tr>', $this->getMapFields($type));

                                        if(!empty($columns)){
                                            foreach($columns as $key => $column){
                                                $input = sprintf('<input type="hidden" name="map_from[%s]" value="%s">', $key, ($file_type == 'text/csv' ? $key : $column));
                                                $content .= sprintf('<tr><th>%s</th><td>%s <select name="map_to[%s]">%s</select></td></tr>', $column, $input, $key, $this->getMapFields($type));
                                            }
                                        }else{

                                        }
                                    }

                                    echo $content;
                                }
                                // start import
                                if($active == 2){
                                    $map_start = isset($_REQUEST['map_start']) ? $_REQUEST['map_start'] : '';
                                    $map_from = isset($_REQUEST['map_from']) ? $_REQUEST['map_from'] : [];
                                    $map_to = isset($_REQUEST['map_to']) ? $_REQUEST['map_to'] : [];
                                    $map_required = isset($_REQUEST['map_required']) ? $_REQUEST['map_required'] : '';
                                    $parse_data = array('post_type'=>$type, 'post_status'=>'publish');
                                    $mapping = array_filter(array_combine($map_from, $map_to));
                                    // json xml
                                    if(in_array($file_type, array('application/json', 'application/xml', 'text/xml'))){
                                        $obj = $this->mapData($data, $file_type, $map_start);
                                        foreach($obj as $item){
                                            $item = $this->flatObject($item);
                                            foreach ($item as $key => $value) {
                                                if(isset($mapping[$key]) && !empty($mapping[$key])){
                                                    $parse_data[$mapping[$key]] = $value;
                                                }
                                            }
                                            if(!empty($map_required) && empty($parse_data[$map_required])){
                                                continue;
                                            }
                                            $this->import($parse_data);
                                        }
                                    }
                                    // csv text
                                    if($file_type == 'text/csv'){
                                        $url = Play_Utils::instance()->getPath($url);
                                        $file = fopen($url, 'r');
                                        if ( false !== $file ) {
                                            // remove the first row;
                                            $header = fgetcsv($file);
                                            $row = fgetcsv($file);
                                            while ( false !== $row ) {
                                                foreach ($row as $key => $value) {
                                                    if(isset($mapping[$key]) && !empty($mapping[$key])){
                                                        $parse_data[$mapping[$key]] = $value;
                                                    }
                                                }
                                                $row = fgetcsv($file);
                                                if(!empty($map_required) && empty($parse_data[$map_required])){
                                                    continue;
                                                }
                                                $this->import($parse_data);
                                            }
                                            fclose($file);
                                        }
                                    }

                                    $content = sprintf( __('<p><strong>%s</strong> items imported, <strong>%s</strong> items updated. </p>'), esc_html($this->count - $this->update), $this->update);
                                    echo $content;
                                } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="import-footer">
                        <input type="hidden" name="step" value="<?php esc_attr_e($step); ?>">
                        <?php if( $step < count($steps)){ ?>
                        <input type="submit" value="<?php esc_attr_e('Continue','play-block'); ?>" class="button button-primary">
                        <?php }else{ ?>
                        <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type='.$type)); ?>"><?php esc_attr_e('View','play-block'); ?></a>
                        <?php } ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    public function importYoutube($url){
        @ini_set('max_execution_time', '300');
        $items = $this->youtube($url);
        
        foreach($items as $item){
            $post = $this->youtubeItemMap($item);
            do_action( 'play_before_import_youtube', $post );
            $this->import($post);
            do_action( 'play_after_import_youtube', $post );
        }
        return $this->count;
    }

    public function youtubeItemMap($item){
        $video_url = 'https://www.youtube.com/watch?v=' . $item['id'];
        $duration = 0;
        if(isset($item[ 'contentDetails' ][ 'duration' ])){
            $s = new DateInterval( $item[ 'contentDetails' ][ 'duration' ] );
            $duration = ($s->h * 3600 + $s->i * 60 + $s->s)*1000;
        }
        $mapped = array(
            'post_type'     => isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : 'station',
            'post_title'    => $item[ 'snippet' ][ 'title' ],
            'post_content'  => $item[ 'snippet' ][ 'description' ],
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( $item[ 'snippet' ][ 'publishedAt' ] ) ),
            'post_status'   => 'publish',
            'meta_input'    => array(
                'stream'    => $video_url,
                'duration'  => $duration
            )
        );
        
        if(isset($item[ 'snippet' ][ 'tags' ])){
            $mapped['tax_input'] = array(
                apply_filters('play_import_youtube_cat', 'station_tag') => $item[ 'snippet' ][ 'tags' ]
            );
        }

        if( isset( $item[ 'snippet'][ 'thumbnails' ][ 'maxres' ] ) )
          $img_url = $item[ 'snippet'][ 'thumbnails' ][ 'maxres' ][ 'url' ];
        else if( isset( $item[ 'snippet'][ 'thumbnails' ][ 'high' ] ) )
          $img_url = $item[ 'snippet'][ 'thumbnails' ][ 'high' ][ 'url' ];
        else if( isset( $item[ 'snippet'][ 'thumbnails' ][ 'medium' ] ) )
          $img_url = $item[ 'snippet'][ 'thumbnails' ][ 'medium' ][ 'url' ];
        else if( isset( $item[ 'snippet'][ 'thumbnails' ][ 'default' ] ) )
          $img_url = $item[ 'snippet'][ 'thumbnails' ][ 'default' ][ 'url' ];

        $mapped['thumbnail'] = $img_url;

        return $mapped;
    }

    public function youtube($url){
        $key = isset($_REQUEST['youtube_api_key']) ? $_REQUEST['youtube_api_key'] : play_get_option( 'youtube_api_key' );
        if($key == ''){
            return;
        }
        $id = Play_Utils::instance()->parsePlayURL($url);
        $arg = [
            'key'   => $key,
            'part'  => urlencode( 'snippet, id' )
        ];
        $items = [];
        $video_ids = [];

        // handle URL
        if(strpos($url, '@')){
            $handle_url = substr($url, strrpos($url, '@') + 1);
            $_url = add_query_arg( array_merge($arg, array('q'=>$handle_url, 'type'=>'channel')), $this->api_youtube.'search' );
            $items = $this->youtubeItems( $_url, 1, 1 );
            if(count($items) > 0){
                $url = $url.'#channel';
                $id = $items[0]['id']['channelId'];
            }
        }
        // channel
        if(strpos($url, 'channel')){
            $_url = add_query_arg( array_merge($arg, array('channelId' => $id)), $this->api_youtube.'search' );
            $items = $this->youtubeItems( $_url );
        // playlist
        }elseif(strpos($url, 'playlist')){
            $_url = add_query_arg( array_merge($arg, array('playlistId' => $id)), $this->api_youtube.'playlistItems' );
            $items = $this->youtubeItems( $_url );
        }else{
        // watch url
            $video_ids[]= $id;
        }
        
        foreach( $items as $item ) {
          // playlist
          if( isset( $item[ 'snippet' ][ 'resourceId' ][ 'videoId' ] ) ) {
            $video_ids[] = $item[ 'snippet' ][ 'resourceId' ][ 'videoId' ];
          }
          // channel
          if( isset( $item[ 'id' ][ 'videoId' ] ) ) {
            $video_ids[] = $item[ 'id' ][ 'videoId' ];
          }
        }
        return $this->youtubeVideos($arg, $video_ids);
    }

    public function youtubeItems($base_url, $limit = 10, $max = 50){
        // max results 500
        $base_url .= '&maxResults='.$max;
        $nextPageToken = null;
        $items = [];

        $this->error = sprintf('<strong>URL</strong>: %s', $base_url);

        while( $nextPageToken !== false && $limit > 0 ) {
          $limit--;

          $url = $base_url;

          if( $nextPageToken !== null )
            $url .= '&pageToken=' . $nextPageToken;

          $response = wp_remote_get( $url );

          if( is_wp_error( $response ) ) {
            $this->error .= sprintf('<br><strong>Error</strong>: %s', $this->get_error($response));
            $nextPageToken = false;
            continue;
          }

          $response = wp_remote_retrieve_body( $response );
          $response = json_decode( $response, true );

          if( !isset( $response[ 'items' ] ) )  {
            $nextPageToken = false;
            continue;
          }

          $items = array_merge( $items, $response[ 'items' ] );

          if( isset( $response[ 'nextPageToken' ] ) )
            $nextPageToken = $response[ 'nextPageToken' ];
          else
            $nextPageToken = false;
        }

        return $items;
    }

    public function youtubeVideos($arg, $ids){
        $items = [];

        while( !empty( $ids ) ) {
          $arg['id'] = implode( ",", array_splice( $ids, 0, 50 ) );
          $url = add_query_arg( $arg, $this->api_youtube.'videos' );
          $response = wp_remote_get( $url );

          if( is_wp_error( $response ) ){
            $this->error = sprintf('<strong>Error</strong>: %s [%s]', $this->get_error($response), $url);
            continue;
          }

          $response = wp_remote_retrieve_body( $response );
          $response = json_decode( $response, true );

          if( !isset( $response[ 'items' ] ) )
            continue;

          $items = array_merge( $items, $response[ 'items' ] );
        }

        return $items;
    }

    public function import($data){
        @ini_set('max_execution_time', '300');
        $data_id = 0;
        $has_thumbnail = false;
        $type = $data['post_type'];
        $this->count++;
        if($this->count > $this->max_import ){
            return;
        }

        if($type !== 'user'){
            $taxonomies = get_object_taxonomies($data['post_type'], 'names');
            foreach($taxonomies as $taxonomy){
                if(!empty($data[$taxonomy])){
                    $id = str_replace('&', ',', $data[$taxonomy]);
                    if(is_taxonomy_hierarchical($taxonomy)){
                        $id = wp_create_term($data[$taxonomy], $taxonomy);
                    }
                    $data['tax_input'][$taxonomy] = $id;
                    unset($data[$taxonomy]);
                }
            }

            $data['meta_input']['type'] = apply_filters('play_import_default_type', 'single');
        }

        $meta_keys = $this->getMetaKeys($type);
        foreach($meta_keys as $meta_key){
            if(!empty($data[$meta_key])){
                $data['meta_input'][$meta_key] = $data[$meta_key];
                unset($data[$meta_key]);
            }
        }

        // start update
        global $wpdb;
        if(!empty($data['meta_input']['stream'])){
            $data_id = intval($wpdb->get_var('SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE ( meta_key = "stream" AND meta_value = ' . $wpdb->prepare( "%s", $data['meta_input']['stream']) . ')' ) );
        }
        if(!empty($data['user_login'])){
            $user = get_user_by('login', $data['user_login']);
            if($user){
                $data_id = $user->ID;
                $userdata = $user->to_array();
                $data = wp_parse_args($data, $userdata);
            }
        }
        if($data_id > 0){
            $data['ID'] = $data_id;
            $this->update++;
        }

        if($type == 'user'){
            $data_id = wp_insert_user( apply_filters( 'play_import_user', $data ) );
            $has_thumbnail = get_user_meta($data_id, '_avatar', true);
        }else{
            $data_id = wp_insert_post( apply_filters( 'play_import_post', $data ) );
            $has_thumbnail = get_post_thumbnail_id($data_id);
        }

        // add thumbnail
        if(!empty($data['thumbnail']) && !$has_thumbnail){
            $url = $data['thumbnail'];
            $id = $this->upload_url( $url );
            if($id > 0){
                if($type == 'user'){
                    // get the url
                    $path = wp_get_attachment_url($id);
                    $uploads = wp_get_upload_dir();
                    $path = str_replace($uploads['baseurl'].'/', '', $path);
                    update_user_meta( $data_id, '_avatar', array( 'full' => $path ) );
                }else{
                    set_post_thumbnail( $data_id, $id );
                }
            }
        }
    }

    public function upload_url($url){
        // use wp_handle_sideload to upload url
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $id = 0;
        $tmp  = download_url( $url );
        $name = basename( $url );
        // use .jpg for default if no ext
        $type = wp_check_filetype( $name, null );
        if ( !$type['ext'] ) {
            $name .= '.jpg';
        }
        $file_array = array(
            'name' => $name,
            'tmp_name' => $tmp
        );

        if ( !is_wp_error( $tmp ) ) {
            $id = media_handle_sideload( $file_array );
            if ( is_wp_error( $id ) ) {
                @unlink( $file_array['tmp_name'] );
                $id = 0;
            }
        }
        return $id;
    }

    public function get_error( $error ){
        $errors = '';
        if ( $error->has_errors() ) {
            foreach ( $error->get_error_codes() as $code ) {
                foreach ( $error->get_error_messages( $code ) as $error_message ) {
                    $errors .= $error_message . "<br />";
                }
            }
        }
        return $errors;
    }

    public function getPostMetaKeys(){
        $meta_keys = array_keys( get_registered_meta_keys('post', '') );
        return apply_filters( 'play_import_custom_meta_keys', $meta_keys);
    }

    public function getUserMetaKeys(){
        $meta_keys = array_keys( get_registered_meta_keys('user', '') );
        return apply_filters( 'play_import_custom_user_meta_keys', $meta_keys);
    }

    public function getPostFields(){
        $fields = array(
            'ID', 
            'post_title', 
            'post_excerpt',
            'post_content',
            'post_author',
            'post_date',
            'post_status',
            'comment_status',
            'comment_count'
        );
        return $fields;
    }

    public function getUserFields(){
        $fields = array(
            'ID',
            'user_login',
            'user_nicename',
            'user_email',
            'user_pass', 
            'user_url',
            'nickname',
            'display_name',
            'first_name', 
            'last_name', 
            'description',
            'user_registered',
            'role',
            'locale'
        );
        return $fields;
    }

    public function getFields($type){
        if($type === 'user'){
            return $this->getUserFields();
        }else{
            return $this->getPostFields();
        }
    }

    public function getMetaKeys($type){
        if($type === 'user'){
            return $this->getUserMetaKeys();
        }else{
            return $this->getPostMetaKeys();
        }
    }

    public function getMapFields($type){
        $fields = $this->getFields($type);
        $meta_keys  = $this->getMetaKeys($type);

        $taxonomies = get_object_taxonomies($type, 'names');
        if(!empty($taxonomies)){
            array_unshift($taxonomies , 'Taxonomies');
        }

        if(!empty($meta_keys)){
            array_unshift($meta_keys , 'Meta_keys');
        }

        $fields = apply_filters('play_import_fields', array_merge(array(''), $fields, array('Media','thumbnail'), $taxonomies, $meta_keys));

        $options = '';
        foreach($fields as $field){
            $options .= sprintf('<option %s>%s</option>', in_array(strtolower($field), array('media','taxonomies','meta_keys')) ? 'disabled' : 'value="'.esc_attr($field).'"', esc_html($field));
        }
        return $options;
    }

    public function DOMtoArray($node) {
        $output = array();
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
              $output = trim($node->textContent);
            break;
            case XML_ELEMENT_NODE:
              for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) {
                $child = $node->childNodes->item($i);
                $v = $this->DOMtoArray($child);
                if(isset($child->tagName)) {
                  $t = $child->tagName;
                  if(!isset($output[$t])) {
                    $output[$t] = array();
                  }
                  $output[$t][] = $v;
                }
                elseif($v || $v === '0') {
                  $output = (string) $v;
                }
              }
              if($node->attributes->length && !is_array($output)) { //Has attributes but isn't an array
                $output = array('@content'=>$output); //Change output into an array.
              }
              if(is_array($output)) {
                if($node->attributes->length) {
                  $a = array();
                  foreach($node->attributes as $attrName => $attrNode) {
                    $a[$attrName] = (string) $attrNode->value;
                  }
                  $output['@attributes'] = $a;
                }
                foreach ($output as $t => $v) {
                  if(is_array($v) && count($v)==1 && $t!='@attributes') {
                    $output[$t] = $v[0];
                  }
                }
              }
            break;
        }
        return $output;
    }

    public function mapData($data, $type, $map_start = ''){
        $obj = [];
        if($type == 'application/json'){
            $obj = json_decode($data);
        }
        if(in_array($type, array('application/xml', 'text/xml'))){
            $dom = new DOMDocument();
            $dom->loadXml($data);
            $root = $dom->documentElement;
            $obj = $this->DOMtoArray($root);
        }
        if(!empty($map_start)){
            $keys = explode('.', $map_start);
            foreach($keys as $key){
                if(is_object($obj)){
                    $obj = $obj->$key;
                }elseif(is_array($obj)){
                    $obj = $obj[$key];
                }
            }
        }
        return $obj;
    }

    public function flatObject($array, $prefix = '') {
        $flat = array();
        $sep = ".";
        if (is_array($array) && sizeof($array) > 0 && isset($array[0])) {
            $flat[$prefix] = '';
            $array = array($array[0]);
        }
        if (!is_array($array)) $array = (array)$array;
        foreach($array as $key => $value)
        {
            $_key = ltrim($prefix.$sep.$key, ".");
            if (is_array($value) || is_object($value))
            {
                $flat = array_merge($flat, $this->flatObject($value, $_key));
            }
            else
            {
                $flat[$_key] = $value;
            }
        }
        return $flat;
    }
}

Play_Import::instance();
