<?php

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php

require_once($CFG->dirroot . '/search2/Zend/Search/Lucene.php');
require_once($CFG->dirroot . '/search2/gs_document.php');
require_once($CFG->dirroot . '/search2/gs_lucene_html.php');
require_once($CFG->dirroot . '/search2/gs_lucene_pdf.php');
require_once($CFG->dirroot . '/search2/gs_lucene_pptx.php');
require_once($CFG->dirroot . '/search2/mod/forum.php');
require_once($CFG->dirroot . '/search2/mod/glossary.php');
require_once($CFG->dirroot . '/search2/mod/label.php');
require_once($CFG->dirroot . '/search2/mod/resource.php');

define('GS_INDEX_PATH', $CFG->dataroot . '/search');
define('GS_TYPE_TEXT', 0);
define('GS_TYPE_FILE', 1);
define('GS_TYPE_HTML', 2);

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
      mtrace("Mime type '" . $doc->get_mime() . " not supported",'<br />');
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
function gs_reset() {
  global $DB;
  
  //delete index
  if ($handle = opendir(GS_INDEX_PATH)) {
    while (false !== ($file = readdir($handle))) {
      if ($file != "." && $file != "..") {
        @unlink($file);
      }
    }
    closedir($handle);
  }
  
  //delete database information
  $DB->delete_records('config_plugins', array('plugin'=>'gs'));
}