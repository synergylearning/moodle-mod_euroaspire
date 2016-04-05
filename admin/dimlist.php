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
 * List all dimensions
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');
global $PAGE, $DB;

$cmid = required_param('cmid', PARAM_INT);

$url = new moodle_url('/mod/euroaspire/admin/dimlist.php', array('cmid' => $cmid));
$PAGE->set_url($url);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'euroaspire');
$euroaspire = $DB->get_record('euroaspire', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$manager = new \mod_euroaspire\local\control\dimmanager($course, $cm, $euroaspire);
$manager->check_access();
$manager->setup_page('list');

$manager->process_list();

$output = $manager->get_renderer();

echo $output->header();
echo $output->dimension_list($manager);
echo $output->footer();
