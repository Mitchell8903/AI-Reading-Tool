<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library functions for the aiassist module
 *
 * @package    mod_aiassist
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds a new instance of the aiassist module.
 *
 * This function now inserts the record, saves the PDF file,
 * sets a placeholder processing status, and immediately starts
 * the asynchronous PDF parsing.
 *
 * @param stdClass $data Form data (from mod_form).
 * @param mod_aiassist_mod_form $form The form instance (optional).
 * @return int The ID of the newly created instance.
 */
function aiassist_add_instance($data, $form) {
    global $DB, $COURSE;

    // Ensure the course is set.
    if (empty($data->course)) {
        $data->course = $COURSE->id;
    }

    // Insert the new aiassist instance.
    $data->timecreated = time();
    $data->timemodified = time();
    $data->id = $DB->insert_record('aiassist', $data);

    // Since the course module record isn't created yet, use course context.
    $context = context_course::instance($data->course);

    // Save the uploaded PDF file.
    file_save_draft_area_files(
        $data->pdffile,
        $context->id,
        'mod_aiassist',
        'pdf',
        $data->id,
        ['subdirs' => 0]
    );

    // Store a placeholder processing status.
    $record = new stdClass();
    $record->id       = $data->id;
    $record->markdown = 'Processing...';
    $DB->update_record('aiassist', $record);

    // Schedule an adhoc task to perform PDF parsing asynchronously.
    $task = new \mod_aiassist\task\parse_pdf_task();

    $task->set_custom_data($data->id);
    $futuretime = time() + 5;
    $task->set_next_run_time($futuretime);
    \core\task\manager::queue_adhoc_task($task);

    return $data->id;
}

/**
 * Updates an existing instance of the aiassist module.
 *
 * This function now only updates the instance record without
 * re-triggering PDF parsing.
 *
 * @param stdClass $data Form data (from mod_form).
 * @param mod_aiassist_mod_form $form The form instance (optional).
 * @return bool True if successful, false otherwise.
 */
function aiassist_update_instance($data, $form) {
    global $DB;

    $data->timemodified = time();
    // Moodle passes in $data->instance for the existing ID; rename it to $data->id.
    $data->id = $data->instance;
    $DB->update_record('aiassist', $data);

    return true;
}

/**
 * Deletes an instance of the aiassist module from the database.
 *
 * @param int $id The ID of the instance to delete.
 * @return bool True if the instance was successfully deleted, false otherwise.
 */
function aiassist_delete_instance($id) {
    global $DB;

    if (!$aiassist = $DB->get_record('aiassist', ['id' => $id])) {
        return false;
    }

    // Retrieve the course module record.
    try {
        $cm = get_coursemodule_from_instance('aiassist', $id, $aiassist->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
    } catch (Exception $e) {
        debugging("Course module record not found in aiassist_delete_instance; using course context. Error: " . $e->getMessage(), DEBUG_DEVELOPER);
        $context = context_course::instance($aiassist->course);
    }

    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    $cache = cache::make('mod_aiassist', 'cache_name');
    $cache->purge();

    $DB->delete_records('aiassist', ['id' => $id]);

    add_to_log($aiassist->course, 'aiassist', 'delete', '', "Deleted instance ID $id");

    return true;
}

/**
 * Returns the icon URL for the aiassist module.
 *
 * @return string The URL of the icon.
 */
function aiassist_get_icon() {
    global $CFG;
    return $CFG->wwwroot . '/mod/aiassist/pix/icon.png';
}

/**
 * Backup the module instance data
 *
 * @param backup_nested_element $backup The backup structure
 * @param stdClass $instance The module instance
 */
function aiassist_backup_instance($backup, $instance) {
    // Add the module instance data
    $backup->add_child('aiassist', array(
        'id' => $instance->id,
        'course' => $instance->course,
        'name' => $instance->name,
        'timecreated' => $instance->timecreated,
        'timemodified' => $instance->timemodified,
        'markdown' => $instance->markdown
    ));
}

/**
 * Restore the module instance data
 *
 * @param restore_nested_element $restore The restore structure
 * @param stdClass $instance The module instance
 */
function aiassist_restore_instance($restore, $instance) {
    global $DB;
    // Get the module instance data
    $data = $restore->get_child('aiassist');
    
    // Update the module instance
    $instance->course = $data->get_value('course');
    $instance->name = $data->get_value('name');
    $instance->timecreated = $data->get_value('timecreated');
    $instance->timemodified = $data->get_value('timemodified');
    $instance->markdown = $data->get_value('markdown');
    
    // Save the module instance
    $DB->update_record('aiassist', $instance);
}

/**
 * Backup the module files
 *
 * @param backup_nested_element $backup The backup structure
 * @param stdClass $instance The module instance
 */
function aiassist_backup_files($backup, $instance) {
    // Get the context
    $context = context_module::instance($instance->id);
    
    // Get the file storage
    $fs = get_file_storage();
    
    // Get all files in the module
    $files = $fs->get_area_files($context->id, 'mod_aiassist', 'pdf', $instance->id, '', false);
    
    // Add each file to the backup
    foreach ($files as $file) {
        $backup->add_child('file', array(
            'filename' => $file->get_filename(),
            'content' => $file->get_content()
        ));
    }
}

/**
 * Restore the module files
 *
 * @param restore_nested_element $restore The restore structure
 * @param stdClass $instance The module instance
 */
function aiassist_restore_files($restore, $instance) {
    // Get the context
    $context = context_module::instance($instance->id);
    
    // Get the file storage
    $fs = get_file_storage();
    
    // Get all files from the backup
    $files = $restore->get_children('file');
    
    // Restore each file
    foreach ($files as $file) {
        $filename = $file->get_value('filename');
        $content = $file->get_value('content');
        
        // Create the file
        $fileinfo = array(
            'contextid' => $context->id,
            'component' => 'mod_aiassist',
            'filearea' => 'pdf',
            'itemid' => $instance->id,
            'filepath' => '/',
            'filename' => $filename
        );
        
        $fs->create_file_from_string($fileinfo, $content);
    }
}

