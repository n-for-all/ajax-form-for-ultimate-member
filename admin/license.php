<?php

define('AJAXY_UM_CLIENT_KEY', '56d4131a60f6c8.69447567');
define('AJAXY_UM_LICENSE_SERVER_URL', 'http://www.ajaxy.org');
define('AJAXY_UM_ITEM_REFERENCE', 'UM-AJAX');

class Ajaxy_UM_Forms_License
{
    public $lic;
    public $server = AJAXY_UM_LICENSE_SERVER_URL;
    public $api_key = AJAXY_UM_CLIENT_KEY;
    private $wp_option  = '_ajaxy_um_forms_license';
    private $product_id = 'UM-AJAX';
    public $err;

    public function __construct()
    {
        add_action('admin_menu', array(&$this, 'license_menu'));
        add_filter('redux/options/um_options/sections', array(&$this, 'um_options'));
        //add_action('redux/page/um_options/sections/after', array(&$this, 'um_section'));
    }
    public function check($lic = false)
    {
        if ($this->is_licensed()) {
            $this->lic = get_option($this->wp_option);
        } else {
            $this->lic = $lic;
        }
    }
    function um_validate_license($field, $value, $existing_value) {
            $error = false;
            $this->check($value);
            if ($this->active()) {
                $error = false;
            }else{
                  $error = true;
                  $field['msg'] = 'Invalid License';
              }

            $return['value'] = $value;
            if ($error == true) {
                $return['error'] = $field;
            }
            return $return;
        }
    public function um_options($sections)
    {
        $sections[] = array(
            'icon'       => 'um-icon-code-working',
            'title'      => __('Ajax', AJAXY_UM_FORMS_TEXT_DOMAIN),
            'fields'     => array(
                array(
                        'id'               => 'um_ajax_enable',
                        'type'             => 'switch',
                        'title'        => __('Ajax Enabled', AJAXY_UM_FORMS_TEXT_DOMAIN),
                        'default'        => 1,
                        'desc'            => 'Enable/disable ajax on the forms',
                        'on'            => __('On', AJAXY_UM_FORMS_TEXT_DOMAIN),
                        'off'            => __('Off', AJAXY_UM_FORMS_TEXT_DOMAIN),
                ),
                array(
                        'id'               => 'um_ajax_account_enable',
                        'type'             => 'switch',
                        'title'        => __('Profile Ajax Validation Enabled', AJAXY_UM_FORMS_TEXT_DOMAIN),
                        'default'        => 0,
                        'desc'            => 'Enable/disable profile validation via ajax',
                        'on'            => __('On', AJAXY_UM_FORMS_TEXT_DOMAIN),
                        'off'            => __('Off', AJAXY_UM_FORMS_TEXT_DOMAIN),
                ),
                array(
                        'id'               => 'um_reset_message',
                        'type'            => 'textarea', // bug with wp 4.4? should be editor
                        'title'            => __('Reset Password Success Message', AJAXY_UM_FORMS_TEXT_DOMAIN),
                        'default'        => __('Your password has been reset successfully!<small>An email with instructions has been sent to your registered email address</small>', AJAXY_UM_FORMS_TEXT_DOMAIN),
                        'desc'            => __('Reset Password form message when submitted successfully.', AJAXY_UM_FORMS_TEXT_DOMAIN)
                ),
                array(
                        'id'               => 'Ajaxy_UM_Forms_License',
                        'type'            => 'text', // bug with wp 4.4? should be editor
                        'title'            => __('License', AJAXY_UM_FORMS_TEXT_DOMAIN),
                        'subtitle'        => $this->is_licensed() ? '<i class="notice-green">Already Activated</i>' : '',
                        'readonly'        => $this->is_licensed(),
                        //'class' =>$this->is_licensed() ? 'notice-green' : '',
                        'default'        => __('', AJAXY_UM_FORMS_TEXT_DOMAIN),
                        'desc'            => __('Unlimited forms, Get a license from <a target="_blank" href="http://www.ajaxy.org">Ajaxy.org</a>.', AJAXY_UM_FORMS_TEXT_DOMAIN),
                        'validate_callback' => array($this, 'um_validate_license')
                )
            )
        );
        return $sections;
    }
    /**
     * check for current product if licensed
     * @return boolean
     */
    public function is_licensed()
    {
        $lic = get_option($this->wp_option);
        if (!empty($lic)) {
            return true;
        }
        return false;
    }


    public function license_menu()
    {
        add_submenu_page(AJAXY_UM_FORMS_TEXT_DOMAIN, 'Ajax', 'Ajax', 'manage_options', 'um-ajax-settings', array($this, 'settings_page'));
    }
    public function settings_page()
    {
        echo '<div class="wrap">';
        echo '<h2>Ultimate Memeber Ajax Settings</h2>';

      /*** License activate button was clicked ***/
      if (isset($_REQUEST['activate_license'])) {
          $license_key = $_REQUEST['license_key'];
          // Send query to the license manager server
          $this->check($license_key);
          if ($this->active()) {
              echo '<b>You license Activated successfuly</b><br/>';
          } else {
              echo $lic->err;
          }
      }
        if ($this->is_licensed()) {
            echo 'Thank You Phurchasing!';
        } else {
            ?>
          <form action="" method="post">
              <table class="form-table">
                  <tr>
                      <th style="width:100px;"><label for="license_key">License Key</label></th>
                      <td ><input class="regular-text" type="text" id="license_key" name="license_key"  value="<?php echo get_option('license_key');
            ?>" ></td>
                  </tr>
              </table>
              <p class="submit">
                  <input type="submit" name="activate_license" value="Activate" class="button-primary" />
              </p>
          </form>
          <?php

        }


        echo '</div>';
    }

    /**
     * send query to server and try to active lisence
     * @return boolean
     */
    public function active()
    {
        $url = AJAXY_UM_LICENSE_SERVER_URL . '/?secret_key=' . AJAXY_UM_CLIENT_KEY . '&slm_action=slm_activate&license_key=' . $this->lic . '&registered_domain=' . get_bloginfo('url').'&item_reference='.$this->product_id;
        $response = wp_remote_get($url, array('timeout' => 20, 'sslverify' => false));
        if (is_array($response)) {
            $json = $response['body']; // use the content
            $json = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', utf8_encode($json));
            $license_data = json_decode($json);
        }
        if ($license_data->result == 'success') {
            update_option($this->wp_option, $this->lic);
            return true;
        } else {
            delete_option($this->wp_option);
            $this->err = $license_data->message;
            return false;
        }
    }

    /**
     * send query to server and try to deactive lisence
     * @return boolean
     */
    public function deactive()
    {
    }
}
global $Ajaxy_UM_Forms_License;
$Ajaxy_UM_Forms_License = new Ajaxy_UM_Forms_License();
