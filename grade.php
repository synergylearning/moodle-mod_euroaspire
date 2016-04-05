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
 * Redirect to show the user's grade
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
global $PAGE, $USER;

$cmid = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$url = new moodle_url('/mod/euroaspire/grade.php', array('id' => $id, 'userid' => $userid));
$PAGE->set_url($url);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'euroaspire');

require_login($course, true, $cm);

if (has_capability('mod/euroaspire:grade', $PAGE->context)) {
    // If the current user can grade submissions, go to the grading page.
    redirect('/mod/euroaspire/grade.php', array('id' => $cm->id, 'userid' => $userid));
}

// Go to the main view page.
redirect('/mod/euroaspire/view.php', array('id' => $cm->id));
