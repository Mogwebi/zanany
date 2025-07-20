<?php

defined( 'ABSPATH' ) || exit;

class Play_Attribute {

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
        
        add_action( 'admin_menu', array( $this, 'add_attributes_menu' ), 9 );

        do_action( 'play_block_attribute_init', $this );

        function play_attribute_taxonomy_name($attribute_name, $post_type){
            return $attribute_name ? $post_type . '_' . play_sanitize_taxonomy_name( $attribute_name ) : '';
        }

        function play_sanitize_taxonomy_name( $taxonomy ) {
            return apply_filters( 'sanitize_taxonomy_name', urldecode( sanitize_title( urldecode( $taxonomy ? $taxonomy : '' ) ) ), $taxonomy );
        }

    }

    public function add_attributes_menu() {
        $types = play_get_option( 'play_types' );
        if ( ! empty( $types ) ) {
            foreach ( $types as $type ) {
                if ( post_type_exists( $type ) ) {
                    $link = '?post_type='.$type;
                    if($type == 'product') continue;
                    if($type == 'post') $link = '';
                    add_submenu_page( 'edit.php'.$link, __( 'Attributes', 'play-block' ), __( 'Attributes', 'play-block' ), 'manage_options', $type.'_attributes', array( $this, 'play_attributes_page' ) );
                }
            }
        }
    }

    public function play_attributes_page() {
        global $wpdb;
        $result = '';
        $action = '';

        if ( ! empty( $_POST['add_new_attribute'] ) ) {
            $action = 'add';
        } elseif ( ! empty( $_POST['save_attribute'] ) && ! empty( $_GET['edit'] ) ) {
            $action = 'edit';
        } elseif ( ! empty( $_GET['delete'] ) ) {
            $action = 'delete';
        }

        $data = $this->get_attribute_data();

        switch ( $action ) {
            case 'add':
                play_add_attribute($data);
                break;
            case 'edit':
                $id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
                $att = play_get_attribute($id);
                $post_type = $att->post_type;
                if($att->attribute_name !== $data['attribute_name']){
                    $wpdb->update(
                        $wpdb->term_taxonomy,
                        array( 'taxonomy' => play_attribute_taxonomy_name( $data['attribute_name'], $post_type ) ),
                        array( 'taxonomy' => play_attribute_taxonomy_name( $att->attribute_name, $post_type ) )
                    );
                }
                play_update_attribute($id, $data);
                break;
            case 'delete':
                $id = isset( $_GET['delete'] ) ? absint( $_GET['delete'] ) : 0;
                $att = play_get_attribute($id);
                $post_type = $att->post_type;
                $taxonomy = play_attribute_taxonomy_name( $att->attribute_name, $post_type );
                if ( taxonomy_exists( $taxonomy ) ) {
                    $terms = get_terms( $taxonomy, 'orderby=name&hide_empty=0' );
                    foreach ( $terms as $term ) {
                        wp_delete_term( $term->term_id, $taxonomy );
                    }
                }
                play_delete_attribute($id);
                break;
        }

        if ( ! empty( $_GET['edit'] ) ) {
            $this->edit_attribute_form();
        } else {
            $this->add_attribute_form();
        }
    }

    public function get_attribute_data(){
        $attribute = array(
            'attribute_label'   => isset( $_POST['attribute_label'] ) ? sanitize_text_field( stripslashes( $_POST['attribute_label'] ) ) : '',
            'attribute_name'    => isset( $_POST['attribute_name'] ) ? play_sanitize_taxonomy_name( stripslashes( $_POST['attribute_name'] ) ) : '',
            'attribute_hierarchical'  => isset( $_POST['attribute_hierarchical'] ) ? 1 : 0,
            'attribute_public'  => isset( $_POST['attribute_public'] ) ? 1 : 0,
            'post_type'         => isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : '',
        );

        if ( empty( $attribute['attribute_label'] ) ) {
            $attribute['attribute_label'] = ucfirst( $attribute['attribute_name'] );
        }
        if ( empty( $attribute['attribute_name'] ) ) {
            $attribute['attribute_name'] = play_sanitize_taxonomy_name( $attribute['attribute_label'] );
        }

        return $attribute;
    }

    public static function edit_attribute_form() {
        $id = absint( $_GET['edit'] );

        $post_type = $_GET['post_type'];
        $attribute_to_edit = play_get_attribute($id);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Edit attribute', 'play-block' ); ?></h1>

            <?php
            if ( ! $attribute_to_edit ) {
                echo '<div id="errors" class="error"><p>' . esc_html__( 'Error: non-existing attribute ID.', 'play-block' ) . '</p></div>';
            } else {
                $att_label   = $attribute_to_edit->attribute_label;
                $att_name    = $attribute_to_edit->attribute_name;
                $att_hierarchical = $attribute_to_edit->attribute_hierarchical;
                $att_public  = $attribute_to_edit->attribute_public;
                ?>
                <form action="edit.php?post_type=<?php echo esc_attr( $post_type ); ?>&amp;page=<?php echo esc_attr( $post_type ); ?>_attributes&amp;edit=<?php echo absint( $id ); ?>" method="post">
                    <table class="form-table">
                        <tbody>
                            <tr class="form-field">
                                <th scope="row" valign="top">
                                    <label for="attribute_label"><?php esc_html_e( 'Name', 'play-block' ); ?></label>
                                </th>
                                <td>
                                    <input name="attribute_label" id="attribute_label" type="text" value="<?php echo esc_attr( $att_label ); ?>" required="required" />
                                    <p class="description"><?php esc_html_e( 'Name for the attribute (shown on the front-end).', 'play-block' ); ?></p>
                                </td>
                            </tr>
                            <tr class="form-field">
                                <th scope="row" valign="top">
                                    <label for="attribute_name"><?php esc_html_e( 'Slug', 'play-block' ); ?></label>
                                </th>
                                <td>
                                    <input name="attribute_name" id="attribute_name" type="text" value="<?php echo esc_attr( $att_name ); ?>" maxlength="28" />
                                    <p class="description"><?php esc_html_e( 'Unique slug/reference for the attribute; must be no more than 28 characters.', 'play-block' ); ?></p>
                                </td>
                            </tr>
                            <tr class="form-field">
                                <th scope="row" valign="top">
                                    <label for="attribute_hierarchical"><?php esc_html_e( 'Enable hierarchical?', 'play-block' ); ?></label>
                                </th>
                                <td>
                                    <input name="attribute_hierarchical" id="attribute_hierarchical" type="checkbox" value="1" <?php checked( $att_hierarchical, 1 ); ?> />
                                    <label for="attribute_hierarchical"><?php esc_html_e( 'Enable this if you want this attribute to be hierarchical.', 'play-block' ); ?></label>
                                </td>
                            </tr>
                            <tr class="form-field">
                                <th scope="row" valign="top">
                                    <label for="attribute_public"><?php esc_html_e( 'Enable archives?', 'play-block' ); ?></label>
                                </th>
                                <td>
                                    <input name="attribute_public" id="attribute_public" type="checkbox" value="1" <?php checked( $att_public, 1 ); ?> />
                                    <label for="attribute_public"><?php esc_html_e( 'Enable this if you want this attribute to have archives.', 'play-block' ); ?></label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="submit"><button type="submit" name="save_attribute" id="submit" class="button-primary" value="<?php esc_attr_e( 'Update', 'play-block' ); ?>"><?php esc_html_e( 'Update', 'play-block' ); ?></button></p>
                    <input name="post_type" id="post_type" type="hidden" value="<?php echo esc_attr( $post_type ); ?>" />
                </form>
            <?php } ?>
        </div>
        <?php
    }

    public function add_attribute_form() {
        $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'post';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div id="col-container" class="wp-clearfix">
                <div id="col-left">
                    <div class="col-wrap">
                        <div class="form-wrap">
                            <h2><?php esc_html_e( 'Add new attribute', 'play-block' ); ?></h2>
                            <p><?php esc_html_e( 'Attributes let you define extra data.', 'play-block' ); ?></p>
                            <form action="edit.php?post_type=<?php echo esc_attr( $post_type ); ?>&amp;page=<?php echo esc_attr( $post_type ); ?>_attributes" method="post">
                                <?php do_action( 'play-block_before_add_attribute_fields' ); ?>

                                <div class="form-field">
                                    <label for="attribute_label"><?php esc_html_e( 'Name', 'play-block' ); ?></label>
                                    <input name="attribute_label" id="attribute_label" type="text" value="" required="required" />
                                    <p class="description"><?php esc_html_e( 'Name for the attribute (shown on the front-end).', 'play-block' ); ?></p>
                                </div>

                                <div class="form-field">
                                    <label for="attribute_name"><?php esc_html_e( 'Slug', 'play-block' ); ?></label>
                                    <input name="attribute_name" id="attribute_name" type="text" value="" maxlength="28" />
                                    <p class="description"><?php esc_html_e( 'Unique slug/reference for the attribute; must be no more than 28 characters.', 'play-block' ); ?></p>
                                </div>

                                <div class="form-field">
                                    <label for="attribute_hierarchical"><input name="attribute_hierarchical" id="attribute_hierarchical" type="checkbox" value="1" /> <?php esc_html_e( 'Enable Hierarchical?', 'play-block' ); ?></label>

                                    <p class="description"><?php esc_html_e( 'Enable this if you want this attribute to be hierarchical.', 'play-block' ); ?></p>
                                </div>

                                <div class="form-field">
                                    <label for="attribute_public"><input name="attribute_public" id="attribute_public" type="checkbox" value="1" /> <?php esc_html_e( 'Enable Archives?', 'play-block' ); ?></label>

                                    <p class="description"><?php esc_html_e( 'Enable this if you want this attribute to have archives in your site.', 'play-block' ); ?></p>
                                </div>

                                <?php do_action( 'play-block_after_add_attribute_fields' ); ?>

                                <p class="submit"><button type="submit" name="add_new_attribute" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Add attribute', 'play-block' ); ?>"><?php esc_html_e( 'Add attribute', 'play-block' ); ?></button></p>
                                <input name="post_type" id="post_type" type="hidden" value="<?php echo esc_attr( $post_type ); ?>" />
                            </form>
                        </div>
                    </div>
                </div>
                <div id="col-right">
                    <div class="col-wrap">
                        <table class="widefat attributes-table wp-list-table ui-sortable" style="width:100%">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e( 'Name', 'play-block' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Slug', 'play-block' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Terms', 'play-block' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ( $attribute_taxonomies = play_get_attributes( array('post_type' => $post_type) ) ) :
                                    foreach ( $attribute_taxonomies as $tax ) :
                                        ?>
                                        <tr>
                                                <td>
                                                    <strong><a href="edit-tags.php?taxonomy=<?php echo esc_attr( play_attribute_taxonomy_name( $tax->attribute_name, $tax->post_type ) ); ?>&amp;post_type=<?php echo esc_attr( $post_type ); ?>"><?php echo esc_html( $tax->attribute_label ); ?></a></strong>

                                                    <div class="row-actions">
                                                        <span class="edit"><a href="<?php echo esc_url( add_query_arg( 'edit', $tax->id, sprintf( 'edit.php?post_type=%s&amp;page=%s_attributes', $tax->post_type, $tax->post_type ) ) ); ?>"><?php esc_html_e( 'Edit', 'play-block' ); ?></a> | </span>
                                                    
                                                        <span class="delete"><a class="delete" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'delete', $tax->id, sprintf( 'edit.php?post_type=%s&amp;page=%s_attributes', $tax->post_type, $tax->post_type ) ), 'play-delete-attribute_' . $tax->id ) ); ?>"><?php esc_html_e( 'Delete', 'play-block' ); ?></a></span>
                                                    </div>
                                                </td>
                                                <td><?php echo esc_html( $tax->attribute_name ); ?></td>
                                                <td class="attribute-terms">
                                                    <?php
                                                    $taxonomy = play_attribute_taxonomy_name( $tax->attribute_name, $tax->post_type );

                                                    if ( taxonomy_exists( $taxonomy ) ) {
                                                        $terms = get_terms(
                                                            array(
                                                                'taxonomy'   => $taxonomy,
                                                                'fields'     => 'names',
                                                                'hide_empty' => false,
                                                            )
                                                        );
                                                        $terms_string = implode( ', ', $terms );
                                                        echo esc_html( $terms_string );
                                                    } else {
                                                            echo '<span class="na">&ndash;</span>';
                                                    }
                                                    ?>
                                                    <br /><a href="edit-tags.php?taxonomy=<?php echo esc_html( play_attribute_taxonomy_name( $tax->attribute_name, $tax->post_type ) ); ?>&amp;post_type=<?php echo esc_attr( $post_type ); ?>" class="configure-terms"><?php esc_html_e( 'Configure terms', 'play-block' ); ?></a>
                                                </td>
                                            </tr>
                                            <?php
                                        endforeach;
                                    else :
                                        ?>
                                        <tr>
                                            <td colspan="6"><?php esc_html_e( 'No attributes currently exist.', 'play-block' ); ?></td>
                                        </tr>
                                        <?php
                                    endif;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <script type="text/javascript">
            /* <![CDATA[ */

                jQuery( 'a.delete' ).click( function() {
                    if ( window.confirm( '<?php esc_html_e( 'Are you sure you want to delete this attribute?', 'play-block' ); ?>' ) ) {
                        return true;
                    }
                    return false;
                });

            /* ]]> */
            </script>
        </div>
        <?php
    }

}

Play_Attribute::instance();
