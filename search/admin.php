<?php

/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php

require_once('../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/search/lib.php');

admin_externalpage_setup('globalsearch');

class search_admin_form extends moodleform {

  function definition() {

    $mform = & $this->_form;
    $objs = array();
    $objs[] = $mform->createElement('submit', 'optimize', 'Optimize');
    $objs[] = $mform->createElement('submit', 'reset', 'Clear index');
    $objs[] = $mform->createElement('submit', 'index', 'Run indexer');
    $mform->addElement('group', 'controlgroup', '', $objs, ' ', false);
    
    //$mform->addElement('hidden','XDEBUG_PROFILE');
  }

}

//$confirm = optional_param('confirm', 0, PARAM_BOOL);
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

$search_admin_form = new search_admin_form();
if ($data = $search_admin_form->get_data()) {
  if (!empty($data->index)) {
    search_index();
  }
  if (!empty($data->optimize)) {
    search_optimize_index();
  }
  if (!empty($data->reset)) {
    search_reset_index();
  }
}
$index = search_get_index();

$table = new html_table();
$table->id = 'gs-control-panel';
$table->head = array(
  "Name", "Newest document indexed", "Last run <br /> (time, # docs, # records, # ignores)"
);
$table->colclasses = array(
  'displayname', 'lastrun', 'timetaken'
);

$supported = search_get_iterators();
$config = search_get_config(array_keys($supported));

foreach ($supported as $name => $mod) {
  $cname = new html_table_cell($name);
  $clastrun = new html_table_cell($config[$name]->lastrun);
  $ctimetaken = new html_table_cell($config[$name]->indexingend - $config[$name]->indexingstart . ' , ' . $config[$name]->docsprocessed . ' , ' . $config[$name]->recordsprocessed . ' , ' . $config[$name]->docsignored);
  $row = new html_table_row(array($cname, $clastrun, $ctimetaken));
  $table->data[] = $row;
}


echo $OUTPUT->header();
echo $OUTPUT->heading('Index statistics');
echo $OUTPUT->box_start();
echo "<ul>";
echo "<li>Number of documents (incl. deleted): " . $index->count() . "</li>";
echo "<li>Number of documents: " . $index->numDocs() . "</li>";
echo "<li>fieldNames: " . implode(', ', $index->getFieldNames()) . "</li>";
echo "<li>formatVersion: " . $index->getFormatVersion() . "</li>";
echo "<li>maxBufferedDocs: " . $index->getMaxBufferedDocs() . "</li>";
echo "<li>maxMergeDocs: " . $index->getMaxMergeDocs() . "</li>";
echo "<li>resultSetLimit: " . $index->getResultSetLimit() . "</li>";
echo "<li>termsPerQueryLimit: " . $index->getTermsPerQueryLimit() . "</li>";
echo "</ul>";
echo $OUTPUT->box_end();
echo $OUTPUT->heading('Last indexing statistics');
echo $OUTPUT->box_start();
echo html_writer::table($table);
//echo $output->plugins_control_panel($pluginman->get_plugins());
echo $OUTPUT->box_end();
echo $OUTPUT->container_start();
echo $search_admin_form->display();
echo $OUTPUT->container_end();

echo $OUTPUT->footer();

