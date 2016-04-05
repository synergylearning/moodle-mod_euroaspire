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
 * Base class for all manager classes
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_euroaspire\local\control;

use cm_info;
use context;
use context_module;
use mod_euroaspire_renderer;
use moodle_url;
use moodleform;

defined('MOODLE_INTERNAL') || die();

abstract class basemanager {
    /** @var object */
    protected $course;
    /** @var cm_info */
    protected $cm;
    /** @var object */
    protected $euroaspire;

    /** @var moodleform */
    protected $form = null;

    const NOTIFICATION_SUCCESS = 'notifysuccess';
    const NOTIFICATION_PROBLEM = 'notifyproblem';

    public function __construct($course, $cm, $euroaspire) {
        $this->course = $course;
        $this->cm = $cm;
        $this->euroaspire = $euroaspire;
    }

    abstract public function check_access();
    abstract public function setup_page($action = null);

    /**
     * @return mod_euroaspire_renderer
     */
    public function get_renderer() {
        global $PAGE;
        return $PAGE->get_renderer('mod_euroaspire');
    }

    public function get_form() {
        if (!$this->form) {
            throw new \coding_exception('Request for a form when no form defined');
        }
        return $this->form;
    }

    public function get_main_url() {
        return new moodle_url('/mod/euroaspire/view.php', array('id' => $this->cm->id));
    }

    /**
     * Can the current user edit competencies + dimensions?
     */
    public function can_edit_settings() {
        return self::can_edit_settings_in_context($this->get_context());
    }

    /**
     * Can the current user edit competencies + dimensions?
     */
    public static function can_edit_settings_in_context(context $context) {
        return has_capability('mod/euroaspire:addinstance', $context);
    }

    protected function notification_message($msg, $type = self::NOTIFICATION_SUCCESS) {
        global $PAGE;
        static $addedjs = false;
        if (!$addedjs) {
            $PAGE->requires->yui_module('moodle-mod_euroaspire-notificationfade', 'M.mod_euroaspire.notificationfade.init');
            $addedjs = true;
        }
        return array($msg, $type);
    }

    public function get_notification_message() {
        return array(null, null);
    }

    /**
     * @return context_module
     */
    protected function get_context() {
        return context_module::instance($this->cm->id);
    }
}
