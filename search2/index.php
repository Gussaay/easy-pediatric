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

gs_reset();
@set_time_limit(0);
echo '<pre>';

if (!file_exists(GS_INDEX_PATH)) {
  if (!mkdir(GS_INDEX_PATH, $CFG->directorypermissions)) {
    error("Error creating data directory at: '" . GS_INDEX_PATH . "'.");
  }
}

Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
$index = new Zend_Search_Lucene(GS_INDEX_PATH, true);

$iterators = gs_get_iterators();
foreach ($iterators as $name => $iterator) {
  mtrace('Processing module ' . $iterator->module);
  set_config($name . '_indexing_start', time(), 'gs');
  $iterfunction = $iterator->iterator;
  $getdocsfunction = $iterator->documents;
  //get the timestamp of the last commited run for the plugin
  $lastrun = get_config('gs', $name . '_last_run');
  $recordset = $iterfunction($lastrun);
  $norecords = 0;
  $nodocuments = 0;
  $nodocumentsignored = 0;
  foreach ($recordset as $record) {
    ++$norecords;
    //var_dump($record);
    $documents = $getdocsfunction($record->id);
    //@TODO find out if it's not an update - delete whole document set if so
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
      if ($lucenedoc) {
        $index->addDocument($lucenedoc);
        ++$nodocuments;
      }
      else {
        ++$nodocumentsignored;
      }
      if ($nodocuments % 20000) {
        $index->commit();
        set_config($name . '_last_run', $record->modified, 'gs');
      }
    }
  }
  $recordset->close();
  set_config($name . '_indexing_end', time(), 'gs');
  if ($norecords > 0) {
    $index->commit();
    //mark the timestamp of the last document commited
    set_config($name . '_last_run', $record->modified, 'gs');
    mtrace("Processed $norecords records containing $nodocuments documents for " . $iterator->module);
  }
}


