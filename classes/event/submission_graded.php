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
 * The mod_euroaspire submission graded event.
 *
 * @package    mod_euroaspire
 * @copyright  2015 Davo Smith, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_euroaspire\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_euroaspire submission graded event class.
 *
 * @package    mod_euroaspire
 * @copyright  2015 Davo Smith, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_graded extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'euroaspire_submission';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has graded the submission for user '$this->relateduserid' for the ".
        "euroaspire with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissiongraded', 'mod_euroaspire');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/euroaspire/assessment/grade.php', array('id' => $this->contextinstanceid,
                                                                             'userid' => $this->relateduserid));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {

        // The legacy log table expects a relative path to /mod/euroaspire/.
        $logurl = substr($this->get_url()->out_as_local_url(), strlen('/mod/euroaspire/'));

        return array($this->courseid, 'euroaspire', 'grade submission', $logurl, $this->objectid, $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
        if (empty($this->data['objectid'])) {
            throw new \coding_exception('Must set the objectid (the euroaspire_submission id).');
        }
        if (!$this->relateduserid) {
            throw new \coding_exception('Must set the relateduserid (the user the submission belongs to).');
        }
    }
}
