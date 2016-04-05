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
 * Manage the dimensions
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_euroaspire\local\control;

use mod_euroaspire\local\data\dimension;
use mod_euroaspire\local\form\dimension_form;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class dimmanager extends basemanager {
    protected $dimension;
    protected $dimensions = null;

    public function __construct($course, $cm, $euroaspire, $dimid = null) {
        parent::__construct($course, $cm, $euroaspire);
        if ($dimid) {
            $this->dimension = new dimension(array('id' => $dimid));
            if ($this->dimension->euroaspireid != $euroaspire->id) {
                throw new \moodle_exception('dimensioninstancemismatch', 'mod_euroaspire');
            }
        } else {
            $this->dimension = new dimension(null, false);
        }
    }

    public function check_access() {
        require_capability('mod/euroaspire:addinstance', $this->get_context());
    }

    public function setup_page($action = null) {
        global $PAGE;

        if ($action == 'edit') {
            if ($this->dimension->id) {
                $title = get_string('editdim', 'mod_euroaspire');
            } else {
                $title = get_string('newdim', 'mod_euroaspire');
            }
        } else {
            $title = get_string($action.'dim', 'mod_euroaspire');
        }

        $PAGE->set_title($title);
        $PAGE->set_heading($title);

        $PAGE->navbar->add($title);
    }

    /**
     * @return dimension
     */
    public function get_dimension() {
        return $this->dimension;
    }

    /**
     * @return dimension[]
     */
    public function get_dimensions() {
        if ($this->dimensions === null) {
            $this->dimensions = dimension::fetch_all(array('euroaspireid' => $this->euroaspire->id));
        }
        return $this->dimensions;
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
        $params['objectid'] = $this->dimension->id;

        switch ($type) {
            case 'updated':
                $event = \mod_euroaspire\event\dimension_updated::create($params);
                break;
            default:
                throw new \coding_exception("Invalid event type '$type'");
        }

        // Trigger the event.
        $event->add_record_snapshot('euroaspire_dimension', $this->dimension->get_record_data());
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('course', $this->course);
        $event->add_record_snapshot('euroaspire', $this->euroaspire);
        $event->trigger();
    }

    // -------------------------------------
    // List handling.
    // -------------------------------------

    public function get_list_url() {
        return new moodle_url('/mod/euroaspire/admin/dimlist.php', array('cmid' => $this->cm->id));
    }

    /**
     * @param dimension $dim
     * @return moodle_url[] - action name => link
     */
    public function get_dimension_actions(dimension $dim) {
        $dims = $this->get_dimensions();
        $firstdim = reset($dims);
        $lastdim = end($dims);

        $ret = array();
        $ret['edit'] = new moodle_url('/mod/euroaspire/admin/dimedit.php', array('cmid' => $this->cm->id,
                                                                                 'id' => $dim->id));
        if ($dim->id != $firstdim->id) {
            $ret['up'] = new moodle_url('/mod/euroaspire/admin/dimlist.php', array(
                'cmid' => $this->cm->id,
                'moveup' => $dim->id,
                'sesskey' => sesskey()
            ));
        } else {
            $ret['up'] = null;
        }
        if ($dim->id != $lastdim->id) {
            $ret['down'] = new moodle_url('/mod/euroaspire/admin/dimlist.php', array(
                'cmid' => $this->cm->id,
                'movedown' => $dim->id,
                'sesskey' => sesskey()
            ));
        } else {
            $ret['down'] = null;
        }
        if ($dim->can_delete()) {
            $ret['delete'] = new moodle_url('/mod/euroaspire/admin/dimdelete.php', array(
                'cmid' => $this->cm->id,
                'id' => $dim->id
            ));
        } else {
            $ret['delete'] = null;
        }
        return $ret;
    }

    /**
     * @return moodle_url[] - action name => link
     */
    public function get_list_actions() {
        $ret = array();
        $highlight = false;
        if (!$this->get_dimensions()) {
            $highlight = true;
        }
        $ret['newdim'] = new action(
            new moodle_url('/mod/euroaspire/admin/dimedit.php', array('cmid' => $this->cm->id)),
            'newdim',
            $highlight
        );
        $ret['editlistcomp'] = new action(
            new moodle_url('/mod/euroaspire/admin/complist.php', array('cmid' => $this->cm->id)),
            'editlistcomp'
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
            $this->dimension = new dimension(array('id' => $moveup, 'euroaspireid' => $this->euroaspire->id));
            $this->dimension->move_up();
            $this->trigger_event('updated');
            redirect($this->get_list_url());
        }
        if ($movedown) {
            require_sesskey();
            $this->dimension = new dimension(array('id' => $movedown, 'euroaspireid' => $this->euroaspire->id));
            $this->dimension->move_down();
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
        $this->form = new dimension_form(null, $custom);

        $toform = $this->dimension->get_record_data();
        $toform->cmid = $this->cm->id;
        $this->form->set_data($toform);

        // Form cancelled.
        if ($this->form->is_cancelled()) {
            redirect($this->get_list_url());
        }

        // Form submitted.
        if ($fromform = $this->form->get_data()) {
            $this->dimension->set_properties($this->dimension, $fromform);
            $this->dimension->euroaspireid = $this->euroaspire->id;

            if ($this->dimension->id) {
                $this->dimension->update();
            } else {
                $this->dimension->insert();
            }
            $this->trigger_event('updated');

            redirect($this->get_list_url());
        }
    }

    // -------------------------------------
    // Delete handling.
    // -------------------------------------

    public function get_confirm_delete_url() {
        return new moodle_url('/mod/euroaspire/admin/dimdelete.php', array(
            'cmid' => $this->cm->id,
            'id' => $this->dimension->id,
            'confirm' => 1, 'sesskey' => sesskey()
        ));
    }

    public function process_delete() {
        if (!$this->dimension->can_delete()) {
            throw new \moodle_exception('cannotdeletedimension', 'mod_euroaspire');
        }
        if (optional_param('confirm', false, PARAM_BOOL)) {
            require_sesskey();
            $this->trigger_event('updated');
            $this->dimension->delete();
            redirect($this->get_list_url());
        }
    }
}