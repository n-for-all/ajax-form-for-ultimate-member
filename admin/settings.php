<?php
class Ajaxy_UM_Forms_Meta_Box
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        if (is_admin()) {
            add_action('load-post.php', array( $this, 'init_metabox' ));
            add_action('load-post-new.php', array( $this, 'init_metabox' ));
        }
    }

    public function init_metabox()
    {
        add_action('add_meta_boxes', array( $this, 'add_metabox'  ));
        add_action('save_post', array( $this, 'save_metabox' ), 10, 2);
    }

    /**
     * Adds the meta box.
     */
    public function add_metabox()
    {
        add_meta_box(
            'ajaxy-um-forms-metabox',
            __('Ajax Settings', AJAXY_UM_FORMS_TEXT_DOMAIN),
            array( $this, 'render_metabox' ),
            'um_form',
            'side',
            'default'
        );
    }

    /**
     * Handles saving the meta box.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @return null
     */
    public function save_metabox($post_id, $post)
    {
        /*
        * We need to verify this came from the our screen and with proper authorization,
        * because save_post can be triggered at other times.
        */

        // Check if our nonce is set.
        if (! isset($_POST['um_ajax_inner_custom_box_nonce'])) {
            return $post_id;
        }

        $nonce = $_POST['um_ajax_inner_custom_box_nonce'];

        // Verify that the nonce is valid.
        if (! wp_verify_nonce($nonce, 'um_ajax_inner_custom_box')) {
            return $post_id;
        }

        /*
        * If this is an autosave, our form has not been submitted,
        * so we don't want to do anything.
        */
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        // Check the user's permissions.
        if ('um_form' == $_POST['post_type']) {
            global $Ajaxy_UM_Forms_License;
            /* OK, it's safe for us to save the data now. */
            $id = "_ajaxy_umajax_enable";

            $forms = get_option("_ajaxy_umajax_forms");
            if(!$forms){
                $forms = array();
            }
            if($_POST[$id] == 1){
                $forms = array_diff($forms, array($post_id));
                $forms[] = $post_id;
                if(sizeof($forms) <= 1 || $Ajaxy_UM_Forms_License->is_licensed()){
                    // Sanitize the user input.
                    $data = $_POST[$id];

                    // Update the meta field.
                    update_post_meta($post_id, $id, $data);
                }else{
                    $forms = array_diff($forms, array($post_id));
                }
            }else{
                update_post_meta($post_id, $id, 0);
                $forms = array_diff($forms, array($post_id));
            }
            update_option("_ajaxy_umajax_forms", $forms);
        }
    }
    /**
     * Render Meta Box content.
     *
     * @param WP_Post $post The post object.
     */
    public function render_metabox($post)
    {
        global $Ajaxy_UM_Forms_License;
        // Add an nonce field so we can check for it later.
        wp_nonce_field('um_ajax_inner_custom_box', 'um_ajax_inner_custom_box_nonce');

        // Use get_post_meta to retrieve an existing value from the database.
        $id = "_ajaxy_umajax_enable";
        $active = get_post_meta($post->ID, $id, true);

        // Display the form, using the current value.
        ?>
        <div class="um-admin-metabox">
            <?php
            $forms = get_option("_ajaxy_umajax_forms");
            if(!$forms){
                $forms = array();
            }
            if(sizeof($forms) < 1 || $Ajaxy_UM_Forms_License->is_licensed() || in_array($post->ID, $forms)){
            ?>
            <p>
                <label for="<?php echo $id; ?>">
                    <?php _e('Enable/Disable Ajax for this form', AJAXY_UM_FORMS_TEXT_DOMAIN);
                ?>
                </label>
                <span>
                    <span class="um-admin-yesno">
                        <span class="btn pos-<?php echo $active; ?>"></span>
                        <span class="yes" data-value="1">Yes</span>
                        <span class="no" data-value="0">No</span>
                        <input type="hidden" name="<?php echo $id; ?>" id="<?php echo $id; ?>" value="<?php echo $active; ?>"/>
                    </span>
                </span>
            </p>
            <p>
            <?php
        }else{ ?>
            <i>Forms are limited to only one for the lite version of Ajax forms for ultimate Member, you can get a license from <a href="http://www.ajaxy.org/shop" target="_blank">Ajaxy.org</a></i>
        </p>
        <?php } ?>
        </div>
        <?php

    }
}

new Ajaxy_UM_Forms_Meta_Box();
?>
