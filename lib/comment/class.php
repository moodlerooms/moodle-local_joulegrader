<?php
defined('MOODLE_INTERNAL') or die('Direct access to this script is forbidden.');

/**
 * joule Grader comment class
 *
 * @author Sam Chaffee
 * @package local/joulegrader
 */
class local_joulegrader_lib_comment_class implements renderable {

    /**
     * @var stdClass Comment record from comment api
     */
    public $commmentrecord;

    /**
     * @var context
     */
    protected $context;

    /**
     * @param stdClass|null  $commentrecord
     */
    public function __construct(stdClass $commentrecord = null) {
        $this->commentrecord = $commentrecord;
    }

    /**
     * @return int
     */
    public function get_id() {
        return $this->commentrecord->id;
    }

    /**
     * @return string
     */
    public function get_content() {
        return $this->commentrecord->content;
    }

    /**
     * @return int
     */
    public function get_timecreated() {
        return $this->commentrecord->timecreated;
    }

    /**
     * @return string
     */
    public function get_avatar() {
        return $this->commentrecord->avatar;
    }

    /**
     * @return string
     */
    public function get_user_fullname() {
        return $this->commentrecord->fullname;
    }

    /**
     * @return string
     */
    public function get_user_profileurl() {
        return $this->commentrecord->profileurl;
    }

    /**
     * @return bool
     */
    public function can_delete() {
        return !empty($this->commentrecord->delete);
    }

    /**
     * @return string
     */
    public function get_dateformat() {
        return $this->commentrecord->strftimeformat;
    }

    /**
     * @param $context
     */
    public function set_context($context) {
        $this->context = $context;
    }

    /**
     * @return mixed - context
     */
    public function get_context() {
        return $this->context;
    }
}