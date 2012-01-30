<?php

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php


function label_search_iterator($from = 0) {
  global $DB;

  $sql = "SELECT id, timemodified AS modified FROM {label} WHERE timemodified > ? ORDER BY timemodified ASC";

  return $DB->get_recordset_sql($sql, array($from));
}

function label_search_get_documents($id) {
  global $DB;

  $documents = array();

  $label = $DB->get_record("label", array("id" => $id));

  $document = new search_document();
  $document->set_id($label->id);
  //only 1 timestamp stored for labels
  $document->set_created($label->timemodified);
  $document->set_modified($label->timemodified);

  $document->set_title($label->name);
  $document->set_courseid($label->course);
//format text from whatever format it was stored in to HTML
  $document->set_content(format_text($label->intro, $label->introformat, array('nocache' => true, 'para' => false)));
  $document->set_type(SEARCH_TYPE_HTML);
  // /mod/label/view.php?l=NNN (will work better when MDL-29889 is done)
  $document->set_contextlink('/mod/label/view.php?l=' . $label->id);
  $document->set_module('label');
  $documents[] = $document;

  return $documents;
}

/**
 * Access to the course was already checked.
 * @param type $id
 * @return type 
 */
function label_search_access($id) {
  global $DB;

  if(!$label = $DB->get_record("label", array("id" => $id))) {
    return SEARCH_ACCESS_DELETED;
  }

  if(!$course = $DB->get_record("course", array("id" => $label->course))) {
    return SEARCH_ACCESS_DELETED;
  }
     
  if(!$cm = get_coursemodule_from_instance("label", $label->id, $course->id)) {
    return SEARCH_ACCESS_DELETED;
  }

  return SEARCH_ACCESS_GRANTED;
}
