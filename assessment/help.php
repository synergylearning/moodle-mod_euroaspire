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
 * View capability description.
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_euroaspire\local\control\helpmanager;
use mod_euroaspire\local\data\competency;

global $PAGE, $OUTPUT, $DB;
require_once(dirname(__FILE__).'/../../../config.php');

$competencyid = required_param('competencyid', PARAM_INT);

$PAGE->set_url('/mod/euroaspire/assessment/help.php');

// Load the relevant data.
$competency = new competency(array('id' => $competencyid));
if (!$competency->id) {
    throw new moodle_exception('unknowncompetency', 'mod_euroaspire');
}
$euroaspire = $DB->get_record('euroaspire', array('id' => $competency->euroaspireid), '*', MUST_EXIST);
list($course, $cm) = get_course_and_cm_from_instance($euroaspire, 'euroaspire');
require_login($course, true, $cm);
$manager = new helpmanager($course, $cm, $euroaspire);
$manager->check_access();
$manager->setup_page();

$data = $manager->get_formatted_description($competency);
echo $OUTPUT->header();
if (!empty($data->heading)) {
    echo $OUTPUT->heading($data->heading, 1, 'helpheading');
}
echo $data->text;
echo $OUTPUT->footer();
