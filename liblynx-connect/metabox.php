<?php
defined('ABSPATH') or die("Invalid request");

/**
 * Adds a box to the main column on the Post and Page edit screens.
 */
function liblynx_add_meta_box()
{
    $screens = array('post', 'page');

    foreach ($screens as $screen) {
        add_meta_box(
            'liblynx_metabox',
            __('LibLynx Connect', 'liblynx_metabox'),
            'liblynx_meta_box_callback',
            $screen
        );
    }
}
add_action('add_meta_boxes', 'liblynx_add_meta_box');

/**
 * Liblynx metabox content contains checkbox to indicate content
 * should be protected
 *
 * @param WP_Post $post The object for the current post/page.
 */
function liblynx_meta_box_callback($post)
{
    // Add a nonce field so we can check for it later.
    wp_nonce_field('liblynx_save_meta_box_data', 'liblynx_meta_box_nonce');

    /*
     * Use get_post_meta() to retrieve an existing value
     * from the database and use the value for the form.
     */
    $value = get_post_meta($post->ID, '_liblynx_protect', false);
    $enabled=is_array($value) && $value[0];
    $checked=$enabled?'checked="checked"':'';

    $unitMeta = get_post_meta($post->ID, '_liblynx_unit', '');
    $unit=is_array($unitMeta) ? $unitMeta[0] : '';

    $default_code=get_option('liblynx_unit_code', '');

    echo '<input type="hidden" name="liblynx_metabox" value="1">';
    echo '<input type="checkbox" id="liblynx_protect" name="liblynx_protect" value="1" '.$checked.'/>';
    echo '<label for="liblynx_protect">Protect content (see <a href="options-general.php?page=liblynx-connect-admin">settings</a> for details)</label>';

    echo '<div id="liblynx_protect_options">';
    echo '<label for="liblynx_custom_unit">Visitor must have subscription for </label>';
    echo '<input type="text" size="8" id="liblynx_custom_unit" name="liblynx_custom_unit" placeholder="'.htmlentities($default_code).'" value="'.htmlentities($unit).'">';
    echo '</div>';

    echo <<<SCRIPT
    <script>


    function updateLibLynxMetabox()
    {
        var enabled=jQuery('#liblynx_protect').is(':checked');
        if (enabled) {
            jQuery('#liblynx_protect_options').show();
        } else {
            jQuery('#liblynx_protect_options').hide();
        }
    }
    jQuery('document').ready(function(){
        jQuery('#liblynx_protect').click(function(){
            updateLibLynxMetabox();
        });
        updateLibLynxMetabox();
    });

    </script>
SCRIPT;

}

/**
 * When the post is saved, saves our custom data.
 *
 * @param int $post_id The ID of the post being saved.
 */
function liblynx_save_meta_box_data($post_id)
{

    /*
     * We need to verify this came from our screen and with proper authorization,
     * because the save_post action can be triggered at other times.
     */

    // Check if our nonce is set.
    if (!isset($_POST['liblynx_meta_box_nonce'])) {
        return;
    }



    // Verify that the nonce is valid.
    if (!wp_verify_nonce($_POST['liblynx_meta_box_nonce'], 'liblynx_save_meta_box_data')) {
        return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions.
    if (isset($_POST['post_type']) && 'page'==$_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }
    } else {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    /* OK, it's safe for us to save the data now. */

    //is our metabox is being submitted?
    if (!isset($_POST['liblynx_metabox'])) {
        return;
    }

    //determine new state
    $protect=isset($_POST['liblynx_protect'])?true:false;
    $unit=trim($_POST['liblynx_custom_unit']);

    // Update the meta field in the database.
    update_post_meta($post_id, '_liblynx_protect', $protect);
    update_post_meta($post_id, '_liblynx_unit', $unit);
}
add_action('save_post', 'liblynx_save_meta_box_data');
