<?php

require_once('../config.php');
ini_set('include_path', $CFG->dirroot . DIRECTORY_SEPARATOR . 'search2' . PATH_SEPARATOR . ini_get('include_path'));
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/search2/Zend/Search/Lucene.php');
require_once($CFG->dirroot . '/search2/gs_document.php');
require_once($CFG->dirroot . '/search2/gs_lucene_html.php');
require_once($CFG->dirroot . '/search2/lib.php');

require_login();
$PAGE->set_context(get_system_context());

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */

@set_time_limit(0);
echo '<pre>';



class gs_document_text extends Zend_Search_Lucene_Document {

  public function create() {
    $this->addField(Zend_Search_Lucene_Field::Text('title', $this->title));
    $this->addField(Zend_Search_Lucene_Field::UnStored('content', $this->content));
  }

}

if (!file_exists(SEARCH_INDEX_PATH)) {
  if (!mkdir(SEARCH_INDEX_PATH, $CFG->directorypermissions)) {
    error('Error creating data directory at: ' . SEARCH_INDEX_PATH . '. Please correct.');
  }
}


Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
$index = new Zend_Search_Lucene(SEARCH_INDEX_PATH, true);

$recordset = gs_forum_iterator();
foreach ($recordset as $record) {
  //var_dump($record);
  $documents = gs_forum_get_documents($record->id);
  foreach ($documents as $document) {
    if ($document->get_type() == GS_TYPE_HTML) {
      $lucenedoc = new gs_lucene_html($document);
      $index->addDocument($lucenedoc);
    }
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
  global $CFG,$DB;

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
  $document->set_content(format_text($post->message,$post->messageformat,array('nocache'=>true,'para'=>false)));
  $document->set_type(GS_TYPE_HTML);
  $document->set_directlink('/mod/forum/discuss.php?d='.$post->discussion.'#p'.$post->id);
  $document->set_module('forum');    
  $documents[] = $document;
  
  //var_dump($document);  die();
//files
  $fs = get_file_storage();
  $files = $fs->get_area_files($context->id, 'mod_forum', 'attachment', $postid, "timemodified", false);

  foreach ($files as $file) {
    /* @var $file stored_file  */
    $filename = $file->get_filename();
    $path = file_encode_url($CFG->wwwroot . '/pluginfile.php',
        '/' . $context->id . '/mod_forum/attachment/' . $postid . '/' . $filename);

    // var_dump($file);
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