<?php
/**
 * Login Form
 *
 * This template can be overridden by copying it to yourtheme/templates/form/login.php.
 *
 * HOWEVER, on occasion we will need to update template files and
 * you will need to copy the new files to your theme to maintain compatibility.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'play_before_login_form' );
?>

<form name="loginform" id="loginform" method="post" action="">
    <h2><?php play_get_text('login-title', true); ?></h2>

    <?php if ( get_option( 'users_can_register' ) ) { ?>
    <p><?php play_get_text('no-account', true); ?> <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="btn-register text-primary no-ajax"><?php play_get_text('sign-up', true); ?></a></p>
    <?php } ?>

    <div class="form-message">
        <?php do_action('template_notices'); ?>
    </div>

    <?php echo apply_filters( 'login_form_top', '', [] ); ?>
    <?php do_action( 'play_login_form_start' ); ?>
    <p class="form-user-log">
        <label><?php play_get_text('username-or-email', true); ?></label>
        <input type="<?php echo esc_attr(apply_filters( 'play_login_username_type', 'text' )); ?>" name="log" class="input" value="" required />
    </p>
    <?php if(apply_filters('play_register_with_pwd', true)){ ?>
    <p class="form-pwd">
        <label>
            <span><?php play_get_text('password', true); ?></span> 
            <?php echo sprintf( ' <a href="%s" tabindex="-1" class="btn-lostpassword no-ajax">%s</a>', esc_url( wp_lostpassword_url() ), play_get_text( 'lost-your-password' ) ); ?>
        </label>
        <input type="password" name="pwd" class="input" value="" required autocomplete="off" />
    </p>
    <p class="form-rememberme">
        <input type="checkbox" name="rememberme" id="rememberme" />
        <label for="rememberme"><?php play_get_text('rememberme', true); ?></label>
    </p>
    <?php } ?>
    <?php echo apply_filters( 'login_form_middle', '', [] ); ?>
    <?php do_action( 'play_login_form' ); ?>

    <?php do_action( 'login_form' ); ?>
    
    <p>
        <button type="submit" name="wp-submit" class="button button-primary">
            <?php play_get_text('login-btn', true); ?>
        </button>
        <input type="hidden" name="form-action" value="login">
    </p>

    <?php echo apply_filters( 'login_form_bottom', '', [] ); ?>
    <?php do_action( 'play_login_form_end' ); ?>
</form>

<?php do_action( 'play_after_login_form' ); ?>
