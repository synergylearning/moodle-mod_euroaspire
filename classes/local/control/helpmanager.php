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
 * Control access to the competency pop-up help.
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_euroaspire\local\control;

use mod_euroaspire\local\data\competency;

defined('MOODLE_INTERNAL') || die();

class helpmanager extends basemanager {
    public function check_access() {
        if (!has_any_capability(array('mod/euroaspire:submit', 'mod/euroaspire:grade', 'mod/euroaspire:addinstance'),
                           $this->get_context())) {
            throw new \moodle_exception('nopermission');
        }
    }

    public function setup_page($action = null) {
        global $PAGE;
        $PAGE->set_pagelayout('popup');
    }

    public function get_formatted_description(competency $competency) {
        $title = format_string($competency->title);
        $description = format_text($competency->description, $competency->descriptionformat);
        $ret = (object)array(
            'heading' => $title,
            'text' => $description,
        );
        return $ret;
    }
}