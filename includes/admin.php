<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'octanist-cookie-handler',
        plugin_dir_url(__FILE__) . '../assets/js/handler.js',
        [],
        '1.0',
        true
    );

    $field_mappings = get_option('octanist_field_mappings', []);
    wp_localize_script('octanist-cookie-handler', 'octanistSettings', [
        'octanistID' => get_option('octanist_id', ''),
        'fieldMappings' => $field_mappings,
    ]);
});

add_action('admin_menu', 'ofh_add_admin_menu');
function ofh_add_admin_menu()
{
    $icon_url = OFH_URL . 'assets/image.png';

    add_menu_page(
        'Octanist',
        'Octanist',
        'manage_options',
        'octanist-settings',
        'ofh_render_settings_page',
        $icon_url,
        2
    );
}

function ofh_render_settings_page()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('save_octanist_settings');

        update_option('octanist_id', sanitize_text_field($_POST['octanist_id']));

        $field_mappings = [
            'name' => sanitize_text_field($_POST['field_mapping_name']),
            'email' => sanitize_text_field($_POST['field_mapping_email']),
            'phone' => sanitize_text_field($_POST['field_mapping_phone']),
            'custom' => sanitize_text_field($_POST['field_mapping_custom']),
        ];
        update_option('octanist_field_mappings', $field_mappings);

        echo '<div class="updated"><p>Octanist settings saved!</p></div>';
    }

    $octanist_id = get_option('octanist_id', '');
    $field_mappings = get_option('octanist_field_mappings', [
        'name' => '',
        'email' => '',
        'phone' => '',
        'custom' => '',
    ]);

    ?>
    <div class="wrap">
        <h1>Octanist Settings</h1>
        <form method="POST">
            <?php wp_nonce_field('save_octanist_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="octanist_id">Octanist ID:</label></th>
                    <td>
                        <input type="text" id="octanist_id" name="octanist_id"
                            value="<?php echo esc_attr($octanist_id ?? ''); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="field_mapping_name">Name field:</label></th>
                    <td>
                        <input type="text" id="field_mapping_name" name="field_mapping_name"
                            value="<?php echo esc_attr($field_mappings['name'] ?? ''); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="field_mapping_email">Email field:</label></th>
                    <td>
                        <input type="text" id="field_mapping_email" name="field_mapping_email"
                            value="<?php echo esc_attr($field_mappings['email'] ?? ''); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="field_mapping_phone">Phone field:</label></th>
                    <td>
                        <input type="text" id="field_mapping_phone" name="field_mapping_phone"
                            value="<?php echo esc_attr($field_mappings['phone'] ?? ''); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="field_mapping_custom">Custom field:</label></th>
                    <td>
                        <input type="text" id="field_mapping_custom" name="field_mapping_custom"
                            value="<?php echo esc_attr($field_mappings['custom'] ?? ''); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Save</button>
            </p>
        </form>
    </div>
    <?php
}
