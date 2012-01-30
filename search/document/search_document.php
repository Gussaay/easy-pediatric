<?php


/**
 * Common class that stores all the data passed
 * to specific search_lucene_*
 * 
 * @author Tomasz Muras
 */
class search_document {

  /**
   * Type as per SEARCH_TYPE_*
   * @var int
   */
  private $type;

  /**
   * Link to a context of the document (e.g. forum post)
   * @var string
   */
  private $contextlink;

  /**
   * Direct link to the document (e.g. document attached to a post)
   * @var string
   */
  private $directlink;

  /**
   * Title of the document
   * @var string
   */
  private $title;

  /**
   * The main content.
   * @var string
   */
  private $content;

  /**
   * Full author's name.
   * @var string
   */
  private $author;

  /**
   * Module that has created original document.
   * @var string
   */
  private $module;

  /**
   * Id that identifies a set of documents for a given module.
   * The id/module pair may point to several documents - e.g. forum post and attachments.
   * Usually it's module id.
   * @var integer
   */
  private $id;
  
  /**
   * Course module id, if document set has one.
   * 
   * @var integer
   */
  private $cm;

  /**
   * Author ID.
   * @var integer
   */
  private $userid;

  /**
   * User (author) object.
   * @var stdObj
   */
  private $user;
  
  /**
   * Timestamp when document was created.
   * @var string
   */
  private $created;

  /**
   * Timestamp of the last modification.
   * @var string
   */
  private $modified;

  /**
   * The course ID where the document is located.
   * @var integer
   */
  private $courseid;

  /**
   * Path to the external file with the document;
   * @var string
   */
  private $filepath;
  
  /**
   * Mime type of the external file.
   * @var string
   */
  private $mime;
  
  public function get_type() {
    return $this->type;
  }

  public function set_type($type) {
    $this->type = $type;
  }

  public function get_contextlink() {
    return $this->contextlink;
  }

  public function set_contextlink($contextlink) {
    $this->contextlink = $contextlink;
    //by default direct & context links are the same
    if(!$this->directlink) {
      $this->directlink = $contextlink;
    }
  }

  public function get_directlink() {
    return $this->directlink;
  }

  public function set_directlink($directlink) {
    $this->directlink = $directlink;
    //by default direct & context links are the same
    if(!$this->contextlink) {
      $this->contextlink = $directlink;
    }
  }

  public function get_title() {
    return $this->title;
  }

  public function set_title($title) {
    $this->title = $title;
  }

  public function get_content() {
    return $this->content;
  }

  public function set_content($content) {
    $this->content = $content;
  }

  public function get_author() {
    return $this->author;
  }

  public function get_module() {
    return $this->module;
  }

  public function set_module($module) {
    $this->module = $module;
  }

  public function get_id() {
    return $this->id;
  }

  public function set_id($id) {
    $this->id = $id;
  }

  public function get_user() {
    return $this->user;
  }

  public function set_user(stdClass $user) {
    $this->userid = $user->id;
    //also set full author name
    $this->author =  fullname($user);
  }

  public function get_created() {
    return $this->created;
  }

  public function set_created($created) {
    $this->created = $created;
  }

  public function get_modified() {
    return $this->modified;
  }

  public function set_modified($modified) {
    $this->modified = $modified;
  }

  public function get_courseid() {
    return $this->courseid;
  }

  public function set_courseid($courseid) {
    $this->courseid = $courseid;
  }
  public function get_filepath() {
    return $this->filepath;
  }

  public function set_filepath($filepath) {
    if(!is_readable($filepath)) {
      throw new search_exception("Can't read file: '$filepath'");
    }
    $this->filepath = $filepath;
  }

  public function get_mime() {
    return $this->mime;
  }

  public function set_mime($mime) {
    $this->mime = $mime;
  }
  
}
