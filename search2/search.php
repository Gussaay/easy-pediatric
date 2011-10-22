<?php
/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php

require_once('../config.php');
ini_set('include_path', $CFG->dirroot . DIRECTORY_SEPARATOR . 'search2' . PATH_SEPARATOR . ini_get('include_path'));
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/search2/lib.php');

require_login();
$PAGE->set_context(get_system_context());

$q = required_param('q', PARAM_TEXT);

@set_time_limit(0);

Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
$index = new Zend_Search_Lucene(GS_INDEX_PATH);
$hits = $index->find($q);

//filter out non-accessible records (security)
$countbefore = count($hits);
foreach ($hits as $k=>$hit) {
  $func = 'gs_'.$hit->module.'_access';
  if(!$func($hit->setid)) {
    unset($hits[$k]);
  }
}
$countafter = count($hits);
mtrace($countbefore - $countafter.' hits removed as non-accessible for current user');
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