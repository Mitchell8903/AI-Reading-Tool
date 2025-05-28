<?php
namespace mod_aiassist\task;

defined('MOODLE_INTERNAL') || die();

class parse_pdf_task extends \core\task\adhoc_task {
    /**
     * Execute the task.
     */
    public function execute() {
        global $DB, $CFG;

        $instanceid = $this->get_custom_data();

        // Get the aiassist record.
        if (!$aiassist = $DB->get_record('aiassist', ['id' => $instanceid], '*', MUST_EXIST)) {
            throw new \moodle_exception('invalidinstance', 'mod_aiassist');
        }

        // Get the course module.
        $cm = get_coursemodule_from_instance('aiassist', $instanceid, $aiassist->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Get the file storage.
        $fs = get_file_storage();

        // Get the PDF file.
        $context = \context_course::instance($aiassist->course);
        $files = $fs->get_area_files($context->id, 'mod_aiassist', 'pdf', $instanceid, '', false);

        if (empty($files)) {
            $coursecontext = \context_course::instance($aiassist->course);
            $files = $fs->get_area_files($coursecontext->id, 'mod_aiassist', 'pdf', $instanceid, '', false);
        }

        if (empty($files)) {
            debugging("No PDF file found for aiassist ID: $instanceid", DEBUG_DEVELOPER);
            return;
        }

        $file = reset($files);

        // Update status to processing.
        $record = new \stdClass();
        $record->id = $instanceid;
        $record->markdown = 'Processing PDF...';
        $DB->update_record('aiassist', $record);

        try {
            // Get Flask server configuration.
            $flask_ip = get_config('mod_aiassist', 'flask_ip');
            $flask_port = get_config('mod_aiassist', 'flask_port');

            // Create a temporary file.
            $tempfile = tempnam(sys_get_temp_dir(), 'aiassist_');
            $file->copy_content_to($tempfile);

            // Prepare the cURL request.
            $ch = curl_init();
            $url = "http://{$flask_ip}:{$flask_port}/process_pdf";

            $post_data = array(
                'file' => new \CURLFile($tempfile, 'application/pdf', 'document.pdf')
            );

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Execute the request.
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // Clean up.
            curl_close($ch);
            unlink($tempfile);

            if ($http_code !== 200) {
                throw new \moodle_exception('flaskerror', 'mod_aiassist', '', $response);
            }

            // Update the record with the markdown content.
            $record = new \stdClass();
            $record->id = $instanceid;
            $record->markdown = $response;
            $DB->update_record('aiassist', $record);

        } catch (\Exception $e) {
            // Update status to error.
            $record = new \stdClass();
            $record->id = $instanceid;
            $record->markdown = 'Error processing PDF: ' . $e->getMessage();
            $DB->update_record('aiassist', $record);
        }
    }
}
