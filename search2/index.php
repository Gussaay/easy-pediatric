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