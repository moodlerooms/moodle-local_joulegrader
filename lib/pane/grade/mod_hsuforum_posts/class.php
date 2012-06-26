<?php
defined('MOODLE_INTERNAL') or die('Direct access to this script is forbidden.');
require_once($CFG->dirroot . '/local/joulegrader/lib/pane/grade/abstract.php');
require_once($CFG->dirroot . '/local/joulegrader/form/mod_hsuforum_posts_grade.php');

/**
 * joule Grader mod_hsuforum_posts grade pane class
 *
 * @author Sam Chaffee
 * @package local/joulegrader
 */
class local_joulegrader_lib_pane_grade_mod_hsuforum_posts_class extends local_joulegrader_lib_pane_grade_abstract {

    protected $cm;
    /**
     * @var context_module
     */
    protected $context;
    protected $forum;

    /**
     * @var gradingform_controller
     */
    protected $controller;

    /**
     * @var gradingform_instance
     */
    protected $gradinginstance;

    protected $teachercap;

    /**
     * Do some initialization
     */
    public function init() {
        global $DB, $USER;

        if (isset($this->mform)) {
            return;
        }

        $this->context = $this->gradingarea->get_gradingmanager()->get_context();
        $this->cm      = get_coursemodule_from_id('hsuforum', $this->context->instanceid, 0, false, MUST_EXIST);
        $this->forum   = $DB->get_record('hsuforum', array('id' => $this->cm->instance), '*', MUST_EXIST);

        $this->gradinginfo = grade_get_grades($this->cm->course, 'mod', 'hsuforum', $this->forum->id, array($this->gradingarea->get_guserid()));

        $gradingdisabled = $this->gradinginfo->items[0]->locked;

        if (($gradingmethod = $this->gradingarea->get_active_gradingmethod()) && in_array($gradingmethod, self::get_supportedplugins())) {
            $this->controller = $this->gradingarea->get_gradingmanager()->get_controller($gradingmethod);
            if ($this->controller->is_form_available()) {
                if ($gradingdisabled) {
                    $this->gradinginstance = $this->controller->get_current_instance($USER->id, $this->gradingarea->get_guserid());
                } else {
                    $instanceid = optional_param('gradinginstanceid', 0, PARAM_INT);
                    $this->gradinginstance = $this->controller->get_or_create_instance($instanceid, $USER->id, $this->gradingarea->get_guserid());
                }

                $currentinstance = $this->gradinginstance->get_current_instance();
                $this->needsupdate = false;
                if ($currentinstance && $currentinstance->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
                    $this->needsupdate = true;
                }
            } else {
                $this->advancedgradingerror = $this->controller->form_unavailable_notification();
            }
        }


        $this->teachercap = has_capability($this->gradingarea->get_teachercapability(), $this->context);
        if ($this->teachercap) {
            //set up the form
            $mformdata = new stdClass();
            $mformdata->cm = $this->cm;
            $mformdata->forum = $this->forum;
            $mformdata->grade = $this->gradinginfo->items[0]->grades[$this->gradingarea->get_guserid()]->grade;
            $mformdata->gradeoverridden = $this->gradinginfo->items[0]->grades[$this->gradingarea->get_guserid()]->overridden;
            $mformdata->gradingdisabled = $gradingdisabled;

            //For advanced grading methods
            if (!empty($this->gradinginstance)) {
                $mformdata->gradinginstance = $this->gradinginstance;
            }

            $posturl = new moodle_url('/local/joulegrader/view.php', array('courseid' => $this->cm->course
                    , 'garea' => $this->gradingarea->get_areaid(), 'guser' => $this->gradingarea->get_guserid(), 'action' => 'process'));

            if ($needsgrading = optional_param('needsgrading', 0, PARAM_BOOL)) {
                $posturl->param('needsgrading', 1);
            }

            $mformdata->nextuser = $this->gradingarea->get_nextuserid();

            //create the mform
            $this->mform = new local_joulegrader_form_mod_hsuforum_posts_grade($posturl, $mformdata);
        }
    }

    /**
     * @return mixed
     */
    public function get_panehtml() {
        //initialize
        $html = '';

        //if this is an ungraded assignment just return a no grading info box
        if ($this->forum->scale == 0) {
            //no grade for this assignment
            $html = html_writer::tag('div', get_string('notgraded', 'local_joulegrader'), array('class' => 'local_joulegrader_notgraded'));
        } else {
            //there is a grade for this assignment
            //check to see if advanced grading is being used
            if (empty($this->controller) || (!empty($this->controller) && !$this->controller->is_form_available())) {
                //advanced grading not used
                //check for cap
                if (!empty($this->teachercap)) {
                    //get the form html for the teacher
                    $mrhelper = new mr_helper();
                    $html = $mrhelper->buffer(array($this->mform, 'display'));
                    $html = html_writer::tag('div', $html, array('class' => 'local_joulegrader_simplegrading'));

                    //advanced grading error warning
                    if (!empty($this->controller) && !$this->controller->is_form_available()) {
                        $html .= $this->advancedgradingerror;
                    }
                } else {
                    //start the html
                    $grade = -1;
                    if (!empty($this->gradinginfo->items[0]) and !empty($this->gradinginfo->items[0]->grades[$this->gradingarea->get_guserid()])
                            and !is_null($this->gradinginfo->items[0]->grades[$this->gradingarea->get_guserid()]->grade)) {
                        $grade = $this->gradinginfo->items[0]->grades[$this->gradingarea->get_guserid()]->str_grade;
                    }

                    $html = html_writer::start_tag('div', array('id' => 'local-joulegrader-gradepane-grade'));
                    if ($this->forum->scale < 0) {
                        $html .= get_string('grade') . ': ';
                        if ($grade != -1) {
                            $html .= $grade;
                        } else {
                            $html .= get_string('nograde');
                        }
                    } else {
                        //if grade isn't set yet then, make is blank, instead of -1
                        if ($grade == -1) {
                            $grade = ' - ';
                        }
                        $html .= get_string('gradeoutof', 'local_joulegrader', $this->forum->scale) . ': ';
                        $html .= $grade;
                    }
                    $html .= html_writer::end_tag('div');

                    $overridden = $this->gradinginfo->items[0]->grades[$this->gradingarea->get_guserid()]->overridden;
                    if (!empty($overridden)) {
                        $html .= html_writer::start_tag('div');
                        $html .= get_string('gradeoverriddenstudent', 'local_joulegrader', $this->gradinginfo->items[0]->grades[$this->gradingarea->get_guserid()]->str_grade);
                        $html .= html_writer::end_tag('div');
                    }
                }
            } else if ($this->controller->is_form_available()) {
                //generate preview based on type of advanced grading plugin (rubric or checklist)
                $gradingmethod = $this->gradingarea->get_active_gradingmethod();

                // shouldn't have this happen, but just in case
                if (!in_array($gradingmethod, self::get_supportedplugins())) {
                    return '';
                }

                $html = '';
                if (!empty($this->teachercap) || !$this->needsupdate) {
                    $controller = $this->controller;
                    $options = $controller->get_options();
                    $grade = $this->gradinginfo->items[0]->grades[$this->gradingarea->get_guserid()];
                    
                    if (!empty($options['alwaysshowdefinition']) || !empty($this->teachercap) || (!empty($grade->grade) && empty($grade->hidden))) {
                        //need to generate the condensed rubric html
                        //first a "view" button
                        //If the user has the ability to see the rubric!
                        $buttonatts = array('type' => 'button', 'id' => 'local-joulegrader-preview-button');
                        $viewbutton = html_writer::tag('button', get_string('view' . $gradingmethod, 'local_joulegrader'), $buttonatts);

                        $html .= html_writer::tag('div', $viewbutton, array('id' => 'local-joulegrader-viewpreview-button-con'));

                        // needsupdate?
                        if ($this->needsupdate) {
                            $html .= html_writer::tag('div', get_string('needregrademessage', 'gradingform_' . $gradingmethod), array('class' => "gradingform_$gradingmethod-regrade"));
                        }

                        //gradingmethod preview
                        $previewmethod = 'get_' . $gradingmethod . '_preview';
                        $html .= $this->$previewmethod();
                    }
                }

                if ((!$grade->grade === false) && empty($grade->hidden)) {
                    $gradeval = $grade->str_long_grade;
                } else {
                    $gradeval = '-';
                }
                $html .= '<div class="grade">'. get_string("grade").': '.$gradeval. '</div>';
            }
        }

        return $html;
    }

    /**
     * @return string - html for a modal
     */
    public function get_modal_html() {
        global $PAGE;

        $html = '';

        if (empty($this->controller) || !$this->controller->is_form_available()) {
            return $html;
        }

        if (empty($this->teachercap) && $this->needsupdate) {
            return $html;
        }

        //check for capability
        if (!empty($this->teachercap)) {
            //get the form and render it via buffer helper
            $mrhelper = new mr_helper();
            $html = $mrhelper->buffer(array($this->mform, 'display'));
        } else {
            //this is for a student
            $gradingmethod = $this->gradingarea->get_active_gradingmethod();

            //get grading info
            $item = $this->gradinginfo->items[0];
            $grade = $item->grades[$this->gradingarea->get_guserid()];

            if ((!$grade->grade === false) && empty($grade->hidden)) {
                $gradestr = '<div class="grade">'. get_string("grade").': '.$grade->str_long_grade. '</div>';
            } else {
                $gradestr = '';
            }
            $controller = $this->controller;

            if (!$controller->get_active_instances($this->gradingarea->get_guserid())) {
                $renderer = $controller->get_renderer($PAGE);
                $options = $controller->get_options();
                switch ($gradingmethod) {
                    case 'rubric':
                        $criteria = $controller->get_definition()->rubric_criteria;
                        $html = $renderer->display_rubric($criteria, $options, $controller::DISPLAY_VIEW, 'rubric');
                        break;
                    case 'checklist':
                        $groups = $controller->get_definition()->checklist_groups;
                        $html = $renderer->display_checklist($groups, $options, $controller::DISPLAY_VIEW, 'checklist');
                        break;
                }
            } else {
                $controller->set_grade_range(make_grades_menu($this->forum->scale));
                $html = $controller->render_grade($PAGE, $this->gradingarea->get_guserid(), $item, $gradestr, false);
            }
        }

        return $html;
    }

    /**
     * Process the grade data
     * @param mr_html_notify $notify
     */
    public function process($notify) {
        //get the moodleform
        $mform = $this->mform;

        //set up a redirect url
        $redirecturl = new moodle_url('/local/joulegrader/view.php', array('courseid' => $this->cm->course
                , 'garea' => $this->get_gradingarea()->get_areaid(), 'guser' => $this->get_gradingarea()->get_guserid()));

        //get the data from the form
        if ($data = $mform->get_data()) {

            if ($data->forum != $this->forum->id) {
                //throw an exception, could be some funny business going on here
                throw new moodle_exception('assignmentnotmatched', 'local_joulegrader');
            }

            if (isset($data->gradinginstanceid)) {
                //using advanced grading
                $gradinginstance = $this->gradinginstance;
                $grade = $gradinginstance->submit_and_get_grade($data->grade, $this->gradingarea->get_guserid());
            } else if ($this->forum->scale < 0) {
                //scale grade
                $grade = clean_param($data->grade, PARAM_INT);
            } else {
                //just using regular grading
                $lettergrades = grade_get_letters(context_course::instance($this->cm->course));
                $grade = $data->grade;

                $touppergrade = textlib::strtoupper($grade);
                $toupperlettergrades = array_map('textlib::strtoupper', $lettergrades);
                if (in_array($touppergrade, $toupperlettergrades)) {
                    //submitting lettergrade, find percent grade
                    $percentvalue = 0;
                    foreach ($toupperlettergrades as $value => $letter) {
                        if ($touppergrade == $letter) {
                            $percentvalue = $value;
                            break;
                        }
                    }

                    //transform to an integer within the range of the assignment
                    $grade = (int) ($this->forum->scale * ($percentvalue / 100));

                } else if (strpos($grade, '%') !== false) {
                    //trying to submit percentage
                    $percentgrade = trim(strstr($grade, '%', true));
                    $percentgrade = clean_param($percentgrade, PARAM_FLOAT);

                    //transform to an integer within the range of the assignment
                    $grade = (int) ($this->forum->scale * ($percentgrade / 100));

                } else if ($grade === '') {
                    //setting to "No grade"
                    $grade = -1;
                } else {
                    //just a numeric value, clean it as int b/c that's what assignment module accepts
                    $grade = clean_param($grade, PARAM_INT);
                }
            }

            //redirect to next user if set
            if (optional_param('saveandnext', 0, PARAM_BOOL) && !empty($data->nextuser)) {
                $redirecturl->param('guser', $data->nextuser);
            }

            if (optional_param('needsgrading', 0, PARAM_BOOL)) {
                $redirecturl->param('needsgrading', 1);
            }

            //save the grade
            if ($this->save_grade($grade, isset($data->override))) {
                $notify->good('gradesaved');
            }
        }

        redirect($redirecturl);
    }

    /**
     * @return void
     */
    public function require_js() {

    }

    /**
     * @return bool
     */
    public function is_validated() {
        $validated = $this->mform->is_validated();
        return $validated;
    }

    /**
     * Returns whether or not there is a grade yet for the area/user
     *
     * @return boolean
     */
    public function not_graded() {
        if (!empty($this->gradinginfo) && is_null($this->gradinginfo->items[0]->grades[$this->gradingarea->get_guserid()]->grade)) {
            return true;
        }
        return false;
    }

    /**
     * @param $grade
     * @param $override
     *
     * @return bool
     */
    protected function save_grade($grade, $override) {
        $gradeitem = grade_item::fetch(array(
            'courseid'     => $this->cm->course,
            'itemtype'     => 'mod',
            'itemmodule'   => 'hsuforum',
            'iteminstance' => $this->forum->id,
            'itemnumber'   => 0,
        ));

        //if no grade item, create a new one
        if (!empty($gradeitem)) {
            //if grade is -1 in assignment_submissions table, it should be passed as null
            if ($grade == -1) {
                $grade = null;
            }
            return $gradeitem->update_final_grade($this->gradingarea->get_guserid(), $grade, 'local/joulegrader');
        }
        return false;
    }
}