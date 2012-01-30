<?php

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php

require_once('../config.php');
//ini_set('include_path', $CFG->dirroot . DIRECTORY_SEPARATOR . 'search2' . PATH_SEPARATOR . ini_get('include_path'));
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/search/lib.php');

require_login();
$PAGE->set_context(get_system_context());

search_reset(); 
@set_time_limit(0);
echo '<pre>';

search_index_check();
search_index();


