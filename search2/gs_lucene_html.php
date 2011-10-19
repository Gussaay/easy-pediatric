<?php

/**
 * Description of gs_lucene_html
 *
 * @author zabuch
 */
class gs_lucene_html extends Zend_Search_Lucene_Document_Html {

  /** @var $doc gs_document */
  public $doc;

  public function __construct(gs_document $doc) {
    $this->doc = $doc;

    $this->addField(Zend_Search_Lucene_Field::Text('title', $this->doc->get_title()));
    $this->addField(Zend_Search_Lucene_Field::Text('author', $this->doc->get_author()));
    $this->addField(Zend_Search_Lucene_Field::UnStored('content', $this->doc->get_content()));
    $this->addField(Zend_Search_Lucene_Field::Keyword('created', $this->doc->get_created()));
    $this->addField(Zend_Search_Lucene_Field::Keyword('modified', $this->doc->get_modified()));
    $this->addField(Zend_Search_Lucene_Field::Keyword('courseid', $this->doc->get_courseid()));
    $this->addField(Zend_Search_Lucene_Field::Keyword('setid', $this->doc->get_id()));
    $this->addField(Zend_Search_Lucene_Field::Keyword('module', $this->doc->get_module()));
    $this->addField(Zend_Search_Lucene_Field::Keyword('directlink', $this->doc->get_directlink()));
    $this->addField(Zend_Search_Lucene_Field::Keyword('contextlink', $this->doc->get_contextlink()));
  }

}
