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
 * Retrieve the title for the given link
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__).'/../../../config.php');
global $PAGE, $DB;

$id = required_param('id', PARAM_INT);
$urlrequest = required_param('url', PARAM_URL);

$url = new moodle_url('/mod/euroaspire/assessment/addevidence.php',
                      array('id' => $id, 'url' => urlencode($urlrequest)));
$PAGE->set_url($url);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'euroaspire');
$euroaspire = $DB->get_record('euroaspire', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$assessmentmanager = new \mod_euroaspire\local\control\assessmentmanager($course, $cm, $euroaspire, 'add');
$assessmentmanager->check_access();

$ret = $assessmentmanager->get_page_title($urlrequest);
if (!isset($ret->error)) {
    $ret->error = 0;
}
echo json_encode($ret);
