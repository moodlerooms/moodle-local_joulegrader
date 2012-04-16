<?php
defined('MOODLE_INTERNAL') or die('Direct access to this script is forbidden.');
require_once($CFG->dirroot . '/local/joulegrader/lib/comment/class.php');
require_once($CFG->dirroot . '/local/joulegrader/form/comment.php');

/**
 * @author Sam Chaffee
 * @package local/joulegrader
 */
class local_joulegrader_lib_comment_loop implements renderable {

    /**
     * @var local_joulegrader_lib_gradingarea_abstract - instance
     */
    protected $gradingarea;

    /**
     * @var array - comments that belong to this comment loop
     */
    protected $comments;

    /**
     * @var moodleform - comment form
     */
    protected $mform;

    /**
     * @param $gradingarea - local_joulegrader_lib_gradingarea_abstract
     */
    public function __construct($gradingarea) {
        $this->gradingarea = $gradingarea;
    }

    /**
     * @return array - the comments for this loop
     */
    public function get_comments() {
        if (is_null($this->comments)) {
            $this->load_comments();
        }
        return $this->comments;
    }

    /**
     * @return moodleform - comment form
     */
    public function get_mform() {
        if (is_null($this->mform)) {
            $this->load_mform();
        }
        return $this->mform;
    }

    /**
     * @return bool
     */
    public function user_can_comment() {
        global $USER;

        //initialize
        $cancomment = false;

        //context to use in has_capability call
        $context = $this->gradingarea->get_gradingmanager()->get_context();

        //check for teacher cap first
        if (has_capability($this->gradingarea->get_teachercapability(), $context)) {
            $cancomment = true;
        } else if (has_capability($this->gradingarea->get_studentcapability(), $context) && $USER->id == $this->gradingarea->get_guserid()) {
            $cancomment = true;
        }

        return $cancomment;
    }

    /**
     * @param stdClass $commentdata - data from submitted comment form
     * @return local_joulegrader_lib_comment_class
     */
    public function add_comment($commentdata) {
        global $USER, $COURSE;

        //create a new record
        $commentrecord = new stdClass;
        $commentrecord->content = $commentdata->comment['text'];
        $commentrecord->gareaid = $this->gradingarea->get_areaid();
        $commentrecord->guserid = $this->gradingarea->get_guserid();
        $commentrecord->commenterid = $USER->id;
        $commentrecord->timecreated = time();

        //instantiate a comment object
        $comment = new local_joulegrader_lib_comment_class($commentrecord);

        //save the new comment
        $comment->save();

        //file area
        $itemid = $commentdata->comment['itemid'];
        $context = $this->gradingarea->get_gradingmanager()->get_context();
        $content = file_save_draft_area_files($itemid, $context->id, 'local_joulegrader', 'comment', $comment->get_id(), null, $comment->get_content());

        // filter content for kaltura embed
        $content = $this->filter_kaltura_video($content);

        $comment->set_content($content);
        $comment->save();

        // set the context
        $comment->set_context($context);

        return $comment;
    }

    /**
     * Loads the comments for this loop
     *
     * @return void
     */
    protected function load_comments() {
        global $DB;

        //initialize
        $this->comments = array();

        $gareaid = $this->gradingarea->get_areaid();
        $guserid = $this->gradingarea->get_guserid();

        $context = $this->gradingarea->get_gradingmanager()->get_context();

        //try to get the comments for the area and user
        if ($comments = $DB->get_records('local_joulegrader_comments', array('gareaid' => $gareaid, 'guserid' => $guserid), 'timecreated ASC')) {
            //iterate through comments and instantiate local_joulegrader_lib_comment_class objects
            foreach ($comments as $comment) {
                $commentobject = new local_joulegrader_lib_comment_class($comment);
                $commentobject->set_context($context);
                $this->comments[] = $commentobject;
            }
        }
    }

    /**
     * Loads the mform for adding comments to this loop
     *
     * @return void
     */
    protected function load_mform() {
        global $COURSE;

        //get info necessary for form action url
        $gareaid = $this->gradingarea->get_areaid();
        $guserid = $this->gradingarea->get_guserid();

        //build the form action url
        $urlparams = array('courseid' => $COURSE->id, 'action' => 'addcomment', 'garea' => $gareaid, 'guser' => $guserid);
        $mformurl = new moodle_url('/local/joulegrader/view.php', $urlparams);

        //instantiate the form
        $this->mform = new local_joulegrader_form_comment($mformurl);
    }

    /**
     * Helper method to make kaltura video smaller
     *
     * @param string $content
     * @return string - filtered comment content
     */
    protected function filter_kaltura_video($content) {
        // See if there is a kaltura_player, if not return the content
        if (strpos($content, 'kaltura_player') === false) {
            return $content;
        }

        // Load up in a dom document
        $doc = DOMDocument::loadHTML($content);

        $changes = false;
        foreach ($doc->getElementsByTagName('object') as $objecttag) {
            $objid = $objecttag->getAttribute('id');
            if (strpos($objid, 'kaltura_player') !== false) {
                // set the width and height
                $objecttag->setAttribute('width', '200px');
                $objecttag->setAttribute('height', '166px');

                $changes = true;
                break;
            }
        }

        if ($changes) {
            // only change $content if the attributes were changed above
            $content = preg_replace('/^<!DOCTYPE.+?>/', '', str_replace( array('<html>', '</html>', '<body>', '</body>'), array('', '', '', ''), $doc->saveHTML()));
        }

        return $content;
    }
}