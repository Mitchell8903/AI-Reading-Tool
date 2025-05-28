<?php
// Include required Moodle configuration and libraries.
require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/mod/aiassist/parsedown.php');
require_once($CFG->libdir . '/filelib.php');

// Get course module ID.
$id = required_param('id', PARAM_INT);

// Ensure the course module exists.
try {
    $cm = get_coursemodule_from_id('aiassist', $id, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
} catch (Exception $e) {
    throw new moodle_exception('invalidcoursemodule', 'error', '', $id);
}

// Set up the page.
$PAGE->set_url('/mod/aiassist/view.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context(context_module::instance($cm->id));

// Ensure the user is logged in and has access to the course.
require_login($course, true, $cm);

// Retrieve the markdown content from the database.
global $DB;
$activity = $DB->get_record('aiassist', ['id' => $cm->instance], '*', MUST_EXIST);

// Initialize Parsedown with proper configuration
$parsedown = new Parsedown();
$parsedown->setSafeMode(false);  // Allow HTML and base64 images
$parsedown->setBreaksEnabled(true);  // Convert line breaks to <br>
$markdownContent = $parsedown->text($activity->markdown ?? '**Error:** Markdown content not found.');

// Set the proxy URL for chatbot requests.
$proxy_url = $CFG->wwwroot . '/mod/aiassist/assistant_proxy.php';

// Output the page
echo $OUTPUT->header();

// Add the assistant interface
?>
<div id="assistant-container">
    <div id="chat-container">
        <div id="chat-messages"></div>
        <div id="input-container">
            <textarea id="user-input" placeholder="Ask a question about the text..."></textarea>
            <button id="send-button">Send</button>
        </div>
    </div>
</div>

<!-- Render the markdown content -->
<div class="markdown-body" id="markdown-body">
    <div class="chapter-navigation">
        <button id="prev-chapter" class="chapter-button">Previous</button>
        <span id="chapter-title" class="chapter-title"></span>
        <button id="next-chapter" class="chapter-button">Next</button>
    </div>
    <div class="chapter-content" id="chapter-content">
        <?php echo $markdownContent; ?>
    </div>
</div>

<script>
    window.flaskConfig = {
        ip: "<?php echo get_config('mod_aiassist', 'flask_ip'); ?>",
        port: "<?php echo get_config('mod_aiassist', 'flask_port'); ?>"
    };
    window.markdownContent = <?php echo json_encode($activity->markdown ?? ''); ?>;
    window.targetWordCount = <?php echo (int)$activity->targetwordcount ?: 300; ?>;
</script>

<script src="scripts/script.js"></script>
<?php
echo $OUTPUT->footer();
