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
class gs_search_form extends moodleform {

  function definition() {

    $mform = & $this->_form;
    $objs = array();
    $objs[] = $mform->createElement('submit', 'optimize', 'Optimize');
    $objs[] = $mform->createElement('submit', 'reset', 'Clear index');
    $objs[] = $mform->createElement('submit', 'index', 'Run indexer');
    $mform->addElement('group', 'controlgroup', '', $objs, ' ', false);
  }

}


$PAGE->set_context(get_system_context());

$q = required_param('q', PARAM_TEXT);

$index = gs_get_index();
$hits = $index->find($q);

//filter out non-accessible records (security)
$countbefore = count($hits);
foreach ($hits as $k=>$hit) {
  $func = $hit->module.'_gs_access';
  $result = $func($hit->setid);
  switch($result) {
    case GS_ACCESS_DELETED:
      $index->delete($hit->id);
    case GS_ACCESS_DENIED:
      unset($hits[$k]);
      break;
  }
}
$countafter = count($hits);
mtrace($countbefore - $countafter.' hits removed as non-accessible for current user','<br />');
//put search results into session cache
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