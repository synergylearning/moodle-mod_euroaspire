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
 * Manage the competencies
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_euroaspire\local\control;

use mod_euroaspire\local\data\competency;
use mod_euroaspire\local\form\competency_form;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class compmanager extends basemanager {
    protected $competency;
    protected $competencies = null;

    public function __construct($course, $cm, $euroaspire, $compid = null) {
        parent::__construct($course, $cm, $euroaspire);
        if ($compid) {
            $this->competency = new competency(array('id' => $compid));
            if ($this->competency->euroaspireid != $euroaspire->id) {
                throw new \moodle_exception('competencyinstancemismatch', 'mod_euroaspire');
            }
        } else {
            $this->competency = new competency(null, false);
        }
    }

    public function check_access() {
        require_capability('mod/euroaspire:addinstance', $this->get_context());
    }

    public function setup_page($action = null) {
        global $PAGE;

        if ($action == 'edit') {
            if ($this->competency->id) {
                $title = get_string('editcomp', 'mod_euroaspire');
            } else {
                $title = get_string('newcomp', 'mod_euroaspire');
            }
        } else {
            $title = get_string($action.'comp', 'mod_euroaspire');
        }

        $PAGE->set_title($title);
        $PAGE->set_heading($title);

        $PAGE->navbar->add($title);
    }

    /**
     * @return competency
     */
    public function get_competency() {
        return $this->competency;
    }

    /**
     * @return competency[]
     */
    public function get_competencies() {
        if ($this->competencies === null) {
            $this->competencies = competency::fetch_all(array('euroaspireid' => $this->euroaspire->id));
        }
        return $this->competencies;
    }

    /**
     * Trigger an event.
     *
     * @param string $type
     * @throws \coding_exception
     */
    protected function trigger_event($type) {
        $params = array(
            'context' => $this->get_context(),
        );
        $params['objectid'] = $this->competency->id;

        switch ($type) {
            case 'updated':
                $event = \mod_euroaspire\event\competency_updated::create($params);
                break;
            default:
                throw new \coding_exception("Invalid event type '$type'");
        }

        // Trigger the event.
        $event->add_record_snapshot('euroaspire_competency', $this->competency->get_record_data());
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('course', $this->course);
        $event->add_record_snapshot('euroaspire', $this->euroaspire);
        $event->trigger();
    }

    // -------------------------------------
    // List handling.
    // -------------------------------------

    public function get_list_url() {
        return new moodle_url('/mod/euroaspire/admin/complist.php', array('cmid' => $this->cm->id));
    }

    /**
     * @param competency $comp
     * @return moodle_url[] - action name => link
     */
    public function get_competency_actions(competency $comp) {
        $comps = $this->get_competencies();
        $firstcomp = reset($comps);
        $lastcomp = end($comps);

        $ret = array();
        $ret['edit'] = new moodle_url('/mod/euroaspire/admin/compedit.php', array(
            'cmid' => $this->cm->id,
            'id' => $comp->id
        ));
        if ($comp->id != $firstcomp->id) {
            $ret['up'] = new moodle_url('/mod/euroaspire/admin/complist.php', array(
                'cmid' => $this->cm->id,
                'moveup' => $comp->id,
                'sesskey' => sesskey()
            ));
        } else {
            $ret['up'] = null;
        }
        if ($comp->id != $lastcomp->id) {
            $ret['down'] = new moodle_url('/mod/euroaspire/admin/complist.php', array(
                'cmid' => $this->cm->id,
                'movedown' => $comp->id,
                'sesskey' => sesskey()
            ));
        } else {
            $ret['down'] = null;
        }
        if ($comp->can_delete()) {
            $ret['delete'] = new moodle_url('/mod/euroaspire/admin/compdelete.php', array(
                'cmid' => $this->cm->id,
                'id' => $comp->id
            ));
        } else {
            $ret['delete'] = null;
        }
        return $ret;
    }

    /**
     * @return action
     */
    public function get_list_actions() {
        $ret = array();
        $highlight = false;
        if (!$this->get_competencies()) {
            $highlight = true;
        }
        $ret['newcomp'] = new action(
            new moodle_url('/mod/euroaspire/admin/compedit.php', array('cmid' => $this->cm->id)),
            'newcomp',
            $highlight
        );
        $ret['editlistdim'] = new action(
            new moodle_url('/mod/euroaspire/admin/dimlist.php', array('cmid' => $this->cm->id)),
            'editlistdim'
        );
        $ret['backintro'] = new action(
            $this->get_main_url(),
            'backintro'
        );
        return $ret;
    }

    public function process_list() {
        $moveup = optional_param('moveup', null, PARAM_INT);
        $movedown = optional_param('movedown', null, PARAM_INT);
        if ($moveup) {
            require_sesskey();
            $this->competency = new competency(array('id' => $moveup, 'euroaspireid' => $this->euroaspire->id));
            $this->competency->move_up();
            $this->trigger_event('updated');
            redirect($this->get_list_url());
        }
        if ($movedown) {
            require_sesskey();
            $this->competency = new competency(array('id' => $movedown, 'euroaspireid' => $this->euroaspire->id));
            $this->competency->move_down();
            $this->trigger_event('updated');
            redirect($this->get_list_url());
        }
    }

    // -------------------------------------
    // Edit handling.
    // -------------------------------------

    public function process_edit() {
        // Set up the form.
        $context = $this->get_context();
        $editoropts = array(
            'trusttext' => false, 'subdirs' => false, 'maxfiles' => 0,
            'maxbytes' => 0, 'context' => $context
        );
        $custom = array('editoropts' => $editoropts);
        $this->form = new competency_form(null, $custom);

        $toform = $this->competency->get_record_data();
        $toform = file_prepare_standard_editor($toform, 'description', $editoropts);
        $toform->cmid = $this->cm->id;
        $this->form->set_data($toform);

        // Form cancelled.
        if ($this->form->is_cancelled()) {
            redirect($this->get_list_url());
        }

        // Form submitted.
        if ($fromform = $this->form->get_data()) {
            // No files, so itemid not needed, so can update from the editor without making sure the competency is created first.
            $fromform = file_postupdate_standard_editor($fromform, 'description', $editoropts, $context);
            $this->competency->set_properties($this->competency, $fromform);
            $this->competency->euroaspireid = $this->euroaspire->id;

            if ($this->competency->id) {
                $this->competency->update();
            } else {
                $this->competency->insert();
            }

            $this->trigger_event('updated');

            redirect($this->get_list_url());
        }
    }

    // -------------------------------------
    // Delete handling.
    // -------------------------------------

    public function get_confirm_delete_url() {
        return new moodle_url('/mod/euroaspire/admin/compdelete.php', array(
            'cmid' => $this->cm->id,
            'id' => $this->competency->id,
            'confirm' => 1, 'sesskey' => sesskey()
        ));
    }

    public function process_delete() {
        if (!$this->competency->can_delete()) {
            throw new \moodle_exception('cannotdeletecompetency', 'mod_euroaspire');
        }
        if (optional_param('confirm', false, PARAM_BOOL)) {
            require_sesskey();
            $this->trigger_event('updated');
            $this->competency->delete();
            redirect($this->get_list_url());
        }
    }
}