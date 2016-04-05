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
 * A user's submission - this will consist of multiple items (files/urls) which may be
 * mapped onto multiple competencies + dimensions.
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_euroaspire\local\data;

use context_module;
use data_object;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/completion/data_object.php');

class submission extends data_object {
    public $table = 'euroaspire_submission';
    public $required_fields = array('id', 'euroaspireid', 'userid', 'grade', 'timecreated', 'timemodified', 'timegraded', 'usercomment');
    public $text_fields = array('title', 'url', 'usercomment');
    public $optional_fields = array();

    public $euroaspireid;
    public $userid;
    public $grade;
    public $timecreated;
    public $timemodified;
    public $timegraded;
    public $usercomment;

    const COMBINED_GRADE_PASS = 1;
    const COMBINED_GRADE_FAIL = 0;
    const COMBINED_GRADE_NONE = null;

    public static $validgrades = array(null, 0, 1);

    /** @var submission_item[] */
    protected $items = null;

    /** @var submission_item[][][] */
    protected $map = null;
    /** @var submission_grade[][] */
    protected $grades = null;
    /** @var submission_comment[] */
    protected $comments = null;

    /**
     * Load the single matching record from the database.
     *
     * @param array $params
     * @return submission
     */
    public static function fetch($params) {
        return self::fetch_helper('euroaspire_submission', __CLASS__, $params);
    }

    /**
     * Load all matching records from the database.
     * @param array $params
     * @return submission[]
     */
    public static function fetch_all($params) {
        return self::fetch_all_helper('euroaspire_submission', __CLASS__, $params);
    }

    /**
     * Load all records matching the given userids.
     * @param int[] $userids
     * @return submission[]
     */
    public static function fetch_by_userids($euroaspireid, $userids) {
        global $DB;
        if (!$userids) {
            return array();
        }
        list($usql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $select = "euroaspireid = :euroaspireid AND userid $usql";
        $params['euroaspireid'] = $euroaspireid;
        $submissions = $DB->get_records_select('euroaspire_submission', $select, $params);
        $result = array();

        foreach ($submissions as $submission) {
            $instance = new submission();
            self::set_properties($instance, $submission);
            $result[$instance->userid] = $instance;
        }
        return $result;
    }

    /**
     * Does this submission belong to the current user?
     *
     * @return bool
     */
    public function is_mine() {
        global $USER;
        return ($this->userid == $USER->id);
    }

    /**
     * Has the submission changed since it was last graded?
     * @return bool
     */
    public function needs_grading() {
        return ($this->timemodified > $this->timegraded);
    }

    /**
     * Has this submission passed?
     *
     * @return bool
     */
    public function is_passed() {
        return ($this->grade == self::COMBINED_GRADE_PASS);
    }

    /**
     * Format the user comment ready for output.
     *
     * @return string
     */
    public function formatted_user_comment() {
        $comment = s($this->usercomment);
        return nl2br($comment);
    }

    /**
     * Format the user dimension comment ready for output.
     *
     * @return string
     */
    public function formatted_dimension_comment(dimension $dimension) {
        $comment = $this->get_comment($dimension);
        if (!$comment) {
            return '';
        }
        $comment = s($comment->comment);
        return nl2br($comment);
    }

    private function check_fields() {
        if (!in_array($this->grade, self::$validgrades)) {
            throw new \coding_exception("Invalid grade '{$this->grade}'");
        }
    }

    public function insert() {
        $this->timecreated = time();
        $this->timemodified = time();
        $this->check_fields();
        $this->update_gradebook();
        return parent::insert();
    }

    public function update($updatetimemodified = true) {
        if ($updatetimemodified) {
            $this->timemodified = time();
        }
        $this->check_fields();
        $this->update_gradebook();
        return parent::update();
    }

    public function delete($deletegrades = true) {
        if ($deletegrades) {
            $this->grade = null;
            $this->update_gradebook();
        }
        if ($items = submission_item::fetch_all(array('submissionid' => $this->id))) {
            foreach ($items as $item) {
                $item->delete(false); // All maps will be deleted below.
            }
        }
        if ($maps = submission_map::fetch_all(array('submissionid' => $this->id))) {
            foreach ($maps as $map) {
                $map->delete();
            }
        }
        if ($grades = submission_grade::fetch_all(array('submissionid' => $this->id))) {
            foreach ($grades as $grade) {
                $grade->delete();
            }
        }
        if ($comments = submission_comment::fetch_all(array('submissionid' => $this->id))) {
            foreach ($comments as $comment) {
                $comment->delete();
            }
        }

        $this->delete_files();
        return parent::delete();
    }

    private function delete_files() {
        if (!$cm = get_coursemodule_from_instance('euroaspire', $this->euroaspireid)) {
            return;
        }
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_euroaspire', submission_item::FILEAREA, $this->id);
    }

    /**
     * Get a list of all items (files/urls).
     *
     * @param bool $sort (optional) set true to stort by title.
     * @return submission_item[]
     */
    public function get_items($sort = false) {
        if ($this->items === null) {
            if (!$this->items = submission_item::fetch_all(array('submissionid' => $this->id))) {
                $this->items = array();
            }
        }
        if ($sort) {
            uasort($this->items, function(submission_item $a, submission_item $b) {
                $cmp = strcasecmp($a->title, $b->title);
                if ($cmp == 0) {
                    return 0;
                }
                return ($cmp < 0) ? -1 : 1;
            });
        }
        return $this->items;
    }

    public function get_mapped_unmapped_items() {
        $this->get_map(); // Make sure the item mapping data is initialised.
        $allitems = $this->get_items(true);
        $mapped = array();
        $unmapped = array();
        foreach ($allitems as $item) {
            if ($item->has_any_mappings()) {
                $mapped[] = $item;
            } else {
                $unmapped[] = $item;
            }
        }
        return array($unmapped, $mapped);
    }

    /**
     * Get a list of only URL items.
     *
     * @return submission_item[]
     */
    public function get_url_items() {
        $items = $this->get_items();
        $ret = array();
        foreach ($items as $item) {
            if ($item->is_url()) {
                $ret[$item->id] = $item;
            }
        }
        return $ret;
    }

    /**
     * Get a list of only file items.
     *
     * @return submission_item[]
     */
    public function get_file_items() {
        $items = $this->get_items();
        $ret = array();
        foreach ($items as $item) {
            if ($item->is_file()) {
                $ret[$item->id] = $item;
            }
        }
        return $ret;
    }

    private function update_gradebook() {
        global $CFG;
        require_once($CFG->dirroot.'/mod/euroaspire/lib.php');
        euroaspire_set_user_grade($this->euroaspireid, $this->userid, $this->grade);
    }

    /**
     * Get the full list of all currently mapped items.
     * @return submission_item[][][]
     */
    public function get_map() {
        if ($this->map === null) {
            $items = $this->get_items();
            $this->map = array();

            $maps = submission_map::fetch_all(array('submissionid' => $this->id));
            if ($maps) {
                foreach ($maps as $map) {
                    if (!isset($items[$map->itemid])) {
                        continue; // This shouldn't happen, but safest solution is to ignore.
                    }
                    if (!isset($this->map[$map->competencyid])) {
                        $this->map[$map->competencyid] = array();
                    }
                    if (!isset($this->map[$map->competencyid][$map->dimensionid])) {
                        $this->map[$map->competencyid][$map->dimensionid] = array();
                    }
                    $item = $items[$map->itemid];
                    $this->map[$map->competencyid][$map->dimensionid][$item->id] = $item;
                    $item->store_mapping($map);
                }
            }
        }
        return $this->map;
    }

    /**
     * The mappings have been updated, so clear the cached copy (it will be rebuilt on request).
     */
    public function clear_cached_map() {
        $this->map = null;
    }

    /**
     * Returns a list of items currently associated with the given competency + dimension.
     *
     * @param competency $competency
     * @param dimension $dimension
     * @return submission_item[]
     */
    public function get_mapped_items(competency $competency, dimension $dimension) {
        $map = $this->get_map();
        if (!isset($map[$competency->id][$dimension->id])) {
            return array();
        }
        return $map[$competency->id][$dimension->id];
    }

    /**
     * Returns a list of all grades given so far to this submissions
     *
     * @return submission_grade[][]
     */
    protected function get_grades() {
        if (!$this->grades) {
            $this->grades = array();

            $grades = submission_grade::fetch_all(array('submissionid' => $this->id));
            if ($grades) {
                foreach ($grades as $grade) {
                    if (!isset($this->grades[$grade->competencyid])) {
                        $this->grades[$grade->competencyid] = array();
                    }
                    $this->grades[$grade->competencyid][$grade->dimensionid] = $grade;
                }
            }
        }
        return $this->grades;
    }

    /**
     * The grades have been updated, so clear the cached copy of them.
     */
    public function clear_cached_grades() {
        $this->grades = null;
    }

    /**
     * Return the grade for a particular competency + dimension
     *
     * @param competency $competency
     * @param dimension $dimension
     * @return submission_grade|null
     */
    public function get_grade(competency $competency, dimension $dimension) {
        $grades = $this->get_grades();
        if (!isset($grades[$competency->id][$dimension->id])) {
            return null;
        }
        return $grades[$competency->id][$dimension->id];
    }

    public function get_overall_grade_string() {
        if ($this->grade === self::COMBINED_GRADE_NONE) {
            return '';
        }
        if ($this->grade == self::COMBINED_GRADE_FAIL) {
            return get_string('fail', 'mod_euroaspire');
        }
        return get_string('pass', 'mod_euroaspire');
    }

    /**
     * Get the list of comments associated with the dimensions for this submission.
     *
     * @return submission_comment[]
     */
    protected function get_comments() {
        if (!$this->comments) {
            $this->comments = array();
            $comments = submission_comment::fetch_all(array('submissionid' => $this->id));
            if ($comments) {
                foreach ($comments as $comment) {
                    $this->comments[$comment->dimensionid] = $comment;
                }
            }
        }
        return $this->comments;
    }

    /**
     * The comments have been updated, so clear the cached copy of them.
     */
    public function clear_cached_comments() {
        $this->comments = null;
    }

    /**
     * Return the comment for a particular dimension.
     *
     * @param dimension $dimension
     * @return submission_comment|null
     */
    public function get_comment(dimension $dimension) {
        $comments = $this->get_comments();
        if (!isset($comments[$dimension->id])) {
            return null;
        }
        return $comments[$dimension->id];
    }
}