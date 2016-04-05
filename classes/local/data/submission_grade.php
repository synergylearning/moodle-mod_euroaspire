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
 * Store grade given for each combination of competency + dimension.
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_euroaspire\local\data;

use data_object;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/completion/data_object.php');

class submission_grade extends data_object {
    public $table = 'euroaspire_submission_grade';
    public $required_fields = array('id', 'submissionid', 'competencyid', 'dimensionid', 'grade', 'comment',
                                    'graderid', 'timecreated', 'timemodified');
    public $text_fields = array('comment');
    public $optional_fields = array();

    /** @var int */
    public $submissionid;
    /** @var int */
    public $competencyid;
    /** @var int */
    public $dimensionid;
    /** @var string */
    public $grade;
    /** @var string */
    public $comment;
    /** @var int */
    public $graderid;
    /** @var int */
    public $timecreated;
    /** @var int */
    public $timemodified;

    const GRADE_NONE = 'none';
    const GRADE_PASS = 'pass';
    const GRADE_FAIL = 'fail';

    public static $validgrades = array(self::GRADE_NONE, self::GRADE_PASS, self::GRADE_FAIL);

    /**
     * Load the single matching record from the database.
     *
     * @param array $params
     * @return submission_grade
     */
    public static function fetch($params) {
        return self::fetch_helper('euroaspire_submission_grade', __CLASS__, $params);
    }

    /**
     * Load all matching records from the database.
     * @param array $params
     * @return submission_grade[]
     */
    public static function fetch_all($params) {
        return self::fetch_all_helper('euroaspire_submission_grade', __CLASS__, $params);
    }

    private function check_fields() {
        if (!in_array($this->grade, self::$validgrades)) {
            throw new \coding_exception("Invalid grade value: {$this->grade}");
        }
    }

    public function insert() {
        $this->timecreated = time();
        $this->timemodified = $this->timecreated;
        $this->check_fields();
        return parent::insert();
    }

    public function update() {
        $this->timemodified = time();
        $this->check_fields();
        return parent::update();
    }
}