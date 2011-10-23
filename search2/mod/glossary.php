<?php

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php

//based on /mod/glossary/showentry?eid=NNN

function glossary_gs_iterator($from = 0) {
  global $DB;

  $sql = "SELECT id, timemodified AS modified FROM {glossary_entries} WHERE timemodified > ? ORDER BY timemodified ASC";

  return $DB->get_recordset_sql($sql, array($from));
}

function glossary_gs_get_documents($id) {
  global $DB;

  $documents = array();
  $glossary = glossary_get_full($id);
  $course = $DB->get_record('course', array('id' => $glossary->course));
  $cm = get_coursemodule_from_instance('glossary', $glossary->glossaryid, $glossary->course);
  $user = $DB->get_record('user', array('id' => $glossary->userid));
  $context = get_context_instance(CONTEXT_MODULE, $cm->id);

  /*  var_dump($glossary);
    var_dump($cm);
    var_dump($context);
   */

  $document = new gs_document();
  $document->set_id($glossary->id);
  $document->set_user($user);
  $document->set_created($glossary->timecreated);
  $document->set_modified($glossary->timemodified);
  $document->set_title($glossary->concept);
  $document->set_courseid($glossary->course);
  //format text from whatever format it was stored in to HTML
  $document->set_content(format_text($glossary->definition, $glossary->definitionformat,
          array('nocache' => true, 'para' => false)));
  $document->set_type(GS_TYPE_HTML);
  $document->set_contextlink('/mod/glossary/showentry?eid=' . $glossary->id);
  $document->set_module('glossary');
  $documents[] = $document;

  $fs = get_file_storage();
  if ($files = $fs->get_area_files($context->id, 'mod_glossary', 'attachment', $glossary->id, "timemodified", false)) {
    foreach ($files as $file) {
      $filename = $file->get_filename();
      $mimetype = $file->get_mimetype();
      $path = $file->get_content_file_location();
      $url = file_encode_url('/pluginfile.php',
          '/' . $context->id . '/mod_glossary/attachment/' . $glossary->id . '/' . $filename);

      $document = clone $document;
      $document->set_directlink($url);
      $document->set_type(GS_TYPE_FILE);
      $document->set_filepath($path);
      $document->set_mime($mimetype);
      $documents[] = $document;
    }
  }
  return $documents;
}

function glossary_gs_access($id) {
  global $DB;
  $entry = $DB->get_record('glossary_entries', array('id' => $id));
  $glossary = $DB->get_record('glossary', array('id' => $entry->glossaryid));
  $cm = get_coursemodule_from_instance('glossary', $glossary->id, 0, false);
  $course = $DB->get_record('course', array('id' => $cm->course));

  try {
    require_course_login($course, true, $cm, true, true);
  } catch (require_login_exception $ex) {
    return false;
  }
  $entry->glossaryname = $glossary->name;
  $entry->cmid = $cm->id;
  $entry->courseid = $cm->course;

  $modinfo = get_fast_modinfo($course);
  // make sure the entry is visible
  if (empty($modinfo->cms[$entry->cmid]->uservisible)) {
    return false;
  }

  // make sure the entry is approved (or approvable by current user)
  if (!$entry->approved and ($USER->id != $entry->userid)) {
    $context = get_context_instance(CONTEXT_MODULE, $entry->cmid);
    if (!has_capability('mod/glossary:approve', $context)) {
      return false;
    }
  }
  return true;
}

function glossary_get_full($id) {
  global $DB;

  return $DB->get_record_sql("SELECT e.*, g.course
                             FROM {glossary_entries} e
                                  JOIN {glossary} g ON e.glossaryid = g.id
                            WHERE e.id = ?",
          array($id));
}