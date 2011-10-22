<?php

require_once($CFG->dirroot . '/search2/Zend/Search/Lucene.php');
require_once($CFG->dirroot . '/search2/gs_document.php');
require_once($CFG->dirroot . '/search2/gs_lucene_html.php');
require_once($CFG->dirroot . '/search2/gs_lucene_pdf.php');
require_once($CFG->dirroot . '/search2/gs_lucene_pptx.php');
require_once($CFG->dirroot . '/search2/mod/forum.php');


define('GS_INDEX_PATH', $CFG->dataroot . '/search');
define('GS_TYPE_TEXT', 0);
define('GS_TYPE_FILE', 1);
define('GS_TYPE_HTML', 2);

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

class gs_exception extends moodle_exception {

  function __construct($hint, $debuginfo=null) {
    parent::__construct($hint, 'debug', '', $hint, $debuginfo);
  }

}