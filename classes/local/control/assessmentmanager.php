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
 * Manage the submission of an assessment
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_euroaspire\local\control;

use cm_info;
use completion_info;
use core_useragent;
use mod_euroaspire\local\data\competency;
use mod_euroaspire\local\data\dimension;
use mod_euroaspire\local\data\submission;
use mod_euroaspire\local\data\submission_comment;
use mod_euroaspire\local\data\submission_grade;
use mod_euroaspire\local\data\submission_item;
use mod_euroaspire\local\data\submission_map;
use mod_euroaspire\local\form\add_evidence_form;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class assessmentmanager extends basemanager {
    /** @var string */
    protected $action;
    /** @var object|null */
    protected $user = null;

    /** @var submission */
    protected $submission = null;

    /** @var competency[] */
    protected $competencies = null;
    /** @var dimension[] */
    protected $dimensions = null;

    protected $page = null;
    protected $perpage = null;
    protected $totalitems = null;
    const DEFAULTPERPAGE = 30;

    /**
     * @param object $course
     * @param cm_info $cm
     * @param object $euroaspire
     * @param string $action
     * @param int $userid (optional)
     * @throws \coding_exception
     */
    public function __construct($course, $cm, $euroaspire, $action) {
        parent::__construct($course, $cm, $euroaspire);
        $this->action = $action;
    }

    /**
     * @throws \required_capability_exception
     */
    public function check_access() {
        $context = $this->get_context();

        // Check if everything is configured - if not, redirect to the most appropriate page.
        $redir = null;
        if (!$this->fully_configured()) {
            if ($this->can_edit_settings()) {
                // Can edit => view competencies or dimensions, depending on which is not yet configured.
                if (!$this->get_competencies()) {
                    $redir = new moodle_url('/mod/euroaspire/admin/complist.php', array('cmid' => $this->cm->id));
                } else {
                    $redir = new moodle_url('/mod/euroaspire/admin/dimlist.php', array('cmid' => $this->cm->id));
                }
            } else if ($this->action != 'intro') {
                // Cannot edit => keep on the intro page for now.
                $redir = $this->get_main_url();
            }
        }
        if ($redir) {
            redirect($redir);
        }

        switch ($this->action) {
            case 'add':
            case 'map':
                require_capability('mod/euroaspire:submit', $context);
                break;
            case 'grade':
            case 'gradeall':
                require_capability('mod/euroaspire:grade', $context);
                break;
        }
    }

    /**
     * @param null $action
     * @throws \coding_exception
     */
    public function setup_page($action = null) {
        global $PAGE;
        $title = get_string('assess'.$this->action, 'mod_euroaspire');

        $PAGE->set_title($title);
        $PAGE->set_heading($title);
        if ($this->action != 'intro') {
            $PAGE->navbar->add($title);
        }
    }

    /**
     * @return moodle_url
     */
    public function get_view_url() {
        return new moodle_url('/mod/euroaspire/view.php', array('id' => $this->cm->id));
    }

    /**
     * Have the competencies & dimensions been configured for this assessment?
     * @return bool
     */
    public function fully_configured() {
        return ($this->get_competencies() && $this->get_dimensions());
    }

    /**
     * Can the current user submit assignments?
     */
    public function can_submit() {
        return has_capability('mod/euroaspire:submit', $this->get_context());
    }

    /**
     * Can the current user grade assignments?
     */
    public function can_grade() {
        return has_capability('mod/euroaspire:grade', $this->get_context());
    }

    /**
     * @return array
     * @throws \coding_exception
     */
    public function get_actions() {
        $actions = array();
        $configured = $this->fully_configured();

        $gradingpage = in_array($this->action, array('grade', 'gradeall'));

        if ($configured && $this->can_grade()) {
            $actions['gradeall'] = new action(
                $this->get_grade_all_url(),
                'assessgradeall'
            );
        }
        if (!$gradingpage && $configured && $this->can_submit()) {
            $submission = $this->get_submission();
            $highlight = (!$submission || !$submission->get_items());
            $actions['add'] = new action(
                new moodle_url('/mod/euroaspire/assessment/addevidence.php', array('id' => $this->cm->id)),
                'assessadd',
                $highlight
            );
            if ($submission && $submission->get_items()) {
                $highlight = $this->evidence_saved();
                $actions['map'] = new action(
                    new moodle_url('/mod/euroaspire/assessment/mapevidence.php', array('id' => $this->cm->id)),
                    'assessmap',
                    $highlight
                );
            }
        }
        if ($this->can_submit() || !$this->can_grade()) {
            // Don't show 'intro' link to users who can only grade responses.
            $actions['intro'] = new action($this->get_view_url(), 'backintro');
        }

        // Remove the current action from the list.
        unset($actions[$this->action]);

        return $actions;
    }

    /**
     * @return string
     */
    public function get_formatted_title() {
        return format_string($this->euroaspire->name);
    }

    /**
     * @return string
     */
    public function get_formatted_intro() {
        return format_module_intro('euroaspire', $this->euroaspire, $this->cm->id);
    }

    /**
     * @return array
     * @throws \coding_exception
     */
    public function get_notification_message() {
        global $DB;
        if ($this->evidence_saved()) {
            return $this->notification_message(get_string('evidencesaved', 'mod_euroaspire'));
        } else if ($this->mapping_saved()) {
            return $this->notification_message(get_string('mappingsaved', 'mod_euroaspire'));
        } else if ($userid = $this->grade_saved()) {
            if ($this->user && $this->user->id == $userid) {
                $user = $this->user;
            } else {
                $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
            }
            return $this->notification_message(get_string('gradesavedfor', 'mod_euroaspire', fullname($user)));
        }
        return parent::get_notification_message();
    }

    /**
     * @param bool $createifmissing
     * @return submission
     */
    public function get_submission($createifmissing = false) {
        global $USER;

        if ($this->submission === null) {
            if ($this->user) {
                $userid = $this->user->id;
            } else {
                $userid = $USER->id;
            }

            $this->submission = new submission(array('userid' => $userid, 'euroaspireid' => $this->euroaspire->id));
            if ($createifmissing && !$this->submission->id) {
                $this->submission->insert();
            }
        }

        return $this->submission;
    }

    /**
     * Get the list of competencies associated with the loaded assessment.
     *
     * @return competency[]
     */
    public function get_competencies() {
        if ($this->competencies === null) {
            $this->competencies = competency::fetch_all(array('euroaspireid' => $this->euroaspire->id));
        }
        return $this->competencies;
    }

    /**
     * Get the list of dimensions associated with the loaded assessment.
     *
     * @return dimension[]
     */
    public function get_dimensions() {
        if ($this->dimensions === null) {
            $this->dimensions = dimension::fetch_all(array('euroaspireid' => $this->euroaspire->id));
        }
        return $this->dimensions;
    }

    /**
     * Gets the current page, number per page and total items for the current paging.
     * @return int[]
     * @throws \coding_exception
     */
    public function get_paging_info() {
        if ($this->page === null) {
            throw new \coding_exception('page not initialised');
        }
        return array($this->page, $this->perpage, $this->totalitems);
    }

    /**
     * Initialise $this->page and $this->perpage from the URL params.
     */
    protected function init_paging() {
        global $PAGE;
        $this->perpage = optional_param('perpage', self::DEFAULTPERPAGE, PARAM_INT);
        $this->page = optional_param('page', 0, PARAM_INT);
        $this->totalitems = 0;
        if ($this->perpage != self::DEFAULTPERPAGE || $this->page != 0) {
            $url = new moodle_url($PAGE->url);
            if ($this->perpage != self::DEFAULTPERPAGE) {
                $url->param('perpage', $this->perpage);
            }
            if ($this->page != 0) {
                $url->param('page', $this->page);
            }
            $PAGE->set_url($url);
        }
    }

    public function reset_userdata($data) {
        $status = array();
        $deletegrades = !empty($data->reset_gradebook_grades);
        if (!empty($data->reset_euroaspire_submissions)) {
            $submissions = submission::fetch_all(array('euroaspireid' => $this->euroaspire->id));
            if ($submissions) {
                foreach ($submissions as $submission) {
                    $submission->delete($deletegrades);
                }
            }
        }

        return $status;
    }

    public function pass_to_complete() {
        return $this->euroaspire->passtocomplete;
    }

    protected function trigger_event($type) {
        $params = array(
            'context' => $this->get_context(),
        );
        if ($this->user) {
            $params['relateduserid'] = $this->user->id;
        }
        $submission = $this->get_submission();
        $params['objectid'] = $submission->id;

        switch ($type) {
            case 'viewed':
                $params['objectid'] = $this->euroaspire->id;
                $event = \mod_euroaspire\event\course_module_viewed::create($params);
                break;
            case 'evidence_uploaded':
                $event = \mod_euroaspire\event\evidence_uploaded::create($params);
                $event->add_record_snapshot('euroaspire_submission', $submission->get_record_data());
                break;
            case 'submission_submitted':
                $event = \mod_euroaspire\event\submission_submitted::create($params);
                $event->add_record_snapshot('euroaspire_submission', $submission->get_record_data());
                break;
            case 'all_submissions_viewed':
                $params['objectid'] = $this->euroaspire->id;
                $event = \mod_euroaspire\event\all_submissions_viewed::create($params);
                break;
            case 'submission_viewed':
                if (!$submission->id) {
                    return; // User has not yet created a submission - so nothing to view.
                }
                $event = \mod_euroaspire\event\submission_viewed::create($params);
                $event->add_record_snapshot('euroaspire_submission', $submission->get_record_data());
                break;
            case 'submission_graded':
                $event = \mod_euroaspire\event\submission_graded::create($params);
                $event->add_record_snapshot('euroaspire_submission', $submission->get_record_data());
                break;
            default:
                throw new \coding_exception("Invalid event type '$type'");
        }

        // Trigger the event.
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('course', $this->course);
        $event->add_record_snapshot('euroaspire', $this->euroaspire);
        $event->trigger();
    }

    // -------------------------------------
    // Intro page.
    // -------------------------------------

    /**
     *
     */
    public function process_intro() {
        // Mark the activity as viewed.
        $completion = new completion_info($this->course);
        $completion->set_module_viewed($this->cm);

        $this->trigger_event('viewed');

        // Users who can grade, but not submit, should start on the 'grade all' page.
        if ($this->fully_configured() && $this->can_grade() && !$this->can_submit()) {
            redirect($this->get_grade_all_url());
        }
    }

    // -------------------------------------
    // Add evidence page.
    // -------------------------------------

    /**
     * @return bool
     */
    public function evidence_saved() {
        return optional_param('evidencesaved', false, PARAM_BOOL);
    }

    /**
     * @throws \moodle_exception
     */
    public function process_add() {
        global $CFG, $PAGE;
        require_once($CFG->dirroot.'/repository/lib.php');
        $fileopts = array(
            'subdirs' => 1,
            'maxbytes' => get_max_upload_file_size($CFG->maxbytes, $this->course->maxbytes),
            'maxfiles' => -1,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE,
        );

        $submission = $this->get_submission(true);
        $urlitems = $submission->get_url_items();
        $toform = new stdClass();
        $toform->id = $this->cm->id;
        $toform = file_prepare_standard_filemanager($toform, 'evidence', $fileopts, $this->get_context(),
                                                    'mod_euroaspire', submission_item::FILEAREA, $submission->id);
        $key = 0;
        foreach ($urlitems as $urlitem) {
            $toform->{"url[{$key}]"} = $urlitem->url;
            $toform->{"urltitle[{$key}]"} = $urlitem->title;
            $key++;
        }

        $numurls = max(count($urlitems) + 2, add_evidence_form::URLS_TO_ADD);
        $custom = array(
            'fileopts' => $fileopts,
            'numurls' => $numurls,
        );

        $this->form = new add_evidence_form(null, $custom);
        $this->form->set_data($toform);

        if ($this->form->is_cancelled()) {
            redirect($this->get_view_url());
        }

        if ($fromform = $this->form->get_data()) {
            $fromform = file_postupdate_standard_filemanager($fromform, 'evidence', $fileopts, $this->get_context(),
                                                             'mod_euroaspire', submission_item::FILEAREA, $submission->id);
            $this->update_items($submission, $fromform);
            $submission->update(); // Update the 'last modified' time.

            $this->trigger_event('evidence_uploaded');

            $redir = new moodle_url($PAGE->url, array('evidencesaved' => 1));
            redirect($redir);
        }

        // Add the javascript for handling page title retrieval, etc.
        $opts = array(
            'cmid' => $this->cm->id,
        );
        $PAGE->requires->yui_module('moodle-mod_euroaspire-addevidence', 'M.mod_euroaspire.addevidence.init', array($opts));
        $PAGE->requires->strings_for_js(array('titleerror'), 'mod_euroaspire');
    }

    /**
     * Given a URL, retrieve the title of the specified page.
     *
     * @param string $url
     * @return object containing title + url
     * @throws moodle_exception
     */
    public function get_page_title($url) {
        require_sesskey();

        if (!trim($url)) {
            throw new moodle_exception('invalidurl', 'mod_euroaspire');
        }

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        $res = curl_exec($curl);
        if ($res === false) {
            throw new moodle_exception('invalidurl', 'mod_euroaspire');
        }
        $result = preg_match("/<title[^>]*>([^<]*)<\/title>/", $res, $matches);
        if (!$result) {
            throw new moodle_exception('notitle', 'mod_euroaspire');
        }
        $title = $matches[1];

        return (object)array(
            'title' => $title,
            'url' => $url,
        );
    }

    /**
     * Update the submitted files & URLs from the form data.
     *
     * @param submission $submission
     * @param object $fromform
     */
    protected function update_items(submission $submission, $fromform) {
        // Current items.
        $oldurls = $submission->get_url_items();
        $oldfiles = $submission->get_file_items();

        // New items.
        $updatedurls = $fromform->url;
        $updatedurltitles = $fromform->urltitle;
        $fs = get_file_storage();
        $context = $this->get_context();
        $updatedfiles = $fs->get_area_files($context->id, 'mod_euroaspire', submission_item::FILEAREA,
                                            $submission->id, 'itemid, filepath, filename', false);

        // Update the URL items.
        /** @var submission_item $oldurl */
        $oldurl = reset($oldurls);
        foreach ($updatedurls as $idx => $updatedurl) {
            $updatedurl = $this->tidy_url($updatedurl);
            $updatedtitle = trim($updatedurltitles[$idx]);
            if ($oldurl) {
                if ($updatedurl) {
                    // URL updated.
                    $oldurl->url = $updatedurl;
                    $oldurl->title = $updatedtitle;
                    $oldurl->update();
                } else {
                    // URL deleted.
                    $oldurl->delete();
                }
            } else {
                if ($updatedurl) {
                    // New URL.
                    $newurl = new submission_item();
                    $newurl->url = $updatedurl;
                    $newurl->title = $updatedtitle;
                    $newurl->submissionid = $submission->id;
                    $newurl->insert();
                }
            }

            $oldurl = next($oldurls);
        }
        // Delete any remaining existing URLs.
        while ($oldurl) {
            $oldurl->delete();
            $oldurl = next($oldurls);
        }

        // Update the file items.
        /** @var submission_item[] $indexedfiles */
        $indexedfiles = array();
        foreach ($oldfiles as $oldfile) {
            $indexedfiles[$oldfile->pathnamehash] = $oldfile;
        }
        foreach ($updatedfiles as $updatedfile) {
            $newpathhash = $updatedfile->get_pathnamehash();
            if (isset($indexedfiles[$newpathhash])) {
                // File still present.
                unset($indexedfiles[$newpathhash]); // Anything still left in the list at the end can be deleted.
            } else {
                // New file.
                $newfile = new submission_item();
                $newfile->pathnamehash = $newpathhash;
                $newfile->title = ltrim($updatedfile->get_filepath().$updatedfile->get_filename(), '/');
                $newfile->submissionid = $submission->id;
                $newfile->insert();
            }
        }
        // Delete any remaining existing files (that were not found in the new files list).
        foreach ($indexedfiles as $todelete) {
            $todelete->delete();
        }
    }

    /**
     * Trim the URL and make sure it starts with a scheme.
     * Defaults to 'http://' if no other specified.
     *
     * @param string $url
     * @return string
     */
    protected function tidy_url($url) {
        $url = trim($url);
        if ($url) {
            $parse = parse_url($url);
            if (empty($parse['scheme'])) {
                $url = 'http://'.$url;
            }
        }
        return $url;
    }

    // -------------------------------------
    // Map evidence page.
    // -------------------------------------

    /**
     * @return bool
     */
    public function mapping_saved() {
        return optional_param('mappingsaved', false, PARAM_BOOL);
    }

    /**
     * Get the URL that the form should submit to when mapping evidence to competencies
     * (including params to output as hidden elements).
     *
     * @return moodle_url
     * @throws \coding_exception
     */
    public function get_submit_map_url() {
        global $PAGE;
        if ($this->action != 'map') {
            throw new \coding_exception('get_submit_map_url only valid when the action is \'map\'');
        }
        $url = new moodle_url($PAGE->url, array('sesskey' => sesskey())); // Add the sesskey to the params.
        return $url;
    }

    public function process_map() {
        global $PAGE;

        if (optional_param('cancel', false, PARAM_BOOL)) {
            redirect($this->get_main_url());
        }

        if ($data = data_submitted()) {
            if (!empty($data->submitbutton)) {
                require_sesskey();
                if ($this->process_submitted_map($data)) {
                    $submission = $this->get_submission();
                    $submission->update(); // Update the time modified.
                }

                $this->trigger_event('submission_submitted');

                $redir = new moodle_url($PAGE->url, array('mappingsaved' => 1));
                redirect($redir);
            }
        }

        $PAGE->requires->string_for_js('changesmadereallygoaway', 'core');
        $opts = array(
            'itemselect' => $this->get_renderer()->item_select($this),
        );
        $PAGE->requires->yui_module('moodle-mod_euroaspire-mapevidence', 'M.mod_euroaspire.mapevidence.init', array($opts));
    }

    /**
     * For the given competency + dimension, return the attributes for the hidden input element containing the current mapping.
     *
     * @param competency $competency
     * @param dimension $dimension
     * @return array
     */
    public function get_mapped_item_input_attribs(competency $competency, dimension $dimension) {
        $items = $this->get_submission()->get_mapped_items($competency, $dimension);

        $name = 'items_'.$competency->id.'_'.$dimension->id;
        $id = 'id_'.$name;

        $attrib = array(
            'type' => 'hidden',
            'name' => $name,
            'id' => $id,
            'value' => implode(',', array_keys($items)),
            'class' => 'currentitems'
        );

        return $attrib;
    }

    /**
     * Update the existing mappings to match those submitted.
     *
     * @param $data
     * @return bool
     */
    protected function process_submitted_map($data) {
        $submitteditemids = $this->extract_submitted_itemids($data);
        $submission = $this->get_submission();
        $items = $submission->get_items();
        $updated = false;

        foreach ($this->get_competencies() as $competency) {
            foreach ($this->get_dimensions() as $dimension) {
                // Compare the submitted mappings for each competency + dimension to those that already exist.
                $currentitems = $submission->get_mapped_items($competency, $dimension);
                $subitemids = $submitteditemids[$competency->id][$dimension->id];

                $addedids = array_diff($subitemids, array_keys($currentitems));
                $removedids = array_diff(array_keys($currentitems), $subitemids);

                // Add any new mappings.
                foreach ($addedids as $addid) {
                    if (!isset($items[$addid])) {
                        continue; // The given itemid does not exist in this submission.
                    }
                    $mapitem = new submission_map(array(), false);
                    $mapitem->submissionid = $submission->id;
                    $mapitem->competencyid = $competency->id;
                    $mapitem->dimensionid = $dimension->id;
                    $mapitem->itemid = $addid;
                    $mapitem->insert();
                    $updated = true;
                }

                // Remove any mappings no longer found in the list.
                foreach ($removedids as $removeid) {
                    $removeitem = $currentitems[$removeid];
                    $removemap = $removeitem->retrieve_mapping($competency, $dimension);
                    $removemap->delete();
                    $updated = true;
                }
            }
        }
        foreach ($this->get_dimensions() as $dimension) {
            if (isset($data->dimensioncomment[$dimension->id])) {
                $newcomment = trim($data->dimensioncomment[$dimension->id]);
                $oldcomment = $submission->get_comment($dimension);
                if ($oldcomment) {
                    if ($oldcomment->comment != $newcomment) {
                        $oldcomment->comment = $newcomment;
                        $oldcomment->update();
                        $updated = true;
                    }
                } else {
                    if ($newcomment) {
                        $ins = new submission_comment(array(), false);
                        $ins->submissionid = $submission->id;
                        $ins->dimensionid = $dimension->id;
                        $ins->comment = $newcomment;
                        $ins->insert();
                        $updated = true;
                    }
                }
            }
        }
        if ($updated) {
            $submission->clear_cached_map();
            $submission->clear_cached_comments();
        }
        if (isset($data->usercomment)) {
            $usercomment = trim(clean_param($data->usercomment, PARAM_NOTAGS));
            if ($submission->usercomment != $usercomment) {
                $submission->usercomment = $usercomment;
                $updated = true; // Don't need to clear the cache, but still need to save the updated submission.
            }
        }
        return $updated;
    }

    /**
     * @param $data
     * @return int[][][]
     */
    protected function extract_submitted_itemids($data) {
        $submitteditemids = array();
        foreach ($data as $key => $value) {
            $key = explode('_', $key);
            if (count($key) != 3 || $key[0] != 'items') {
                continue;
            }
            $competencyid = intval($key[1]);
            $dimensionid = intval($key[2]);
            $value = clean_param($value, PARAM_SEQUENCE);
            if ($value) {
                $items = explode(',', $value);
            } else {
                $items = array();
            }
            if (!isset($submitteditemids[$competencyid])) {
                $submitteditemids[$competencyid] = array();
            }
            $submitteditemids[$competencyid][$dimensionid] = $items;
        }
        return $submitteditemids;
    }

    // -------------------------------------
    // List all grades page.
    // -------------------------------------

    public function get_grade_all_url() {
        return new moodle_url('/mod/euroaspire/assessment/gradeall.php', array('id' => $this->cm->id));
    }

    public function process_grade_all() {
        $this->trigger_event('all_submissions_viewed');
    }

    /**
     * Get all gradeable students on the course, without paging.
     *
     * @return object[]
     */
    private function get_all_gradeable_students() {
        $fields = 'u.id,'.get_all_user_name_fields(true, 'u');
        return get_users_by_capability($this->get_context(), 'mod/euroaspire:submit', $fields, 'u.lastname');
    }

    /**
     * Get a paged list of gradeable students
     *
     * @return object[]
     */
    public function get_gradable_students() {
        $this->init_paging();

        $users = $this->get_all_gradeable_students();
        if (!$users) {
            return array();
        }
        $this->totalitems = count($users);
        $start = $this->page * $this->perpage;
        if ($start > $this->totalitems) {
            $start = 0;
            $this->page = 0;
        }
        $users = array_slice($users, $start, $this->perpage, true);

        $submissions = submission::fetch_by_userids($this->euroaspire->id, array_keys($users));
        foreach ($submissions as $userid => $submission) {
            $users[$userid]->submission = $submission;
        }
        foreach ($users as $user) {
            if (!isset($user->submission)) {
                $user->submission = new submission();
            }
        }
        return $users;
    }

    /**
     * Get the URL for grading this user.
     *
     * @param object $user
     * @return moodle_url
     */
    public function get_grade_url($user) {
        return new moodle_url('/mod/euroaspire/assessment/grade.php', array('id' => $this->cm->id, 'userid' => $user->id));
    }

    // -------------------------------------
    // Update grade page.
    // -------------------------------------

    /**
     * Should a 'grade saved' message be displayed?
     * @return int|false
     */
    public function grade_saved() {
        return optional_param('gradesaved', false, PARAM_INT);
    }

    /**
     * Set the id of the user being graded (only valid when grading an assessment).
     * @param $userid
     * @throws \coding_exception
     */
    public function set_userid($userid) {
        global $DB;
        if ($this->action == 'grade') {
            $this->user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
            require_capability('mod/euroaspire:submit', $this->get_context(), $this->user->id);
        } else if ($userid) {
            throw new \coding_exception('Userid should only be specified for the \'grade\' action');
        }
    }

    /**
     * Get the user who is being graded.
     */
    public function get_user() {
        if (!$this->user) {
            throw new \coding_exception('No user specified');
        }
        return $this->user;
    }

    /**
     * Get the URL that the form should submit to when grading evidence
     * (including params to output as hidden elements).
     *
     * @return moodle_url
     * @throws \coding_exception
     */
    public function get_submit_grade_url() {
        global $PAGE;
        if ($this->action != 'grade') {
            throw new \coding_exception('get_submit_grade_url only valid when the action is \'map\'');
        }
        $url = new moodle_url($PAGE->url, array('sesskey' => sesskey())); // Add the sesskey to the params.
        return $url;
    }

    /**
     * Process any submitted grading update.
     */
    public function process_grade() {
        global $PAGE;

        if (!$this->user) {
            throw new \coding_exception('Must specify the user that is being graded');
        }

        if (optional_param('cancel', false, PARAM_BOOL)) {
            redirect($this->get_grade_all_url());
        }

        if (optional_param('nextbutton', false, PARAM_BOOL)) {
            redirect($this->get_next_grade_url());
        }

        if (optional_param('exportcomments', false, PARAM_BOOL)) {
            $this->export_comments();
            die();
        }

        if ($data = data_submitted()) {
            if (!empty($data->submitbutton) || !empty($data->submitnextbutton)) {
                require_sesskey();

                $this->process_submitted_grades($data);


                $submission = $this->get_submission(true);
                $waspassed = $submission->is_passed();
                $submission->grade = $this->calculate_grade($submission);
                $submission->timegraded = time();
                $submission->update(false); // Do not update the modified time.

                // Update completion, if the pass status has changed.
                $nowpassed = $submission->is_passed();
                if ($nowpassed != $waspassed) {
                    $completion = new completion_info($this->course);
                    if ($completion->is_enabled($this->cm) && $this->euroaspire->passtocomplete) {
                        $completionstate = $nowpassed ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
                        $completion->update_state($this->cm, $completionstate, $this->user->id);
                    }
                }

                $this->trigger_event('submission_graded');

                if (!empty($data->submitnextbutton)) {
                    $redir = $this->get_next_grade_url();
                } else {
                    $redir = new moodle_url($PAGE->url);
                }
                $redir->param('gradesaved', $this->user->id);
                redirect($redir);
            }
        }

        $this->trigger_event('submission_viewed');

        $PAGE->requires->string_for_js('changesmadereallygoaway', 'core');
        $PAGE->requires->yui_module('moodle-mod_euroaspire-grade', 'M.mod_euroaspire.grade.init');
    }

    /**
     * Get the URL to grade the next user in the list.
     */
    protected function get_next_grade_url() {
        $users = $this->get_all_gradeable_students();
        $user = reset($users);
        do {
            if ($user->id == $this->user->id) {
                if ($nextuser = next($users)) {
                    // Found the next user - return a link to them.
                    return $this->get_grade_url($nextuser);
                }
            }
        } while ($user = next($users));

        // Reached the end of the list - go back to the 'grade all' page.
        return $this->get_grade_all_url();
    }

    /**
     * Update the grades & comments in the database.
     *
     * @param $data
     */
    protected function process_submitted_grades($data) {
        global $USER;
        list($submittedcomments, $submittedgrades) = $this->extract_submitted_grades($data);
        $submission = $this->get_submission(true);
        $updated = false;

        foreach ($this->get_competencies() as $competency) {
            foreach ($this->get_dimensions() as $dimension) {
                $currentgrade = $submission->get_grade($competency, $dimension);
                $subcomment = $submittedcomments[$competency->id][$dimension->id];
                $subgrade = $submittedgrades[$competency->id][$dimension->id];

                if ($currentgrade) {
                    if (!$subcomment && $subgrade == submission_grade::GRADE_NONE) {
                        // Grade and comment are both blank - delete the existing grade.
                        $currentgrade->delete();
                        $updated = true;
                    } else if ($subcomment != $currentgrade->comment || $subgrade != $currentgrade->grade) {
                        // Grade and/or comment have changed - update.
                        $currentgrade->comment = $subcomment;
                        $currentgrade->grade = $subgrade;
                        $currentgrade->graderid = $USER->id;
                        $currentgrade->update();
                        $updated = true;
                    }
                } else {
                    if ($subcomment || $subgrade != submission_grade::GRADE_NONE) {
                        // New grade and/or comment to insert.
                        $newgrade = new submission_grade();
                        $newgrade->submissionid = $submission->id;
                        $newgrade->competencyid = $competency->id;
                        $newgrade->dimensionid = $dimension->id;
                        $newgrade->grade = $subgrade;
                        $newgrade->comment = $subcomment;
                        $newgrade->graderid = $USER->id;
                        $newgrade->insert();
                        $updated = true;
                    }
                }
            }
        }

        if ($updated) {
            $submission->clear_cached_grades();
        }
    }

    /**
     * Extract the comments and grades from the submitted data.
     *
     * @param $data
     * @return array => [ string[][], int[][] ] - comments + grades
     */
    protected function extract_submitted_grades($data) {
        $submittedcomments = array();
        $submittedgrades = array();
        foreach ($data as $key => $value) {
            $key = explode('_', $key);
            if (count($key) != 3 || !in_array($key[0], array('grade', 'gradecomment'))) {
                continue;
            }
            $competencyid = intval($key[1]);
            $dimensionid = intval($key[2]);
            switch ($key[0]) {
                case 'gradecomment':
                    $value = clean_param($value, PARAM_NOTAGS);
                    if (!isset($submittedcomments[$competencyid])) {
                        $submittedcomments[$competencyid] = array();
                    }
                    $submittedcomments[$competencyid][$dimensionid] = trim($value);
                    break;
                case 'grade':
                    if (!in_array($value, submission_grade::$validgrades)) {
                        $value = submission_grade::GRADE_NONE;
                    }
                    if (!isset($submittedgrades[$competencyid])) {
                        $submittedgrades[$competencyid] = array();
                    }
                    $submittedgrades[$competencyid][$dimensionid] = $value;
                    break;
            }
        }
        return array($submittedcomments, $submittedgrades);
    }

    protected function calculate_grade(submission $submission) {
        $grade = submission::COMBINED_GRADE_PASS;
        foreach ($this->get_competencies() as $competency) {
            foreach ($this->get_dimensions() as $dimension) {
                $partgrade = $submission->get_grade($competency, $dimension);
                $partgrade = $partgrade ? $partgrade->grade : submission_grade::GRADE_NONE;
                if ($partgrade == submission_grade::GRADE_FAIL) {
                    return submission::COMBINED_GRADE_FAIL;
                }
                if ($partgrade == submission_grade::GRADE_NONE) {
                    $grade = submission::COMBINED_GRADE_NONE;
                }
            }
        }
        return $grade;
    }

    protected function export_comments() {
        $content = '';

        $submission = $this->get_submission();
        foreach ($this->get_dimensions() as $dimension) {
            foreach ($this->get_competencies() as $competency) {
                $grade = $submission->get_grade($competency, $dimension);
                if (empty($grade->comment)) {
                    continue;
                }
                $content .= '** '.s($dimension->title).' - '.s($competency->title).' **'."\n";
                $content .= s($grade->comment)."\n\n";
            }
        }

        $info = (object)array(
            'euroaspire' => format_string($this->euroaspire->name),
            'user' => fullname($this->get_user())
        );
        $filename = get_string('commentsfilename', 'mod_euroaspire', $info);
        $this->send_text_file($filename, $content);
    }

    protected function send_text_file($filename, $content) {
        $mimetype = !core_useragent::is_firefox() ? 'application/x-forcedownload' : 'text/plain';

        if (core_useragent::is_ie()) {
            $filename = rawurlencode($filename);
        }
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        if (is_https()) { // HTTPS sites - watch out for IE! KB812935 and KB316431.
            header('Cache-Control: private, max-age=10, no-transform');
            header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
            header('Pragma: ');
        } else { // Normal http - prevent caching at all cost.
            header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0, no-transform');
            header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
            header('Pragma: no-cache');
        }

        if ($mimetype === 'text/plain') {
            header('Content-Type: text/plain; charset=utf-8');
        } else {
            header('Content-Type: '.$mimetype);
        }
        header('Accept-Ranges: none');
        header('Content-Length: '.strlen($content));

        echo $content;

        die();
    }
}
