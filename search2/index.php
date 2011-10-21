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

@set_time_limit(0);
echo '<pre>';



if (!file_exists(GS_INDEX_PATH)) {
  if (!mkdir(GS_INDEX_PATH, $CFG->directorypermissions)) {
    error("Error creating data directory at: '" . GS_INDEX_PATH . "'.");
  }
}

Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
$index = new Zend_Search_Lucene(GS_INDEX_PATH, true);

$recordset = gs_forum_iterator();
foreach ($recordset as $record) {
  //var_dump($record);
  $documents = gs_forum_get_documents($record->id);
  //var_dump($documents);
  foreach ($documents as $document) {
    switch ($document->get_type()) {
      case GS_TYPE_HTML:
        $lucenedoc = new gs_lucene_html($document);
        break;
      case GS_TYPE_FILE:
        $lucenedoc = gs_document_for_mime($document);
        break;
      default:
        throw new gs_exception("Wrong document type");
    }

    $index->addDocument($lucenedoc);
  }
}
$index->commit();

//var_dump($recordset);
//start with forum iterator
//mdl_forum_discussions
function gs_forum_iterator($from = 0) {
  global $DB;

  $sql = "SELECT id FROM {forum_posts} WHERE modified > ?";

  return $DB->get_recordset_sql($sql, array($from));
}

function gs_forum_get_documents($postid) {
  global $CFG, $DB;

  //return array of indexable documents: post and attachment
  $documents = array();

  //general data
  $post = forum_get_post_full2($postid);
  $cm = get_coursemodule_from_instance('forum', $post->forum, $post->course);
  $context = get_context_instance(CONTEXT_MODULE, $cm->id);

  $user = $DB->get_record('user', array('id' => $post->userid));

  $document = new gs_document();
  $document->set_id($post->id);
  $document->set_user($user);
  $document->set_created($post->created);
  $document->set_modified($post->modified);
  $document->set_title($post->subject);
  $document->set_courseid($post->course);
  //format text from whatever format it was stored in to HTML
  $document->set_content(format_text($post->message, $post->messageformat, array('nocache' => true, 'para' => false)));
  $document->set_type(GS_TYPE_HTML);
  $document->set_contextlink('/mod/forum/discuss.php?d=' . $post->discussion . '#p' . $post->id);
  $document->set_module('forum');
  $documents[] = $document;

  //var_dump($document);  die();
  //files
  $fs = get_file_storage();
  $files = $fs->get_area_files($context->id, 'mod_forum', 'attachment', $postid, "timemodified", false);
  //if($files) {  echo '<pre>'; var_dump($files); die();  }
  foreach ($files as $file) {
    /* @var $file stored_file  */
    $filename = $file->get_filename();
    $path = $file->get_content_file_location();
    $url = file_encode_url('/pluginfile.php', '/' . $context->id . '/mod_forum/attachment/' . $postid . '/' . $filename);

    $document = clone $document;
    $document->set_directlink($url);
    $document->set_type(GS_TYPE_FILE);
    $document->set_filepath($path);
    $document->set_mime($file->get_mimetype());
    $documents[] = $document;
  }
  return $documents;
}

function gs_forum_access($id) {
//forum_user_can_view_post($post, $course, $cm, $forum, $discussion)  
}

function forum_get_post_full2($postid) {
  global $CFG, $DB;

  return $DB->get_record_sql("SELECT p.*, d.course, d.forum, u.firstname, u.lastname, u.email, u.picture, u.imagealt
                             FROM {forum_posts} p
                                  JOIN {forum_discussions} d ON p.discussion = d.id
                                  LEFT JOIN {user} u ON p.userid = u.id
                            WHERE p.id = ?",
          array($postid));
}

function gs_document_for_mime(gs_document $doc) {
  switch ($doc->get_mime()) {
    case 'application/pdf':
      if (gs_lucene_pdf::is_enabled()) {
        return new gs_lucene_pdf($doc);
      }
      else {
        return null;
      }
    case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
      return new gs_lucene_pptx($doc);
    default:
      mtrace("Mime type '" . $doc->get_mime() . "not supported");
      return null;
  }
}