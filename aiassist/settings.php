<?php
// Ensure the script is being run in a Moodle context.
defined('MOODLE_INTERNAL') || die();

// Check if the admin tree is loaded.
if ($ADMIN->fulltree) {
    // Add a settings page for the plugin.
    $settings->add(new admin_setting_heading(
        'aiassist_settings',
        get_string('pluginname', 'mod_aiassist'),
        get_string('settingsdescription', 'mod_aiassist')
    ));

    // Add a setting for the Flask app IP.
    $settings->add(new admin_setting_configtext(
        'mod_aiassist/flask_ip',
        get_string('flaskip', 'mod_aiassist'),
        get_string('flaskip_desc', 'mod_aiassist'),
        'localhost',
        PARAM_HOST
    ));

    // Add a setting for the Flask app port.
    $settings->add(new admin_setting_configtext(
        'mod_aiassist/flask_port',
        get_string('flaskport', 'mod_aiassist'),
        get_string('flaskport_desc', 'mod_aiassist'),
        '5000',
        PARAM_INT
    ));
}
