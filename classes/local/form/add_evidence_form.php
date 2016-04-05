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
 * Form for editing a competency
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_euroaspire\local\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir.'/formslib.php');

class add_evidence_form extends moodleform {
    const URLS_TO_ADD = 3;

    public function definition() {
        $mform = $this->_form;
        $fileopts = $this->_customdata['fileopts'];
        $numurls = $this->_customdata['numurls'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'deletedlinks');
        $mform->setType('deletedlinks', PARAM_SEQUENCE);

        $mform->addElement('filemanager', 'evidence_filemanager', get_string('files', 'mod_euroaspire'), null, $fileopts);

        $repeatarray = array();
        $repeatoptions = array();
        $repeatarray[] = $mform->createElement('text', 'url', get_string('url', 'mod_euroaspire'),
                                               array('size' => 60, 'class' => 'evidenceurl'));
        $repeatoptions['url']['type'] = PARAM_URL;
        $repeatarray[] = $mform->createElement('text', 'urltitle', get_string('urltitle', 'mod_euroaspire'));
        $repeatoptions['urltitle']['type'] = PARAM_NOTAGS;

        $this->repeat_elements($repeatarray, $numurls, $repeatoptions, 'url_repeats', 'url_add_fields', self::URLS_TO_ADD,
                               get_string('addmoreurls', 'mod_euroaspire', self::URLS_TO_ADD));

        $this->add_action_buttons();
    }
}