<?php

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php

require_once('Zend/Search/Lucene.php');
require_once($CFG->dirroot . '/search/gs_document.php');
require_once($CFG->dirroot . '/search/gs_lucene_html.php');
require_once($CFG->dirroot . '/search/gs_lucene_pdf.php');
require_once($CFG->dirroot . '/search/gs_lucene_pptx.php');
require_once($CFG->dirroot . '/search/mod/forum.php');
require_once($CFG->dirroot . '/search/mod/glossary.php');
require_once($CFG->dirroot . '/search/mod/label.php');
require_once($CFG->dirroot . '/search/mod/resource.php');

define('GS_INDEX_PATH', $CFG->dataroot . '/search');
define('GS_TYPE_TEXT', 0);
define('GS_TYPE_FILE', 1);
define('GS_TYPE_HTML', 2);

define('GS_ACCESS_DENIED', 0);
define('GS_ACCESS_GRANTED', 1);
define('GS_ACCESS_DELETED', 2);

class gs_exception extends moodle_exception {

  function __construct($hint, $debuginfo=null) {
    parent::__construct($hint, 'debug', '', $hint, $debuginfo);
  }

}

function gs_fill_lucene_fields($document) {
  $document->addField(Zend_Search_Lucene_Field::Text('title', $document->doc->get_title()));
  $document->addField(Zend_Search_Lucene_Field::Text('author', $document->doc->get_author()));
  $document->addField(Zend_Search_Lucene_Field::Keyword('created', $document->doc->get_created()));
  $document->addField(Zend_Search_Lucene_Field::Keyword('modified', $document->doc->get_modified()));
  $document->addField(Zend_Search_Lucene_Field::Keyword('courseid', $document->doc->get_courseid()));
  $document->addField(Zend_Search_Lucene_Field::Keyword('setid', $document->doc->get_id()));
  $document->addField(Zend_Search_Lucene_Field::Keyword('module', $document->doc->get_module()));
  $document->addField(Zend_Search_Lucene_Field::Keyword('directlink', $document->doc->get_directlink()));
  $document->addField(Zend_Search_Lucene_Field::Keyword('contextlink', $document->doc->get_contextlink()));
  $document->addField(Zend_Search_Lucene_Field::Keyword('fullsetid',
          $document->doc->get_module() . ':' . $document->doc->get_id()));
  if ($document->doc->get_type() !== GS_TYPE_FILE) {
    $document->addField(Zend_Search_Lucene_Field::UnStored('content', $document->doc->get_content()));
  }
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
      mtrace("Mime type '" . $doc->get_mime() . " not supported", '<br />');
      return null;
  }
}

function gs_get_iterators() {
  global $DB;
  $mods = $DB->get_records('modules', null, 'name', 'id,name');
  foreach ($mods as $k => $mod) {
    if (!plugin_supports('mod', $mod->name, FEATURE_GLOBAL_SEARCH)) {
      unset($mods[$k]);
    }
  }
  $functions = array();
  foreach ($mods as $mod) {
    if (!function_exists($mod->name . '_gs_iterator')) {
      throw new coding_exception('Module declared FEATURE_GLOBAL_SEARCH but function \'' . $mod->name . '_gs_iterator' . '\' is missing.');
    }
    if (!function_exists($mod->name . '_gs_get_documents')) {
      throw new coding_exception('Module declared FEATURE_GLOBAL_SEARCH but function \'' . $mod->name . '_gs_get_documents' . '\' is missing.');
    }
    if (!function_exists($mod->name . '_gs_access')) {
      throw new coding_exception('Module declared FEATURE_GLOBAL_SEARCH but function \'' . $mod->name . '_gs_access' . '\' is missing.');
    }
    $functions[$mod->name] = new stdClass();
    $functions[$mod->name]->iterator = $mod->name . '_gs_iterator';
    $functions[$mod->name]->documents = $mod->name . '_gs_get_documents';
    $functions[$mod->name]->access = $mod->name . '_gs_access';
    $functions[$mod->name]->module = $mod->name;
  }

  // $blocks = $DB->get_records('block', null, 'name', 'id,name');
  return $functions;
}

/**
 * Reset all search data
 */
function gs_reset_index() {
  global $DB;

  //delete index
  if ($handle = opendir(GS_INDEX_PATH)) {
    while (false !== ($file = readdir($handle))) {
      if ($file != "." && $file != "..") {
        unlink(GS_INDEX_PATH . DIRECTORY_SEPARATOR . $file);
      }
    }
    closedir($handle);
  }

  //delete database information
  $DB->delete_records('config_plugins', array('plugin' => 'gs'));
}

/**
 * Merge separate index segments into one.
 */
function gs_optimize_index() {
  $index = gs_get_index();
  $index->optimize();
}

/**
 * Return configuration and stats for GS.
 */
function gs_get_config($mods) {
  $all = get_config('gs');
  $configvars = array('indexingstart', 'indexingend', 'lastrun', 'docsignored', 'docsprocessed', 'recordsprocessed');

  $ret = array();
  foreach ($mods as $mod) {
    $ret[$mod] = new stdClass();
    foreach ($configvars as $var) {
      $method = "{$mod}_$var";
      if (empty($all->$method)) {
        $ret[$mod]->$var = 0;
      }
      else {
        $ret[$mod]->$var = $all->$method;
      }
    }
    if (empty($ret[$mod]->lastrun)) {
      $ret[$mod]->lastrun = "never";
    }
    else {
      $ret[$mod]->lastrun = userdate($ret[$mod]->lastrun);
    }
    if (empty($ret[$mod]->timetaken)) {
      $ret[$mod]->timetaken = 0;
    }
    else {
      $ret[$mod]->timetaken = $ret[$mod]->indexingend - $ret[$mod]->indexingstart;
    }
  }
  return $ret;
}

/**
 * Check if the index file is OK.
 */
function gs_index_check() {
  if (!file_exists(GS_INDEX_PATH)) {
    if (!mkdir(GS_INDEX_PATH, $CFG->directorypermissions)) {
      error("Error creating data directory at: '" . GS_INDEX_PATH . "'.");
    }
  }
  //allow for symlinking
  if (!is_dir(GS_INDEX_PATH)) {
    error("Index path '" . GS_INDEX_PATH . "' is not a directory");
  }
  $index = gs_get_index();
}

/**
 * Get Lucene Index
 * @return Zend_Search_Lucene 
 */
function gs_get_index() {
//  static $index;
//  if ($index === null) {
  Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
  try {
    $index = new Zend_Search_Lucene(GS_INDEX_PATH, false);
  } catch (Zend_Search_Lucene_Exception $ex) {
    $index = new Zend_Search_Lucene(GS_INDEX_PATH, true);
  }
  //}

  return $index;
}

/**
 * Index all documents.
 */
function gs_index() {
  $index = gs_get_index();
  $iterators = gs_get_iterators();
  foreach ($iterators as $name => $iterator) {
    mtrace('Processing module ' . $iterator->module, '<br />');
    $indexingstart = time();
    $iterfunction = $iterator->iterator;
    $getdocsfunction = $iterator->documents;
    //get the timestamp of the last commited run for the plugin
    $lastrun = get_config('gs', $name . '_lastrun');
    $recordset = $iterfunction($lastrun);
    $norecords = 0;
    $nodocuments = 0;
    $nodocumentsignored = 0;
    foreach ($recordset as $record) {
      mtrace("$name,{$record->id}", '<br/>');
      ++$norecords;
      //var_dump($record);
      $documents = $getdocsfunction($record->id);
      //find out if it's not an update - delete whole document set if so
      gs_remove_set($name, $record->id);
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
          set_config($name . '_lastrun', $record->modified, 'gs');
        }
      }
    }
    $recordset->close();
    if ($norecords > 0) {
      $index->commit();
      $indexingend = time();
      //mark the timestamp of the last document commited
      set_config($name . '_indexingstart', $indexingstart, 'gs');
      set_config($name . '_indexingend', $indexingend, 'gs');
      set_config($name . '_lastrun', $record->modified, 'gs');
      set_config($name . '_docsignored', $nodocumentsignored, 'gs');
      set_config($name . '_docsprocessed', $nodocuments, 'gs');
      set_config($name . '_recordsprocessed', $norecords, 'gs');
      mtrace("Processed $norecords records containing $nodocuments documents for " . $iterator->module);
    }
  }
}

/**
 * Remove all documents from index for $module and set $id.
 * @param type $module
 * @param type $id 
 */
function gs_remove_set($module, $id) {
  mtrace("gs_remove_set($module, $id)", '<br/>');
  $index = gs_get_index();
  $q = "fullsetid:\"$module:$id\"";
  
  $term = new Zend_Search_Lucene_Index_Term("$module:$id", "fullsetid");
  $ids  = $index->termDocs($term);
  //mtrace($q, '<br/>');
  foreach ($ids as $id) {
    mtrace("Removing: $id", '<br/>');
    $index->delete($id);
  }

}
