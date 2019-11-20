<?php
defined('ABSPATH') or die("Invalid request");

function liblynx_plugin_menu()
{
    add_options_page(
        'LibLynx Connect Options', //page title
        'LibLynx Connect', //menu text
        'manage_options', //capability
        'liblynx-connect-admin', //menu slug
        'liblynx_plugin_options' //callback
    );
}

function liblynx_plugin_options()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Read in existing option value from database
    $api_key = get_option('liblynx_api_key');
    $api_secret = get_option('liblynx_api_secret');
    $liblynx_protect= get_option('liblynx_protect', 'nothing');
    $unit_code=get_option('liblynx_unit_code', '');


    if (isset($_POST['liblynx_api_key'])) {
        $api_key=$_POST['liblynx_api_key'];
        $api_secret=$_POST['liblynx_api_secret'];
        $liblynx_protect=$_POST['liblynx_protect'];
        $unit_code=$_POST['liblynx_unit_code'];

        update_option('liblynx_api_key', $api_key);
        update_option('liblynx_api_secret', $api_secret);
        update_option('liblynx_protect', $liblynx_protect);
        update_option('liblynx_unit_code', $unit_code);

        ?>
        <div class="updated"><p><strong><?php _e('settings saved.', 'menu-test'); ?></strong></p></div>
        <?php
    }

    // Now display the settings editing screen
    echo '<div class="wrap">';

    // header
    echo "<h2>" . __('LibLynx Connect Settings', 'menu-liblynx') . "</h2>";

    // settings form
    ?>

<form name="liblynx-form" method="post" action="">

<h3 class="title">LibLynx Connect API Key</h3>
<p>To use LibLynx Connect, you require an API key and secret.
If you're already a LibLynx customer, you can find this in the
<a href="http://connect.liblynx.com/publisher/config/apikeys">LibLynx Publisher Portal</a>.
Otherwise, <a href="mailto:info@liblynx.com">get in touch</a>
with LibLynx to discuss your requirements.</p>

<table class="form-table">
    <tbody><tr>
        <th><label for="liblynx_api_key">LibLynx API Key</label></th>
        <td><input name="liblynx_api_key" id="liblynx_api_key" type="text" value="<?php echo htmlentities($api_key); ?>" class="regular-text code">
        </td>
    </tr>
    <tr>
        <th><label for="liblynx_api_secret">LibLynx API Secret</label></th>
        <td> <input name="liblynx_api_secret" id="liblynx_api_secret" type="text" value="<?php echo htmlentities($api_secret); ?>" class="regular-text code"></td>
    </tr>
    </tbody>
</table>

<h3 class="title">Securing Content</h3>

<p>This plugin will ensure only authorized visitors are able to view content. You can choose
    which content to protect in the following ways:</p>

<table class="form-table">
    <tr>
        <th scope="row">What to protect?</th>
        <td>
            <fieldset>
                <legend class="screen-reader-text"><span>What to protect?</span></legend>
                <label title="All pages and posts"><input type="radio" name="liblynx_protect" value="all" <?php echo ($liblynx_protect=='all')?'checked="checked"':''?>> All pages and posts</label><br>
                <!--
                <label title="Only specific URL patterns - configure these below"><input type="radio" name="liblynx_protect" value="patterns"<?php echo ($liblynx_protect=='patterns')?'checked="checked"':''?>> Only specific URL patterns</label><br>
                -->
                <label title="Only flagged pages and posts - this plugin adds a checkbox in the post editor"><input type="radio" name="liblynx_protect" value="tagged"<?php echo ($liblynx_protect=='tagged')?'checked="checked"':''?>> Only flagged pages and posts</label><br>
                <label title="Nothing - useful to turn off protection without losing other settings"><input type="radio" name="liblynx_protect" value="nothing"<?php echo ($liblynx_protect=='nothing')?'checked="checked"':''?>> Nothing</label><br>
            </fieldset>
        </td>
    </tr>
    <tr>
        <th><label for="liblynx_unit_code">Default Content Unit</label></th>
        <td><input name="liblynx_unit_code" id="liblynx_unit_code" type="text" value="<?php echo htmlentities($unit_code); ?>" class="regular-text code">
            <p class="description">You must enter a unit code which is used to represent your protected content in LibLynx. This unit
                code is what you'll use to create customer subscriptions for this content. You can configure
                units in the <a href="http://connect.liblynx.com/publisher/config/unit/">LibLynx Connect Publisher Portal here</a>
                <br><br>If you choose 'flagged pages and posts' above, you can override this on a per-post basis

            </p>
        </td>
    </tr>
</table>

<hr />

<p class="submit">
  <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
</p>

</form>
</div>

<?php

}

add_action('admin_menu', 'liblynx_plugin_menu');
