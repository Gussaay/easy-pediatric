<?php

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php

//based on 
function resource_search_iterator($from = 0) {
  global $DB;

  $sql = "SELECT id, timemodified AS modified FROM {resource} WHERE timemodified > ? ORDER BY timemodified ASC";

  return $DB->get_recordset_sql($sql, array($from));
}

function resource_search_get_documents($id) {
  global $DB;

  $documents = array();

  $resource = $DB->get_record('resource', array('id' => $id));
  $cm = get_coursemodule_from_instance('resource', $resource->id, $resource->course);
  $context = get_context_instance(CONTEXT_MODULE, $cm->id);

  $document = new search_document();
  $document->set_id($resource->id);
  $document->set_created($resource->timemodified);
  $document->set_modified($resource->timemodified);
  $document->set_title($resource->name);
  $document->set_courseid($resource->course);
  //format text from whatever format it was stored in to HTML
  $document->set_content(format_text($resource->intro, $resource->introformat, array('nocache' => true, 'para' => false)));
  $document->set_type(SEARCH_TYPE_HTML);
  $document->set_contextlink('/mod/resource/view.php?r=' . $resource->id);
  $document->set_module('resource');
  $documents[] = $document;

  $fs = get_file_storage();
  $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
  if (count($files) > 0) {
    $file = reset($files);
    unset($files);
  }

  $filename = $file->get_filename();
  $mimetype = $file->get_mimetype();
  $path = $file->get_content_file_location();
  $url = file_encode_url('/pluginfile.php',
      '/' . $context->id . '/mod_resource/attachment/' . $resource->id . '/' . $filename);

  $document = clone $document;
  $document->set_directlink($url);
  $document->set_type(SEARCH_TYPE_FILE);
  $document->set_filepath($path);
  $document->set_mime($mimetype);
  $documents[] = $document;
  //var_dump($documents); die();
  return $documents;
}

function resource_search_access($id) {
  global $DB;

  try {
    $resource = $DB->get_record('resource', array('id' => $id), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('resource', $resource->id, $resource->course, false);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
  } catch (dml_missing_record_exception $ex) {
    return SEARCH_ACCESS_DELETED;
  }
  try {
    require_course_login($course, true, $cm, true, true);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    require_capability('mod/resource:view', $context);
  } catch (moodle_exception $ex) {
    return false;
  }

  return true;
}
