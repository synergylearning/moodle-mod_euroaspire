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
 * Store the per-dimension user comments.
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

class submission_comment extends data_object {
    public $table = 'euroaspire_submission_comnt';
    public $required_fields = array('id', 'submissionid', 'dimensionid', 'comment');
    public $text_fields = array('comment');
    public $optional_fields = array();

    /** @var int */
    public $submissionid;
    /** @var int */
    public $dimensionid;
    /** @var string */
    public $comment;

    /**
     * Load the single matching record from the database.
     *
     * @param array $params
     * @return submission_comment
     */
    public static function fetch($params) {
        return self::fetch_helper('euroaspire_submission_comnt', __CLASS__, $params);
    }

    /**
     * Load all matching records from the database.
     * @param array $params
     * @return submission_comment[]
     */
    public static function fetch_all($params) {
        return self::fetch_all_helper('euroaspire_submission_comnt', __CLASS__, $params);
    }
}