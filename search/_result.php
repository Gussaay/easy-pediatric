<?php
/* @var $DB mysqli_native_moodle_database */
/* @var $OUTPUT core_renderer */
/* @var $PAGE moodle_page */
?>
<?php $OUTPUT->box_start(); ?>
<b>Title:</b> <?php echo $hit->title ?> <br/>
<b>Author:</b> <?php echo $hit->author ?> <br/>
<b>Created:</b> <?php echo userdate($hit->created) ?> <br/>
<b>Modified:</b> <?php echo userdate($hit->modified) ?> <br/>
<b>Course:</b> <?php echo $hit->courseid ?> <br/>
<b>Document:</b> <?php echo $hit->module . ':' . $hit->setid ?> <br/>
<b>Direct link:</b> <?php echo $hit->directlink ?> <br/>
<b>Context link:</b> <?php echo $hit->contextlink ?> <br/>
<?php $OUTPUT->box_end(); ?>
