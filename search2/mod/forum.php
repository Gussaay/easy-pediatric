<?php

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php

//mdl_forum_discussions
function forum_gs_iterator($from = 0) {
  global $DB;

  $sql = "SELECT id, modified FROM {forum_posts} WHERE modified > ? ORDER BY modified ASC";

  return $DB->get_recordset_sql($sql, array($from));
}

function forum_gs_get_documents($postid) {
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

/**
 *
 * @param integer $id as returned by gs_forum_iterator()
 */
function forum_gs_access($id) {
  global $DB, $USER;
  $post = $DB->get_record('forum_posts', array('id' => $id), '*', MUST_EXIST);
  $discussion = $DB->get_record('forum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
  $forum = $DB->get_record('forum', array('id' => $discussion->forum), '*', MUST_EXIST);
  $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
  $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
  $context = get_context_instance(CONTEXT_MODULE, $cm->id);

// Make sure groups allow this user to see the item they're rating
  if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
    if (!groups_group_exists($discussion->groupid)) { // Can't find group
      return false;
    }

    if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
      // do not allow viewing of posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
      return false;
    }
  }

  // perform some final capability checks
  if (!forum_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
    return false;
  }

//forum_user_can_view_post($post, $course, $cm, $forum, $discussion)  
  return true;
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
