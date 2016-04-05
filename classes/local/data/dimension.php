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
 * Store details of different dimensions
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_euroaspire\local\data;

use coding_exception;
use data_object;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/completion/data_object.php');

class dimension extends data_object {
    public $table = 'euroaspire_dimension';
    public $required_fields = array('id', 'euroaspireid', 'title', 'sortorder');
    public $text_fields = array();
    public $optional_fields = array();

    public $euroaspireid;
    public $title;
    public $sortorder;

    private $candelete = null;

    /**
     * Load the single matching record from the database.
     *
     * @param array $params
     * @return dimension
     */
    public static function fetch($params) {
        return self::fetch_helper('euroaspire_dimension', __CLASS__, $params);
    }

    /**
     * Load all matching records from the database.
     * @param array $params
     * @return dimension[]
     */
    public static function fetch_all($params) {
        if (!$ret = self::fetch_all_helper('euroaspire_dimension', __CLASS__, $params)) {
            $ret = array();
        }
        // Sort by sortorder.
        usort($ret, function(dimension $a, dimension $b) {
            if ($a->sortorder < $b->sortorder) {
                return -1;
            }
            if ($a->sortorder > $b->sortorder) {
                return 1;
            }
            return 0;
        });
        if (array_keys($params) == array('euroaspireid')) {
            // If retrieving all dimensions for an instance (the usual case), check the sortorder fields are consecutive.
            self::check_sortorder($ret);
        }
        return $ret;
    }

    /**
     * Fix any sortorder problems
     *
     * @param dimension[] $dimensions
     */
    private static function check_sortorder($dimensions) {
        $expected = 1;
        foreach ($dimensions as $comp) {
            if ($comp->sortorder != $expected) {
                $comp->sortorder = $expected;
                $comp->update();
            }
            $expected++;
        }
    }

    public function insert() {
        $this->sortorder = $this->get_next_sortorder();
        return parent::insert();
    }

    public function delete() {
        // Delete any associated mappings / grades.
        if ($maps = submission_map::fetch_all(array('dimensionid' => $this->id))) {
            foreach ($maps as $map) {
                $map->delete();
            }
        }
        if ($grades = submission_grade::fetch_all(array('dimensionid' => $this->id))) {
            foreach ($grades as $grade) {
                $grade->delete();
            }
        }
        if ($comments = submission_comment::fetch_all(array('dimensionid' => $this->id))) {
            foreach ($comments as $comment) {
                $comment->delete();
            }
        }
        return parent::delete();
    }

    private function get_next_sortorder() {
        global $DB;
        if (!$this->euroaspireid) {
            throw new coding_exception('Must set euroaspireid before saving');
        }
        $maxsortorder = $DB->get_field('euroaspire_dimension', 'MAX(sortorder)',
                                       array('euroaspireid' => $this->euroaspireid));
        if (!$maxsortorder) {
            return 1;
        }
        return $maxsortorder + 1;
    }

    public function move_up() {
        $all = self::fetch_all(array('euroaspireid' => $this->euroaspireid)); // Make sure the sortorder is correct.
        /** @var dimension $prev */
        $prev = null;
        foreach ($all as $comp) {
            if ($comp->id == $this->id) {
                if ($prev) {
                    $this->sortorder = $prev->sortorder;
                    $this->update();
                    $prev->sortorder = $comp->sortorder;
                    $prev->update();
                }
                return;
            } else {
                $prev = $comp;
            }
        }
    }

    public function move_down() {
        $all = self::fetch_all(array('euroaspireid' => $this->euroaspireid)); // Make sure the sortorder is correct.
        /** @var dimension $me */
        $me = null;
        foreach ($all as $comp) {
            if ($comp->id == $this->id) {
                $me = $comp;
            } else if ($me) {
                $this->sortorder = $comp->sortorder;
                $this->update();
                $comp->sortorder = $me->sortorder;
                $comp->update();
                return;
            }
        }
    }

    /**
     * Can this dimension be deleted? (no, if it is linked to a grade)
     *
     * @return bool
     */
    public function can_delete() {
        global $DB;
        if ($this->candelete === null) {
            $this->candelete = !$DB->record_exists('euroaspire_submission_map', array('dimensionid' => $this->id))
                && !$DB->record_exists('euroaspire_submission_grade', array('dimensionid' => $this->id))
                && !$DB->record_exists('euroaspire_submission_comnt', array('dimensionid' => $this->id));
        }
        return $this->candelete;
    }
}