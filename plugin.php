<?php
/*
Plugin Name: Ajax Forms for Ultimate Member
Plugin URI: http://ajaxy.org
Description: Tranform your static ultimate member forms to ajax
Version: 1.0.0
Author: Naji Amer - @n-for-all
Author URI: http://ajaxy.org
*/


if (is_admin()) {
    require_once 'admin/license.php';
    require_once 'admin/settings.php';
}

/* Not tested with older versions of Ultimate Member, lower the version number at your own risk */
define("AJAXY_UM_FORMS_REQUIRED_VERSION", "1.3.65");


define("AJAXY_UM_FORMS_TEXT_DOMAIN", "um-ajax");
define("AJAXY_UM_FORMS_PLUGIN_URL", plugins_url('', __FILE__));

class Ajaxy_UM_Forms_Screen
{
    public function in_admin()
    {
        return false;
    }
}
class Ajaxy_UM_Forms
{
    private $license = null;
    private $vars = array();
    public function __construct(){
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));
    }
    public function plugins_loaded()
    {
        if (version_compare(ultimatemember_version, AJAXY_UM_FORMS_REQUIRED_VERSION) >= 0) {
            $this->filters();
            $this->actions();
        } else {
            add_action('admin_notices', array(&$this, 'version_notice__error'));
        }
    }

    public function filters()
    {
    }
    public function head()
    {
        $forms = get_option("_ajaxy_umajax_forms");
        if(!$forms){
            $forms = array();
        }
        $classes = array();
        foreach($forms as $form){
            $classes[] = ".um-".trim($form);
        }
        echo '<script type="text/javascript">
         var um_ajax = { endpoint: "'.admin_url('admin-ajax.php').'", forms: "'.implode(",", $classes).'"};
        </script>
        ';
    }
    public function actions()
    {
        add_action('wp_ajax_um_submit_form', array(&$this,'submit_form'));
        add_action('wp_ajax_nopriv_um_submit_form', array(&$this,'submit_form'));
        add_action('wp_head', array(&$this,'head'));

        add_action('admin_enqueue_scripts', array(&$this, 'admin_scripts'));

        add_action('wp_enqueue_scripts', array(&$this, 'scripts'));
        add_action('um_after_form', array(&$this, 'um_after_form'));

        if (isset($_POST['ajaxy-umajax-mode'])) {
            add_action('init', array(&$this, 'setup_screen'), 1);
            add_action('um_registration_after_auto_login', array(&$this, 'registration_after_auto_login'), 1);
            add_action('um_on_login_before_redirect', array(&$this, 'um_on_login_before_redirect'), 1);
        }
    }

    public function version_notice__error()
    {
        $class = 'notice notice-error';
        $message = __('Ajax forms for Ultimate Member requires Ultimate Member version '.AJAXY_UM_FORMS_REQUIRED_VERSION.' or higher.', AJAXY_UM_FORMS_TEXT_DOMAIN);

        printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
    }
    public function admin_scripts()
    {
        wp_enqueue_style(AJAXY_UM_FORMS_TEXT_DOMAIN."-style", AJAXY_UM_FORMS_PLUGIN_URL. '/admin/css/styles.css');
    }
    public function scripts()
    {
        $in_footer = true;
        wp_enqueue_script(AJAXY_UM_FORMS_TEXT_DOMAIN, AJAXY_UM_FORMS_PLUGIN_URL. '/js/front.js', array( 'jquery' ), "1.0.0", $in_footer);
        wp_enqueue_style(AJAXY_UM_FORMS_TEXT_DOMAIN."-style", AJAXY_UM_FORMS_PLUGIN_URL. '/css/styles.css');
    }
    public function setup_screen()
    {
        if (isset($_POST['ajaxy-umajax-mode'])) {
            if (!$GLOBALS['current_screen']) {
                $GLOBALS['current_screen'] = new Ajaxy_UM_Forms_Screen();
            }
        }
    }
    public function um_on_login_before_redirect()
    {
        global $ultimatemember;
        $after = um_user('after_login');

        switch ($after) {
            case 'redirect_admin':
                $this->vars['after_login'] = admin_url();
                break;
            case 'redirect_profile':
                $this->vars['after_login'] = um_user_profile_url();
                break;
            case 'redirect_url':
                $this->vars['after_login'] = um_user('login_redirect_url');
                break;
            case 'refresh':
                $this->vars['after_login'] = $ultimatemember->permalinks->get_current_url();
                break;
        }
        $ultimatemember->user->profile['after_login'] = "";
    }
    public function registration_after_auto_login()
    {
        global $ultimatemember;
        $this->vars['auto_approve_act']= $ultimatemember->user->profile['auto_approve_act'];
        if (um_user('auto_approve_act') == 'redirect_url' && um_user('auto_approve_url') !== '') {
            $this->vars['auto_approve_act'] = um_user('auto_approve_url') ;
        }
        if (um_user('auto_approve_act') == 'redirect_profile') {
            $this->vars['auto_approve_act'] = um_user_profile_url() ;
        }
        $ultimatemember->user->profile['auto_approve_act'] = "";
    }
    public function submit_form()
    {
        global $ultimatemember;
        $continue = true;
        $output = array();
        $code = isset($_POST['code-'.$ultimatemember->form->form_id]) ? $_POST['code-'.$ultimatemember->form->form_id] :  false;
        if($code){
            $code = get_page_by_title($code, OBJECT, 'code');
            if ($code == null) {
                $output = array("status" => 'failure', "message" => 'Invalid Code');
            }else{
                if($code->post_author == 1){ //code is not claimed yet
                    // Update post 37
                    if(get_current_user_id() > 0){
                      $my_post = array(
                          'ID'           => $code->ID,
                          'post_type'   => 'code',
                          'post_author' => get_current_user_id(),
                      );

                    // Update the post into the database
                      wp_update_post( $my_post );
                  }
                    //$output = array("status" => 'success', "message" => 'Code Added');
                }else{
                    $continue = false;
                    $output = array("status" => 'failure', "message" => 'Code is already claimed');
                }
            }
        }
        if($continue):
        switch ($_POST['ajaxy-umajax-mode']) {
            case "register":
                $error = '';
                if ($ultimatemember->form->count_errors() > 0) {
                    $error = array_values($ultimatemember->form->errors);
                    $error = array_shift($error);
                }
                $errors = trim(apply_filters('register_errors', $error));
                if (trim($errors)) {
                    $ajax_nonce = wp_create_nonce("um_register_form");
                    $output = array("status" => "error", "message" => $errors, "nonce" => $ajax_nonce);
                } else {
                    $output = array("status" => "success", "message" => apply_filters("um_custom_success_message_handler", '', ''), "redirect" => isset($this->vars['auto_approve_act']) ?  $this->vars['auto_approve_act'] : um_user('auto_approve_act'));
                }

                break;
            case "login":
                $error = '';
                if ($ultimatemember->form->count_errors() > 0) {
                    $error = array_values($ultimatemember->form->errors);
                    $error = array_shift($error);
                }
                $errors = trim(apply_filters('login_errors', $error));
                if (trim($errors)) {
                    $output = array("status" => "error", "message" => $errors);
                } else {
                    $output = array("status" => "success", "message" => apply_filters("um_login_success_message_handler", '', ''), "redirect" => isset($this->vars['after_login']) ?  $this->vars['after_login'] : um_user('after_login'));
                }

                break;
            default:
                if ($_POST['form_id'] == 'um_password_id' && $_POST['_um_password_reset'] == 1) {
                    $ultimatemember->form->post_form = $_POST;
                    do_action('um_reset_password_errors_hook', $ultimatemember->form->post_form);

                    if (!isset($ultimatemember->form->errors)) {
                        do_action('um_reset_password_process_hook', $ultimatemember->form->post_form);
                    }
                    $error = '';
                    if ($ultimatemember->form->count_errors() > 0) {
                        $error = array_values($ultimatemember->form->errors);
                        $error = array_shift($error);
                    }
                    $errors = trim(apply_filters('password_errors', $error));
                    if (trim($errors)) {
                        $output = array("status" => "error", "message" => $errors);
                    } else {
                        $output = array("status" => "success", "message" => apply_filters("um_password_reset_success_message_handler", 'Your password has been reset successfully!<small>An email with instructions has been sent to your registered email address</small>', ''), "redirect" => isset($this->vars['auto_approve_act']) ?  $this->vars['auto_approve_act'] : um_user('auto_approve_act'));
                    }
                } elseif (is_user_logged_in() && $_POST['_um_account'] == 1) {
                    $ultimatemember->form->post_form = $_POST;
                    do_action('um_submit_account_errors_hook', $ultimatemember->form->post_form);
                    $error = '';
                    if ($ultimatemember->form->count_errors() > 0) {
                        $error = array_values($ultimatemember->form->errors);
                        $error = array_shift($error);
                    }
                    $errors = trim(apply_filters('password_errors', $error));
                    if (trim($errors)) {
                        $output = array("status" => "error", "message" => $errors);
                    } else {
                        // if there is no errors, just submit, we have no choice Ultimate Member is using redirects
                        $output = array("status" => "submit");
                    }
                } else {
                    $output = array("status" => "submit");
                }
                break;
        }
        endif;
        $GLOBALS['current_screen'] = null;
        echo json_encode($output);
        die();
    }
    public function um_after_form($args)
    {
        if ($args['mode']) {
            ?>
        <input type="hidden" name="ajaxy-umajax-mode" value="<?php echo $args['mode'];
            ?>" />
        <?php

        }
    }
}

$Ajaxy_UM_Forms = new Ajaxy_UM_Forms();
