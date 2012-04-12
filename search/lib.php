<?php

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php

require_once('Zend/Search/Lucene.php');
require_once($CFG->dirroot . '/search/document/search_document.php');
require_once($CFG->dirroot . '/search/document/search_lucene_html.php');
require_once($CFG->dirroot . '/search/document/search_lucene_pdf.php');
require_once($CFG->dirroot . '/search/document/search_lucene_pptx.php');
require_once($CFG->dirroot . '/search/mod/forum.php');
require_once($CFG->dirroot . '/search/mod/glossary.php');
require_once($CFG->dirroot . '/search/mod/label.php');
require_once($CFG->dirroot . '/search/mod/resource.php');
require_once($CFG->dirroot . '/lib/accesslib.php');


define('SEARCH_INDEX_PATH', $CFG->dataroot . '/search');
define('SEARCH_TYPE_TEXT', 0);
define('SEARCH_TYPE_FILE', 1);
define('SEARCH_TYPE_HTML', 2);

define('SEARCH_ACCESS_DENIED', 0);
define('SEARCH_ACCESS_GRANTED', 1);
define('SEARCH_ACCESS_DELETED', 2);

define('SEARCH_MAX_BUFFERED_DOCS', 100);
define('SEARCH_MAX_MERGE_FACTOR', 10);
define('SEARCH_MAX_MERGE_DOCS', 10000);

class search_exception extends moodle_exception {

    function __construct($hint, $debuginfo=null) {
        parent::__construct($hint, 'debug', '', $hint, $debuginfo);
    }

}

function search_fill_lucene_fields($document) {
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
    if ($document->doc->get_type() !== SEARCH_TYPE_FILE) {
        $document->addField(Zend_Search_Lucene_Field::UnStored('content', $document->doc->get_content()));
    }
}

function search_document_for_mime(search_document $doc) {
    switch ($doc->get_mime()) {
        case 'application/pdf':
            if (search_lucene_pdf::is_enabled()) {
                return new search_lucene_pdf($doc);
            } else {
                return null;
            }
        case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
            return new search_lucene_pptx($doc);
        default:
            mtrace("Mime type '" . $doc->get_mime() . " not supported", '<br />');
            return null;
    }
}

function search_get_iterators() {
    global $DB;
    $mods = $DB->get_records('modules', null, 'name', 'id,name');
    foreach ($mods as $k => $mod) {
        if (!plugin_supports('mod', $mod->name, FEATURE_GLOBAL_SEARCH)) {
            unset($mods[$k]);
        }
    }
    $functions = array();
    foreach ($mods as $mod) {
        if (!function_exists($mod->name . '_search_iterator')) {
            throw new coding_exception('Module declared FEATURE_GLOBAL_SEARCH but function \'' . $mod->name . '_search_iterator' . '\' is missing.');
        }
        if (!function_exists($mod->name . '_search_get_documents')) {
            throw new coding_exception('Module declared FEATURE_GLOBAL_SEARCH but function \'' . $mod->name . '_search_get_documents' . '\' is missing.');
        }
        if (!function_exists($mod->name . '_search_access')) {
            throw new coding_exception('Module declared FEATURE_GLOBAL_SEARCH but function \'' . $mod->name . '_search_access' . '\' is missing.');
        }
        $functions[$mod->name] = new stdClass();
        $functions[$mod->name]->iterator = $mod->name . '_search_iterator';
        $functions[$mod->name]->documents = $mod->name . '_search_get_documents';
        $functions[$mod->name]->access = $mod->name . '_search_access';
        $functions[$mod->name]->module = $mod->name;
    }

    // $blocks = $DB->get_records('block', null, 'name', 'id,name');
    return $functions;
}

/**
 * Reset all search data
 */
function search_reset_index() {
    global $DB;

    //delete index
    if ($handle = opendir(SEARCH_INDEX_PATH)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                unlink(SEARCH_INDEX_PATH . DIRECTORY_SEPARATOR . $file);
            }
        }
        closedir($handle);
    }

    //delete database information
    $DB->delete_records('config_plugins', array('plugin' => 'search'));
}

/**
 * Merge separate index segments into one.
 */
function search_optimize_index() {
    set_time_limit(576000);
    raise_memory_limit('9000M');
    $index = search_get_index();
    $index->setMaxMergeDocs(1000);
    $index->optimize();
}

/**
 * Return configuration and stats for GS.
 */
function search_get_config($mods) {
    $all = get_config('search');
    $configvars = array('indexingstart', 'indexingend', 'lastrun', 'docsignored', 'docsprocessed', 'recordsprocessed');

    $ret = array();
    foreach ($mods as $mod) {
        $ret[$mod] = new stdClass();
        foreach ($configvars as $var) {
            $method = "{$mod}_$var";
            if (empty($all->$method)) {
                $ret[$mod]->$var = 0;
            } else {
                $ret[$mod]->$var = $all->$method;
            }
        }
        if (empty($ret[$mod]->lastrun)) {
            $ret[$mod]->lastrun = "never";
        } else {
            $ret[$mod]->lastrun = userdate($ret[$mod]->lastrun);
        }
        if (empty($ret[$mod]->timetaken)) {
            $ret[$mod]->timetaken = 0;
        } else {
            $ret[$mod]->timetaken = $ret[$mod]->indexingend - $ret[$mod]->indexingstart;
        }
    }
    return $ret;
}

/**
 * Check if the index file is OK.
 */
function search_index_check() {
    if (!file_exists(SEARCH_INDEX_PATH)) {
        if (!mkdir(SEARCH_INDEX_PATH, $CFG->directorypermissions)) {
            error("Error creating data directory at: '" . SEARCH_INDEX_PATH . "'.");
        }
    }
    //allow for symlinking
    if (!is_dir(SEARCH_INDEX_PATH)) {
        error("Index path '" . SEARCH_INDEX_PATH . "' is not a directory");
    }
    $index = search_get_index();
}

/**
 * Get Lucene Index
 * @return Zend_Search_Lucene 
 */
function search_get_index() {
//  static $index;
//  if ($index === null) {
    Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
    try {
        $index = new Zend_Search_Lucene(SEARCH_INDEX_PATH, false);
    } catch (Zend_Search_Lucene_Exception $ex) {
        $index = new Zend_Search_Lucene(SEARCH_INDEX_PATH, true);
    }
    //}

    return $index;
}

/**
 * Index all documents.
 */
function search_index($initial=true) {
    mtrace("Memory usage:" . memory_get_usage(), '<br/>');
    set_time_limit(576000);
    $index = search_get_index();

    $index->setMaxBufferedDocs(SEARCH_MAX_BUFFERED_DOCS);
    $index->setMergeFactor(SEARCH_MAX_MERGE_FACTOR);
    $index->setMaxMergeDocs(SEARCH_MAX_MERGE_DOCS);
    
    $iterators = search_get_iterators();
mtrace("Memory usage:" . memory_get_usage(), '<br/>');
    foreach ($iterators as $name => $iterator) {
        mtrace('Processing module ' . $iterator->module, '<br />');
        $indexingstart = time();
        $iterfunction = $iterator->iterator;
        $getdocsfunction = $iterator->documents;
        //get the timestamp of the last commited run for the plugin
        $lastrun = get_config('search', $name . '_lastrun');
        $recordset = $iterfunction($lastrun);
        $norecords = 0;
        $nodocuments = 0;
        $nodocumentsignored = 0;
        foreach ($recordset as $record) {
            mtrace("$name,{$record->id}", '<br/>');
            mtrace("Memory usage:" . memory_get_usage(), '<br/>');
            ++$norecords;
            //var_dump($record);
            $timestart = microtime(true);
            $documents = $getdocsfunction($record->id);
            //find out if it's not an update - delete whole document set if so
            search_remove_set($name, $record->id);
            foreach ($documents as $document) {
                switch ($document->get_type()) {
                    case SEARCH_TYPE_HTML:
                        mtrace("Memory usage: (new html doc)" . memory_get_usage(), '<br/>');
                        $lucenedoc = new search_lucene_html($document);
                        mtrace("Memory usage: (new html doc created)" . memory_get_usage(), '<br/>');
                        break;
                    case SEARCH_TYPE_FILE:
                        $lucenedoc = search_document_for_mime($document);
                        break;
                    default:
                        throw new search_exception("Wrong document type");
                }
                if ($lucenedoc) {
                    $index->addDocument($lucenedoc);
                    mtrace("Memory usage: (doc added)" . memory_get_usage(), '<br/>');
                    ++$nodocuments;
                } else {
                    ++$nodocumentsignored;
                }
            }
            $timetaken = microtime(true) - $timestart;
            mtrace("Time $norecords: $timetaken", '<br/>');
        }
        $recordset->close();
        if ($norecords > 0) {
            $index->commit();
            $indexingend = time();
            //mark the timestamp of the last document commited
            set_config($name . '_indexingstart', $indexingstart, 'search');
            set_config($name . '_indexingend', $indexingend, 'search');
            set_config($name . '_lastrun', $record->modified, 'search');
            set_config($name . '_docsignored', $nodocumentsignored, 'search');
            set_config($name . '_docsprocessed', $nodocuments, 'search');
            set_config($name . '_recordsprocessed', $norecords, 'search');
            mtrace("Processed $norecords records containing $nodocuments documents for " . $iterator->module);
        }
    }
}

/**
 * Remove all documents from index for $module and set $id.
 * @param type $module
 * @param type $id 
 */
function search_remove_set($module, $id) {
    mtrace("search_remove_set($module, $id)", '<br/>');
    $index = search_get_index();
    $q = "fullsetid:\"$module:$id\"";

    $term = new Zend_Search_Lucene_Index_Term("$module:$id", "fullsetid");
    $ids = $index->termDocs($term);
    //mtrace($q, '<br/>');
    foreach ($ids as $id) {
        mtrace("Removing: $id", '<br/>');
        mtrace("Memory usage (before delete):" . memory_get_usage(), '<br/>');
        $index->delete($id);
        mtrace("Memory usage (after delete):" . memory_get_usage(), '<br/>');
    }
}

function search_can_access_course($courseid) {
    static $cache = array();

    if (!isset($cache[$courseid])) {
        $cache[$courseid] = can_access_course($course);
    }

    return $cache[$courseid];
}

/**
 * Check standard security for hits that do have $cm.
 * @param type $cm 
 */
function search_access($cm) {
    
}
