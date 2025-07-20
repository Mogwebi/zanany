<?php
/**
 * Plugins
 */

class ffl_plugins{
	private $url = '';
	private $msg = '';
	private $msg_type;
	private $setting;
	protected static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_action( 'init', [$this, 'init'] );
        add_action( 'admin_head', [$this, 'head'] );

        require_once get_template_directory() . '/includes/libs/TGMPA/class-tgm-plugin-activation.php';
        add_action( 'tgmpa_register', [$this, 'register_required_plugins'] );
    }

    public function register_required_plugins(){
	    $plugins = array(
	    	array(
	    		'name'      => 'WordPress Importer',
	            'slug'      => 'wordpress-importer',
	            'required'  => true
	    	),
	        array(
	            'name'      => 'Play Block',
	            'slug'      => 'play-block',
	            'source'    => $this->url.'&plugin=play-block',
	            'version'   => $this->setting['Plugin'],
	            'required'  => true
	        ),
	        array(
	            'name'      => 'Loop Block',
	            'slug'      => 'loop-block',
	            'source'    => $this->url.'&plugin=loop-block',
	            'version'   => $this->setting['Plugin'],
	            'required'  => true
	        )
	    );
	    $plugins = apply_filters('ffl_required_plugins', $plugins);
	    
	    $config = array(
			'id'           => 'ffl',
			'default_path' => '',
			'menu'         => 'tgmpa-install-plugins',
			'has_notices'  => true,
			'dismissable'  => true,
			'dismiss_msg'  => '',
			'is_automatic' => false,
			'message'      => '',
		);

		tgmpa( $plugins, $config );
    }

    public function head(){
    	echo '<style>.appearance_page_tgmpa-install-plugins .code.pre{display:none}</style>';
    }

    public function init() {
    	if(!is_admin()){ return; }

    	$code = get_option('envato_purchase_code');
		$this->setting = get_file_data( get_template_directory().'/style.css', array('Plugin' => 'Plugin', 'Support' => 'Support') );
		$this->url = $this->setting['Support'].'/wp-json/plugin/update/?code='.$code.'&version='.$this->setting['Plugin'].'&site='.site_url();

		// import demo
		if(current_user_can('manage_options') && isset( $_REQUEST['import'] ) && isset( $_REQUEST['demo'] )){
			// verify the code first.
			$data = $this->verify_code();
			if($data['verify'] && $data['supported']){
				$this->import_demo();
			}
		}

		// update plugin
		if(isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'tgmpa-install-plugins' && isset($_REQUEST['plugin']) ){
			$plugins = $_REQUEST['plugin'];
			if(!is_array($plugins)) $plugins = array($plugins);
			if(in_array('loop-block', $plugins) || in_array('play-block', $plugins)){
				$this->verify_code();
			}
		}
    }

    public function import_demo(){
		// install plugins first
	    if (class_exists('WP_Import') && class_exists('Play_Block')) {
	    	require_once ABSPATH . 'wp-admin/includes/file.php';
		    require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$file = download_url($this->url.'&plugin=demo');
			if(is_wp_error($file)){
				$this->add_message('Can not import demo data.', 'error');
			}else{
				if(is_file($file) && (filesize( $file ) > 0)){
					if (!function_exists('wp_insert_category')) {
		                require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
		            }
		            if (!function_exists('post_exists')) {
		                require_once ABSPATH . 'wp-admin/includes/post.php';
		            }
		            if (!function_exists('comment_exists')) {
		                require_once ABSPATH . 'wp-admin/includes/comment.php';
		            }

		            // make sure we have got a xml file
		            WP_Filesystem();
		            global $wp_filesystem;
					if ( (strpos ( $wp_filesystem->get_contents( $file ), 'xml' ) !== false) ) {
						$wp_import = new WP_Import();
						if(apply_filters('ffl_import_demo_attachment', true)){
							$wp_import->fetch_attachments = true;
							$wp_import->allow_fetch_attachments();
						}
						// remove current menus
						$menus = wp_get_nav_menus();
						if (!empty($menus)) {
							foreach ($menus as $menu) {
								wp_delete_nav_menu($menu);
							}
						}

						ob_start();
							$wp_import->import($file);
						ob_end_clean();

						$this->setup_demo();

						$this->add_message('Demo data imported.', 'success');
					}else{
						$this->add_message('Import failed, Not a XML file.', 'error');
					}
				}else{
					$this->add_message('Import failed, Empty demo data.', 'error');
				}
			}
		}else{
			$this->add_message('Install & Activate the required plugins first.', 'error');
		}
    }

    public function setup_demo(){
	    // Set menu
		$locations = get_theme_mod('nav_menu_locations');
		$menus = wp_get_nav_menus();
		if (!empty($menus)) {
			foreach ($menus as $menu) {
				if (is_object($menu) && $menu->name == 'Before login') {
					$locations['before_login'] = $menu->term_id;
				}
				if (is_object($menu) && $menu->name == 'After login') {
					$locations['after_login'] = $menu->term_id;
				}
				if (is_object($menu) && in_array($menu->name, ['Primary','Browse'])) {
					$locations['primary'] = $menu->term_id;
				}
				if (is_object($menu) && $menu->name == 'Secondary') {
					$locations['secondary'] = $menu->term_id;
				}
				if (is_object($menu) && $menu->name == 'Mobile') {
					$locations['mobile'] = $menu->term_id;
				}
			}
		}
		set_theme_mod('nav_menu_locations', $locations);

		// set anyone can register
		update_option('users_can_register', true);

		// set home
		$page = get_posts( array('post_type' => 'page', 'post_status' => 'all', 'numberposts' => 1,
		'title' => 'discover'));
	    if (!empty( $page )) {
	         update_option('show_on_front', 'page');
	         update_option('page_on_front', $page[0]->ID);
	    }

	    // set page area
	    $page = get_posts( array('post_type' => 'page', 'post_status' => 'all', 'numberposts' => 1,
		'title' => 'footer'));
	    if (!empty( $page )) {
	         update_option('page_footer', $page[0]->ID);
	    }

	    $page = get_posts( array('post_type' => 'page', 'post_status' => 'all', 'numberposts' => 1,
		'title' => 'sidebar'));
	    if (!empty( $page )) {
	         update_option('page_sidebar', $page[0]->ID);
	    }

	    $page = get_posts( array('post_type' => 'page', 'post_status' => 'all', 'numberposts' => 1,
		'title' => 'side header'));
	    if (!empty( $page )) {
	    	 update_option('page_sideheader', $page[0]->ID);
	    }

	    $page = get_posts( array('post_type' => 'page', 'post_status' => 'all', 'numberposts' => 1,
		'title' => 'side footer'));
	    if (!empty( $page )) {
	    	 update_option('page_sidefooter', $page[0]->ID);
	    }
	}

    public function verify_code(){
    	$response = wp_remote_get($this->url.'&plugin=Verify');
    	$data = array(
    		'verify' => false,
    		'supported' => false,
    	);
    	if (!is_wp_error($response) ) {
            $response_body = wp_remote_retrieve_body($response);
            $res = json_decode($response_body);
            $data = wp_parse_args($res, $data);
            $this->add_message($data['message'], !$data['verify'] || !$data['supported'] ? 'error' : 'info');
        } else {
        	$this->add_message('Server error, Try later.', 'error');
        }
        return $data;
    }

    public function add_message($msg, $type = 'info'){
    	new ffl_message($msg, $type);
    }
}

class ffl_message {
    private $_message;
    private $_type;
    function __construct( $message, $type = 'info' ) {
        $this->_message = $message;
        $this->_type = $type;
        add_action( 'admin_notices', array( $this, 'render' ) );
    }
    function render() {
        printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $this->_type, $this->_message );
    }
}

ffl_plugins::instance();