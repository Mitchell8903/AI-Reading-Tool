<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Definition of the form for the aiassist module
 *
 * @package    mod_aiassist
 * @copyright  2024 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_aiassist_mod_form extends moodleform_mod {
    /**
     * Defines the form fields for our activity.
     */
    public function definition() {
        $mform = $this->_form;

        // 1) General settings.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Activity name.
        $mform->addElement('text', 'name', get_string('aiassistname', 'mod_aiassist'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // 2) PDF upload field.
        // Using filepicker gives a draft file area.
        $mform->addElement('filepicker',
            'pdffile',
            get_string('uploadpdf', 'mod_aiassist'),
            null,
            ['accepted_types' => ['.pdf']]
        );
        $mform->setType('pdffile', PARAM_FILE);

        // Add target word count field
        $mform->addElement('text', 'targetwordcount', get_string('targetwordcount', 'mod_aiassist'), array('size' => '10'));
        $mform->setType('targetwordcount', PARAM_INT);
        $mform->setDefault('targetwordcount', 300);
        $mform->addHelpButton('targetwordcount', 'targetwordcount', 'mod_aiassist');
        $mform->addRule('targetwordcount', null, 'numeric', null, 'client');

        // 3) Standard course module elements & action buttons.
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Preprocess form data before display.
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
    }
}
