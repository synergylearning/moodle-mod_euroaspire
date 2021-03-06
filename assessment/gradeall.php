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
 * Generate a list of all students with assignments to be graded.
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');
global $PAGE, $DB;

$id = required_param('id', PARAM_INT);

$url = new moodle_url('/mod/euroaspire/assessment/gradeall.php', array('id' => $id));
$PAGE->set_url($url);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'euroaspire');
$euroaspire = $DB->get_record('euroaspire', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$assessmentmanager = new \mod_euroaspire\local\control\assessmentmanager($course, $cm, $euroaspire, 'gradeall');
$assessmentmanager->check_access();
$assessmentmanager->setup_page();

$assessmentmanager->process_grade_all();

$output = $assessmentmanager->get_renderer();
echo $output->header();
echo $output->assessment_grade_all($assessmentmanager);
echo $output->footer();
