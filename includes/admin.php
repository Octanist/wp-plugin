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
        // Add a submenu page under the main "Settings" menu
        add_options_page(
            'Octanist Settings', // The title displayed in the browser tab
            'Octanist',          // The text for the menu item
            'manage_options',    // The capability required to see this item
            'octanist-settings', // The slug for the page
            [$this, 'render_settings_page'] // The function to render the page
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        // The hook for a settings submenu page is 'settings_page_{menu_slug}'
        if ($hook !== 'settings_page_octanist-settings') {
            return;
        }
        wp_enqueue_style(
            'octanist-admin-css',
            OCTANIST_URL . 'assets/css/admin-styles.css',
            [],
            '1.1.0'
        );
        wp_enqueue_script(
            'octanist-admin-js',
            OCTANIST_URL . 'assets/js/admin.js',
            ['jquery'],
            '1.1.0',
            true
        );
    }

    public function render_settings_page()
    {
        $this->options = get_option('octanist_settings');
        ?>
        <div class="wrap" id="octanist-settings-page">
            <div class="octanist-header">
                <img src="<?php echo esc_url(OCTANIST_URL . 'assets/icon.svg'); ?>" alt="Octanist Logo">
                <h1>Octanist Settings</h1>
            </div>
            <form method="POST" action="options.php">
                <?php
                settings_fields('octanist_settings_group');

                // We'll render sections manually to wrap them in cards
                global $wp_settings_sections, $wp_settings_fields;

                $page = 'octanist-settings-page';

                if (!isset($wp_settings_sections[$page])) {
                    return;
                }

                foreach ((array) $wp_settings_sections[$page] as $section) {
                    echo '<div class="octanist-card">';
                    if ($section['title']) {
                        echo "<h2>" . esc_html($section['title']) . "</h2>";
                    }

                    if ($section['callback']) {
                        call_user_func($section['callback'], $section);
                    }

                    if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']])) {
                        continue;
                    }
                    
                    echo '<table class="form-table">';
                    do_settings_fields($page, $section['id']);
                    echo '</table>';
                    
                    echo '</div>'; // close .octanist-card
                }
                
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
        $value = isset($this->options['octanist_id']) ? $this->options['octanist_id'] : '';
        echo '<input type="text" id="octanist_id" name="octanist_settings[octanist_id]" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function render_mappings_section_text()
    {
        echo '<div class="octanist-alert-info">';
        echo '<p>Enter all possible form field names for each standard Octanist property. For example, your "Name" field might be called "name", "your-name", or "full_name" in different forms.</p>';
        echo '<p><strong>Note:</strong> If a form contains multiple fields that map to the same property (e.g., separate "First Name" and "Last Name" fields both mapped to "Name"), their values will be combined with a pipe symbol ( | ).</p>';
        echo '</div>';
    }
    
    public function render_mapping_fields($args)
    {
        $field = $args['field'];
        $mappings = isset($this->options['field_mappings'][$field]) ? $this->options['field_mappings'][$field] : [''];
        
        echo '<div class="mapping-field-wrapper" id="mapping-wrapper-' . esc_attr($field) . '">';
        foreach ($mappings as $index => $value) {
            echo '<div><input type="text" name="octanist_settings[field_mappings][' . esc_attr($field) . '][]" value="' . esc_attr($value) . '" class="regular-text" /> <button type="button" class="button remove-mapping-field">-</button></div>';
        }
        echo '</div>';
        echo '<button type="button" class="button add-mapping-field" data-field="' . esc_attr($field) . '">+ Add Field</button>';
    }

    public function render_field_checkbox($args)
    {
        $name = $args['name'];
        $default = $args['default'];
        $checked = isset($this->options[$name]) ? $this->options[$name] : $default;
        echo '<input type="checkbox" name="octanist_settings[' . esc_attr($name) . ']" value="1" ' . checked('1', $checked, false) . '>';
        if ($name === 'debug_mode') {
            echo '<p class="description">Enable to log detailed diagnostic information to the browser console.</p>';
        }
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

    wp_enqueue_script(
        'octanist-handler',
        OCTANIST_URL . 'assets/js/handler.js',
        [],
        '2.0.0', // Version bump for unified handler
        true
    );

    wp_localize_script('octanist-handler', 'octanistSettings', [
        'octanistID'      => $options['octanist_id'] ?? '',
        'fieldMappings'   => $options['field_mappings'] ?? [],
        'sendToOctanist'  => $options['send_to_octanist'] ?? '1',
        'sendToDataLayer' => $options['send_to_datalayer'] ?? '0',
        'debugMode'       => $options['debug_mode'] ?? '0',
    ]);
});
