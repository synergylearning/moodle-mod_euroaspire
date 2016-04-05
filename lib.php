<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Functions to connect with the rest of Moodle
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_euroaspire\local\control\assessmentmanager;

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function euroaspire_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MODEDIT_DEFAULT_COMPLETION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the Euro Aspire assessment into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $euroaspire Submitted data from the form in mod_form.php
 * @param mod_euroaspire_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted euroaspire record
 */
function euroaspire_add_instance(stdClass $euroaspire, mod_euroaspire_mod_form $mform = null) {
    global $DB;
    $euroaspire->timecreated = time();
    $euroaspire->timemodified = $euroaspire->timecreated;
    $euroaspire->id = $DB->insert_record('euroaspire', $euroaspire);
    euroaspire_grade_item_update($euroaspire);
    return $euroaspire->id;
}

/**
 * Updates an instance of the Euro Aspire assessment in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $euroaspire An object from the form in mod_form.php
 * @param mod_euroaspire_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function euroaspire_update_instance(stdClass $euroaspire, mod_euroaspire_mod_form $mform = null) {
    global $DB;
    $euroaspire->timemodified = time();
    $euroaspire->id = $euroaspire->instance;
    $result = $DB->update_record('euroaspire', $euroaspire);
    euroaspire_grade_item_update($euroaspire);
    return $result;
}

/**
 * Removes an instance of the Euro Aspire assessment from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function euroaspire_delete_instance($id) {
    global $DB;
    if (!$euroaspire = $DB->get_record('euroaspire', array('id' => $id))) {
        return false;
    }

    // Delete dependent records.
    $submissionids = $DB->get_fieldset_select('euroaspire_submission', 'id', 'euroaspireid = ?', array($euroaspire->id));
    if ($submissionids) {
        $DB->delete_records_list('euroaspire_submission_item', 'submissionid', $submissionids);
        $DB->delete_records_list('euroaspire_submission_map', 'submissionid', $submissionids);
        $DB->delete_records_list('euroaspire_submission_grade', 'submissionid', $submissionids);
        $DB->delete_records_list('euroaspire_submission', 'id', $submissionids);
    }
    $DB->delete_records('euroaspire_competency', array('euroaspireid' => $euroaspire->id));
    $DB->delete_records('euroaspire_dimension', array('euroaspireid' => $euroaspire->id));

    $DB->delete_records('euroaspire', array('id' => $euroaspire->id));
    euroaspire_grade_item_delete($euroaspire);
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in euroaspire activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function euroaspire_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link euroaspire_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function euroaspire_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@link euroaspire_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function euroaspire_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function euroaspire_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function euroaspire_get_extra_capabilities() {
    return array(); // TODO davo.
}

/* Gradebook API */
/**
 * Is a given scale used by the instance of Euro Aspire assessment?
 *
 * This function returns if a scale is being used by one Euro Aspire assessment
 * if it has support for grading and scales.
 *
 * @param int $euroaspireid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given euroaspire instance
 */
function euroaspire_scale_used($euroaspireid, $scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of Euro Aspire assessment.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any euroaspire instance
 */
function euroaspire_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * Creates or updates grade item for the given Euro Aspire assessment instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $euroaspire instance object with extra cmidnumber and modname property
 * @param bool $reset reset grades in the gradebook
 * @return void
 */
function euroaspire_grade_item_update(stdClass $euroaspire, $reset=false) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    $item = array();
    $item['itemname'] = clean_param($euroaspire->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = 1;
    $item['grademin']  = 0;
    if ($reset) {
        $item['reset'] = true;
    }
    grade_update('mod/euroaspire', $euroaspire->course, 'mod', 'euroaspire', $euroaspire->id, 0, null, $item);
}

/**
 * Delete grade item for given Euro Aspire assessment instance
 *
 * @param stdClass $euroaspire instance object
 * @return grade_item
 */
function euroaspire_grade_item_delete($euroaspire) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    return grade_update('mod/euroaspire', $euroaspire->course, 'mod', 'euroaspire', $euroaspire->id, 0, null,
                        array('deleted' => 1));
}

/**
 * Update Euro Aspire assessment grades in the gradebook
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $euroaspire instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 */
function euroaspire_update_grades(stdClass $euroaspire, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    $param = array('euroaspireid' => $euroaspire->id);
    if ($userid) {
        $param['userid'] = $userid;
    }
    $grades = $DB->get_records('euroaspire_submission', $param, '', 'userid AS id, userid, grade AS rawgrade');
    grade_update('mod/euroaspire', $euroaspire->course, 'mod', 'euroaspire', $euroaspire->id, 0, $grades);
}

/**
 * Update the grade for a specific user
 *
 * @param object|int $euroaspireorid
 * @param int $userid
 * @param int|null $grade
 */
function euroaspire_set_user_grade($euroaspireorid, $userid, $grade) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if (is_object($euroaspireorid)) {
        $euroaspireid = $euroaspireorid->id;
        $courseid = $euroaspireorid->course;
    } else {
        $euroaspireid = $euroaspireorid;
        $courseid = $DB->get_field('euroaspire', 'course', array('id' => $euroaspireorid), MUST_EXIST);
    }

    $grades = array($userid => (object)array('rawgrade' => $grade, 'userid' => $userid));
    grade_update('mod/euroaspire', $courseid, 'mod', 'euroaspire', $euroaspireid, 0, $grades);
}


/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function euroaspire_get_file_areas($course, $cm, $context) {
    return array(); // No browsable areas - only private submissions.
}

/**
 * File browsing support for Euro Aspire assessment file areas
 *
 * @package mod_euroaspire
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function euroaspire_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null; // No browsable areas - only private submissions.
}

/**
 * Serves the files from the Euro Aspire assessment file areas
 *
 * @package mod_euroaspire
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the euroaspire's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function euroaspire_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }
    require_login($course, true, $cm);

    \mod_euroaspire\local\data\submission_item::send_file($course, $cm, $context, $filearea, $args, $forcedownload, $options);

    send_file_not_found();
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all Euro Aspire submissions in the database
 * and clean up any related data.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array
 */
function euroaspire_reset_userdata($data) {
    global $DB;

    $status = array();
    $course = $DB->get_record('course', array('id' => $data->courseid), '*', MUST_EXIST);
    if ($instances = $DB->get_records('euroaspire', array('course' => $course->id), '', 'id')) {
        foreach ($instances as $instance) {
            $cm = get_coursemodule_from_instance('euroaspire', $instance->id, $course->id, false, MUST_EXIST);
            $manager = new \mod_euroaspire\local\control\assessmentmanager($course, $cm, $instance, '');
            $manager->reset_userdata($data);
        }
    }
    if (!empty($data->reset_euroaspire_submissions)) {
        $status[] = array(
            'component' => get_string('modulenameplural', 'mod_euroaspire'),
            'item' => get_string('deleteallsubmissions', 'mod_euroaspire'),
            'error' => false
        );
    }
    return $status;
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid The ID of the course to reset
 */
function euroaspire_reset_gradebook($courseid) {
    global $DB;

    $params = array('moduletype' => 'euroaspire', 'courseid' => $courseid);
    $sql = 'SELECT e.*, cm.idnumber as cmidnumber, a.course as courseid
            FROM {euroaspire} e, {course_modules} cm, {modules} m
            WHERE m.name = :moduletype AND m.id = cm.module AND cm.instance = e.id AND e.course = :courseid';

    if ($assessments = $DB->get_records_sql($sql, $params)) {
        foreach ($assessments as $assessment) {
            euroaspire_grade_item_update($assessment, 'reset');
        }
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the Euro Aspire assessment.
 * @param MoodleQuickForm $mform form passed by reference
 */
function euroaspire_reset_course_form_definition($mform) {
    $mform->addElement('header', 'euroaspireheader', get_string('modulenameplural', 'mod_euroaspire'));
    $name = get_string('deleteallsubmissions', 'mod_euroaspire');
    $mform->addElement('advcheckbox', 'reset_euroaspire_submissions', $name);
}

/**
 * Course reset form defaults.
 * @param  object $course
 * @return array
 */
function euroaspire_reset_course_form_defaults($course) {
    return array('reset_euroaspire_submissions' => 1);
}

/**
 * Obtains the automatic completion state for this module based on any conditions
 * in assign settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function euroaspire_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/assign/locallib.php');

    $euroaspire = $DB->get_record('euroaspire', array('id' => $cm->instance), '*', MUST_EXIST);
    $manager = new \mod_euroaspire\local\control\assessmentmanager($course, $cm, $euroaspire, 'grade');

    // If completion option is enabled, evaluate it and return true/false.
    if ($manager->pass_to_complete()) {
        $manager->set_userid($userid);
        $submission = $manager->get_submission();
        return ($submission->is_passed());

    } else {
        // Completion option is not enabled so just return $type.
        return $type;
    }
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called
 *
 * @param settings_navigation $settings
 * @param navigation_node $euroaspirenode
 * @return void
 */
function euroaspire_extend_settings_navigation($settings, $euroaspirenode) {
    global $PAGE;

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $euroaspirenode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (assessmentmanager::can_edit_settings_in_context($PAGE->context)) {
        $url = new moodle_url('/mod/euroaspire/admin/complist.php', array('cmid' => $PAGE->cm->id));
        $node = navigation_node::create(get_string('editlistcomp', 'mod_euroaspire'),
                                        $url, navigation_node::TYPE_SETTING, null,
                                        'mod_euroaspire_editlistcomp');
        $euroaspirenode->add_node($node, $beforekey);

        $url = new moodle_url('/mod/euroaspire/admin/dimlist.php', array('cmid' => $PAGE->cm->id));
        $node = navigation_node::create(get_string('editlistdim', 'mod_euroaspire'),
                                        $url, navigation_node::TYPE_SETTING, null,
                                        'mod_euroaspire_editlistdim');
        $euroaspirenode->add_node($node, $beforekey);
    }
}

