<?php
if (!defined('ABSPATH')) {
    exit;
}

class Octanist_Admin
{
    private $options;

    public function __init()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Octanist',
            'Octanist',
            'manage_options',
            'octanist-settings',
            [$this, 'render_settings_page'],
            OFH_URL . 'assets/image.png',
            2
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        // Only load on our settings page
        if ($hook !== 'toplevel_page_octanist-settings') {
            return;
        }
        wp_enqueue_script(
            'octanist-admin-js',
            OFH_URL . 'assets/js/admin.js',
            ['jquery'],
            '1.1.0',
            true
        );
    }

    public function render_settings_page()
    {
        $this->options = get_option('octanist_settings');
        ?>
        <div class="wrap">
            <h1>Octanist Settings</h1>
            <form method="POST" action="options.php">
                <?php
                settings_fields('octanist_settings_group');
                do_settings_sections('octanist-settings-page');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings()
    {
        register_setting(
            'octanist_settings_group',
            'octanist_settings',
            [$this, 'sanitize_settings']
        );

        // General Settings Section
        add_settings_section(
            'octanist_general_section',
            'General Settings',
            null,
            'octanist-settings-page'
        );

        add_settings_field(
            'octanist_id',
            'Octanist ID',
            [$this, 'render_field_id'],
            'octanist-settings-page',
            'octanist_general_section'
        );

        // Field Mappings Section
        add_settings_section(
            'octanist_mappings_section',
            'Field Mappings',
            [$this, 'render_mappings_section_text'],
            'octanist-settings-page'
        );

        $mapping_fields = ['name', 'email', 'phone', 'custom'];
        foreach ($mapping_fields as $field) {
            add_settings_field(
                'field_mapping_' . $field,
                ucfirst($field) . ' Fields',
                [$this, 'render_mapping_fields'],
                'octanist-settings-page',
                'octanist_mappings_section',
                ['field' => $field]
            );
        }

        // Advanced Settings Section
        add_settings_section(
            'octanist_advanced_section',
            'Advanced Settings',
            null,
            'octanist-settings-page'
        );

        add_settings_field(
            'send_to_octanist',
            'Send data to Octanist',
            [$this, 'render_field_checkbox'],
            'octanist-settings-page',
            'octanist_advanced_section',
            ['name' => 'send_to_octanist', 'default' => '1']
        );

        add_settings_field(
            'send_to_datalayer',
            'Send data to (GTM) Datalayer',
            [$this, 'render_field_checkbox'],
            'octanist-settings-page',
            'octanist_advanced_section',
            ['name' => 'send_to_datalayer', 'default' => '0']
        );

        add_settings_field(
            'submission_mode',
            'Form Submission Mode',
            [$this, 'render_field_submission_mode'],
            'octanist-settings-page',
            'octanist_advanced_section'
        );
        
        add_settings_field(
            'debug_mode',
            'Debug Mode',
            [$this, 'render_field_checkbox'],
            'octanist-settings-page',
            'octanist_advanced_section',
            ['name' => 'debug_mode', 'default' => '0']
        );
    }

    public function render_field_id()
    {
        $value = isset($this->options['octanist_id']) ? esc_attr($this->options['octanist_id']) : '';
        echo '<input type="text" id="octanist_id" name="octanist_settings[octanist_id]" value="' . $value . '" class="regular-text">';
    }

    public function render_mappings_section_text()
    {
        echo '<p>Enter all possible form field names for each standard Octanist property. For example, your "Name" field might be called "name", "your-name", or "full_name" in different forms.</p>';
        echo '<p><strong>Note:</strong> If a form contains multiple fields that map to the same property (e.g., separate "First Name" and "Last Name" fields both mapped to "Name"), their values will be combined with a pipe symbol ( | ).</p>';
    }
    
    public function render_mapping_fields($args)
    {
        $field = $args['field'];
        $mappings = isset($this->options['field_mappings'][$field]) ? $this->options['field_mappings'][$field] : [''];
        
        echo '<div id="mapping-wrapper-' . $field . '">';
        foreach ($mappings as $index => $value) {
            echo '<div><input type="text" name="octanist_settings[field_mappings][' . $field . '][]" value="' . esc_attr($value) . '" class="regular-text" /> <button type="button" class="button remove-mapping-field">-</button></div>';
        }
        echo '</div>';
        echo '<button type="button" class="button add-mapping-field" data-field="' . $field . '">+ Add Field</button>';
    }

    public function render_field_checkbox($args)
    {
        $name = $args['name'];
        $default = $args['default'];
        $checked = isset($this->options[$name]) ? $this->options[$name] : $default;
        echo '<input type="checkbox" name="octanist_settings[' . $name . ']" value="1" ' . checked('1', $checked, false) . '>';
        if ($name === 'debug_mode') {
            echo '<p class="description">Enable to log detailed diagnostic information to the browser console.</p>';
        }
    }

    public function render_field_submission_mode()
    {
        $mode = isset($this->options['submission_mode']) ? $this->options['submission_mode'] : 'ajax';
        ?>
        <fieldset>
            <label>
                <input type="radio" name="octanist_settings[submission_mode]" value="ajax" <?php checked($mode, 'ajax'); ?>>
                <span>AJAX (Default - For most form plugins)</span>
            </label>
            <br>
            <label>
                <input type="radio" name="octanist_settings[submission_mode]" value="standard" <?php checked($mode, 'standard'); ?>>
                <span>Standard (non-AJAX forms)</span>
            </label>
        </fieldset>
        <?php
    }

    public function sanitize_settings($input)
    {
        $new_input = [];

        // Sanitize single text fields
        $new_input['octanist_id'] = isset($input['octanist_id']) ? sanitize_text_field($input['octanist_id']) : '';

        // Sanitize checkboxes
        $new_input['send_to_octanist'] = isset($input['send_to_octanist']) ? '1' : '0';
        $new_input['send_to_datalayer'] = isset($input['send_to_datalayer']) ? '1' : '0';
        $new_input['debug_mode'] = isset($input['debug_mode']) ? '1' : '0';
        
        // Sanitize radio button
        $new_input['submission_mode'] = isset($input['submission_mode']) && in_array($input['submission_mode'], ['ajax', 'standard']) ? $input['submission_mode'] : 'ajax';

        // Sanitize field mappings (array of arrays)
        if (isset($input['field_mappings']) && is_array($input['field_mappings'])) {
            foreach ($input['field_mappings'] as $field => $mappings) {
                $new_input['field_mappings'][$field] = array_map('sanitize_text_field', (array)$mappings);
                // Remove any empty values that might get submitted
                $new_input['field_mappings'][$field] = array_filter($new_input['field_mappings'][$field]);
            }
        }

        return $new_input;
    }
}

// In a real plugin, you'd likely have a loader file or class that calls ->__init()
// For this structure, we'll instantiate and call it directly.
$octanist_admin = new Octanist_Admin();
$octanist_admin->__init();

// The old script loading logic and admin page rendering is now handled by the class.
// We just need to update the script loader to use the new settings format.
add_action('wp_enqueue_scripts', function () {
    $options = get_option('octanist_settings', []);
    $submission_mode = isset($options['submission_mode']) ? $options['submission_mode'] : 'ajax';
    $handler_script = ($submission_mode === 'standard') ? 'handler-standard.js' : 'handler-ajax.js';
    $script_handle = 'octanist-handler-' . $submission_mode;

    wp_enqueue_script(
        $script_handle,
        OFH_URL . 'assets/js/' . $handler_script,
        [],
        '1.1.0', // Version bump
        true
    );

    wp_localize_script($script_handle, 'octanistSettings', [
        'octanistID'      => $options['octanist_id'] ?? '',
        'fieldMappings'   => $options['field_mappings'] ?? [],
        'sendToOctanist'  => $options['send_to_octanist'] ?? '1',
        'sendToDataLayer' => $options['send_to_datalayer'] ?? '0',
        'debugMode'       => $options['debug_mode'] ?? '0',
    ]);
});
