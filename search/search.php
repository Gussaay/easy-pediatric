<?php

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php

require_once('../config.php');
//ini_set('include_path', $CFG->dirroot . DIRECTORY_SEPARATOR . 'search2' . PATH_SEPARATOR . ini_get('include_path'));
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/search/lib.php');

require_login();

class search_search_form extends moodleform {

    function definition() {

        $mform = & $this->_form;
        $objs = array();
        $objs[] = $mform->createElement('submit', 'optimize', 'Optimize');
        $objs[] = $mform->createElement('submit', 'reset', 'Clear index');
        $objs[] = $mform->createElement('submit', 'index', 'Run indexer');
        $mform->addElement('group', 'controlgroup', '', $objs, ' ', false);
    }

}

/*
  $course = $DB->get_record('course', array('id'=>2));
  $can = can_access_course($course, 2);

 */
$PAGE->set_context(get_system_context());

$q = required_param('q', PARAM_TEXT);

$index = search_get_index();
$gstimes = array();
$timestart = microtime(true);
$hits = $index->find($q);
$timestop = microtime(true);
mtrace("Query time: " . ($timestop - $timestart), '<br />');
mtrace("Hits: " . count($hits), '<br />');
for ($i = 0; $i < count($gstimes) - 1; $i++) {
    mtrace("gstimes #$i:" . ($gstimes[$i + 1] - $gstimes[$i]),'<br/>');
}
mtrace("Memory allocated: ".memory_get_usage(), '<br />');

//filter out non-accessible records (security)
$countbefore = count($hits);
foreach ($hits as $k => $hit) {
    
    $func = $hit->module . '_search_access';
    $result = $func($hit->setid);
    switch ($result) {
        case SEARCH_ACCESS_DELETED:
            $index->delete($hit->id);
        case SEARCH_ACCESS_DENIED:
            unset($hits[$k]);
            break;
    }
}
$countafter = count($hits);
mtrace($countbefore - $countafter . ' hits removed as non-accessible for current user', '<br />');
//@TODO - cache search results, for now just take up to 100 best hits
//
//display search results
foreach ($hits as $hit) {
    include('_result.php');
}

//display pager
$baseurl = new moodle_url('search.php');
$count = count($hits);
$page = 0;
$perpage = 20;
echo $OUTPUT->paging_bar($count, $page, $perpage, $baseurl);