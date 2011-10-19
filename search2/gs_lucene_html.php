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
    gs_fill_lucene_fields($this);
  }
}
