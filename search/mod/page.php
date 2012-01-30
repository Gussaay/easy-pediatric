<?php

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php

//based on 

function page_search_iterator($from = 0) {
  global $DB;

  $sql = "SELECT id, timemodified AS modified FROM {page} WHERE timemodified > ? ORDER BY timemodified ASC";

  return $DB->get_recordset_sql($sql, array($from));
}

function page_search_get_documents($id) {
  global $DB;

  $documents = array();
  $page = $DB->get_record('page', array('id' => $id), '*', MUST_EXIST);
  $course = $DB->get_record('course', array('id' => $page->course), '*', MUST_EXIST);
  $cm = get_coursemodule_from_instance('page', $page->id, $page->course, false, MUST_EXIST);
  $context = get_context_instance(CONTEXT_MODULE, $cm->id, MUST_EXIST);

  /*  var_dump($glossary);
    var_dump($cm);
    var_dump($context);
   */

  $document = new search_document();
  $document->set_id($page->id);
  $document->set_created($page->timecreated);
  $document->set_modified($page->timemodified);
  $document->set_title($page->name);
  $document->set_courseid($page->course);
  //format text from whatever format it was stored in to HTML
  $document->set_content(
      format_text($page->intro, $page->introformat, array('nocache' => true, 'para' => false)) . ' ' .
      format_text($page->content, $page->contentformat, array('nocache' => true, 'para' => false))
  );
  $document->set_type(SEARCH_TYPE_HTML);
  $document->set_contextlink('/mod/page/view.php?id=' . $page->id);
  $document->set_module('page');
  $documents[] = $document;

  return $documents;
}

function page_search_access($id) {
  global $DB;
  //@TODO - finish
    if (!$page = $DB->get_record('page', array('id'=>$p))) {
        print_error('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('page', $page->id, $page->course, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/page:view', $context);
}
