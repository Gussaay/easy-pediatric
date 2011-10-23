<?php

/**
 * Description of gs_lucene_pdf
 *
 * @author Tomasz Muras
 */
class gs_lucene_pdf extends Zend_Search_Lucene_Document_Pptx {

  /** @var $doc gs_document */
  public $doc;
  public static $enabled = NULL;
  public static $path = NULL;

  public function __construct(gs_document $doc) {
    $this->doc = $doc;
    gs_fill_lucene_fields($this);
    $this->addField(Zend_Search_Lucene_Field::UnStored('content', $this->convert($doc->get_filepath())));
  }

  /**
   * Convert PDF document to text
   * @param string $path 
   */
  public function convert($file) {
    $file = escapeshellarg($file);
    $cmd = escapeshellcmd(self::$path) .' '. $file .' -';
    $ret = NULL;
    $output = NULL;
    $result = exec($cmd, $output, $ret);
    if($ret != 0) {
      mtrace("PDF text extraction failed, command (return status): $cmd ($ret)");
      return '';
    }
    return implode(' ', $output);
  }

  /**
   * Check if pdf conversion is enabled and possible.
   */
  public static function is_enabled() {
    global $CFG;
    if (self::$enabled !== NULL) {
      return self::$enabled;
    }

    $path = '/usr/bin/pdftotext';
    if (isset($CFG->gs_pdftotext_path)) {
      if ($CFG->gs_pdftotext_path[0] == '/' || $CFG->gs_pdftotext_path[0] == '\\' || $CFG->gs_pdftotext_path[0] == ':') {
        $path = $CFG->dirroot . DIRECTORY_SEPARATOR . $CFG->gs_pdftotext_path;
      }
      else {
        $path = $CFG->gs_pdftotext_path;
      }
    }
    if (!file_exists($path)) {
      mtrace("Disabling pdftotext conversion because '$path' does not exist.");
      self::$enabled = false;
      return false;
    }
    if (!is_executable($path)) {
      mtrace("Disabling pdftotext conversion because '$path' is not executable.");
      self::$enabled = false;
      return false;
    }
    $output = array();
    $retval = null;
    exec("$path -v", $output, $retval);
    if ($retval) {
      mtrace("Disabling pdftotext conversion because return value for '$path -v' is non-zero ($retval).");
      self::$enabled = false;
      return false;
    }
    self::$path = $path;
    self::$enabled = true;
    mtrace('pdftotext enabled, using \'' . self::$path ."'");
    return true;
  }

}
