<?php

/**
 * This script contains an admin-section extended version of the plugin.
 */

class GoogleWebfontsForWooFrameworkAdmin extends GoogleWebfontsForWooFramework
{
    // Some constants to help make sense of the admin page structure.
    const settings_group_name = 'gwfc-group';
    const settings_page_slug = 'gw-for-wooframework';
    // CHECKME: the 'page' should "match the page slug" according to some codex documentation,
    // but is different in many code examples.
    const settings_page = 'gwfc_main_section';
    const settings_section_id = 'gwfc_main';

    // Field names.
    const settings_field_api_key = 'google_api_key';
    const settings_field_new_fonts = 'new_fonts';
    const settings_field_old_fonts = 'old_fonts';

    public function init()
    {
        // Add the missing fonts in the admin page.
        add_action('admin_head', array($this, 'action_add_fonts'), 20);

        if (is_admin()) {
            // Add the admin menu.
            add_action('admin_menu', array($this, 'admin_menu'));

            // Register the settings.
            add_action('admin_init', array($this, 'register_settings'));
        }

        parent::init();
    }

    public function admin_menu()
    {
        // And "options_page" will go under the settings menu as a sub-menu.
        add_options_page(
            __('Google Webfonts for Woo Framework Options'),
            __('Google Webfonts for Woo Framework'),
            'manage_options',
            self::settings_page_slug,
            array($this, 'plugin_options')
        );
    }

    public function plugin_options()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        echo '<div class="wrap">';
        screen_icon();
        echo '<h2>' . __('Google Webfonts for Woo Framework Options') . '</h2>';

        echo '<form method="post" action="options.php">';

        echo '<table class="form-table">';

        settings_fields(self::settings_group_name);
        do_settings_sections(self::settings_page);

        echo '</table>';

        submit_button();
        echo '</form>';

        echo '</div>';
    }

    public function register_settings()
    {
        // Register the settings.

        register_setting(
            self::settings_group_name, 
            self::settings_field_api_key,
            array($this, self::settings_field_api_key . '_validate')
        );

        // Register a section in the page.

        add_settings_section(
            self::settings_section_id, 
            __('Main Settings'), 
            array($this, 'plugin_main_section_text'),
            self::settings_page
        );

        // Add fields to the section.

        // The API key.
        add_settings_field(
            self::settings_field_api_key,
            __('Google Developer API Key'),
            array($this, self::settings_field_api_key . '_field'),
            self::settings_page,
            self::settings_section_id // section
        );

        // List of added fonts (read-only).
        add_settings_field(
            self::settings_field_old_fonts,
            __('Framework fonts (view only)'),
            array($this, self::settings_field_old_fonts . '_field'),
            self::settings_page,
            self::settings_section_id
        );

        // List of added fonts (read-only).
        add_settings_field(
            self::settings_field_new_fonts,
            __('New fonts introduced (view only)'),
            array($this, self::settings_field_new_fonts . '_field'),
            self::settings_page,
            self::settings_section_id
        );
    }

    // Summary text/introduction to the main section.

    public function plugin_main_section_text()
    {
        echo '<p>' . __('Google Webfonts for WooThemes Woo Framework.') . '</p>';
    }

    // Display the input fields.

    // The Google API Key input field.
    public function google_api_key_field() {
        $option = get_option(self::settings_field_api_key, '');
        echo "<input id='" . self::settings_field_api_key . "' name='" . self::settings_field_api_key . "' size='80' type='text' value='{$option}' />";
    }

    // Display the list of original framework fonts.
    public function old_fonts_field() {
        $used_fonts = $this->fonts_used_in_theme();

        if (empty($this->old_fonts)) {
            _e('No framework fonts found');
        } else {
            echo '<select name="' . self::settings_field_old_fonts . '" multiple="multiple" size="10">';

            $i = 1;
            foreach($this->old_fonts as $font) {
                $selected = (isset($used_fonts[$font['name']])) ? ' selected="selected"' : '';
                echo '<option value="'. $i++ .'"' . $selected . '>' .$font['name']. '</option>';
            }

            echo '</select> (' . count($this->old_fonts) . ')';
        }
    }

    // Display the list of new fonts this plugin makes available.
    public function new_fonts_field() {
        $used_fonts = $this->fonts_used_in_theme();

        if (empty($this->new_fonts)) {
            _e('No new fonts found');
        } else {
            echo '<select name="' . self::settings_field_new_fonts . '" multiple="multiple" size="10">';

            $i = 1;
            foreach($this->new_fonts as $font) {
                $selected = (isset($used_fonts[$font['name']])) ? ' selected="selected"' : '';
                echo '<option value="'. $i++ .'"' . $selected . '>' . $font['name'] . '</option>';
            }

            echo '</select> (' . count($this->new_fonts) . ')';
        }
    }

    // Validate the submitted fields.

    public function google_api_key_validate($input) {
        // Make sure it is a URL-safe string.
        if ($input != rawurlencode($input)) {
            add_settings_error(self::settings_field_api_key, 'texterror', __('API key contains invalid characters'), 'error');
        } else {
            // If valid, then discard the current fonts cache, so a fresh fetch is
            // done with the new key.
            delete_transient($this->trans_cache_name);
        }

        return $input;
    }

    /**
     * Get a list of all the fonts used in the theme at present.
     */

    public function fonts_used_in_theme()
    {
        global $google_fonts;
        global $woo_options;
        static $fonts = null;

        // If we have done the search already, then returned the cached list.
        if (is_array($fonts)) return $fonts;

        // The font list we will return.
        $fonts = array();

        // List of font names we find in the options of the theme.
        $option_fonts = array();

        // Go through the options in the theme.
        if (!empty($woo_options)) {
            foreach ($woo_options as $option) {
                // Check if option has "face" in array.
                if (is_array($option) && isset($option['face'])) {
                    if (!isset($option_fonts[$option['face']])) {
                        $option_fonts[$option['face']] = $option['face'];
                    }
                }
            }

            // Now if we have found a list of used font families, check which
            // are available as Google fonts.
            if (!empty($option_fonts)) {
                foreach ($google_fonts as $font) {
                    if (isset($option_fonts[$font['name']]) && !isset($fonts[$font['name']])) {
                        $fonts[$font['name']] = $font['name'];
                    }
                }
            }
        }

        return $fonts;
    }

}

