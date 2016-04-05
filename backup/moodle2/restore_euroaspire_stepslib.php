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
 * @package    mod_euroaspire
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_euroaspire_activity_task
 */

/**
 * Structure step to restore one euroaspire activity
 */
class restore_euroaspire_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('euroaspire', '/activity/euroaspire');
        $paths[] = new restore_path_element('euroaspire_competency', '/activity/euroaspire/competencies/competency');
        $paths[] = new restore_path_element('euroaspire_dimension', '/activity/euroaspire/dimensions/dimension');
        if ($userinfo) {
            $paths[] = new restore_path_element('euroaspire_submission',
                                                '/activity/euroaspire/submissions/submission');
            $paths[] = new restore_path_element('euroaspire_submission_item',
                                                '/activity/euroaspire/submissions/submission/items/item');
            $paths[] = new restore_path_element('euroaspire_submission_map',
                                                '/activity/euroaspire/submissions/submission/maps/map');
            $paths[] = new restore_path_element('euroaspire_submission_grade',
                                                '/activity/euroaspire/submissions/submission/grades/grade');
            $paths[] = new restore_path_element('euroaspire_submission_comment',
                                                '/activity/euroaspire/submissions/submission/comments/comment');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_euroaspire($euroaspire) {
        global $DB;

        $euroaspire = (object)$euroaspire;
        $oldid = $euroaspire->id;
        $euroaspire->course = $this->get_courseid();

        // Insert the euroaspire record.
        $newitemid = $DB->insert_record('euroaspire', $euroaspire);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_euroaspire_competency($competency) {
        global $DB;

        $competency = (object)$competency;
        $oldid = $competency->id;

        $competency->euroaspireid = $this->get_new_parentid('euroaspire');

        // Insert the euroaspire_competency record.
        $newitemid = $DB->insert_record('euroaspire_competency', $competency);
        $this->set_mapping('euroaspire_competency', $oldid, $newitemid, false); // No files associated.
    }

    protected function process_euroaspire_dimension($dimension) {
        global $DB;

        $dimension = (object)$dimension;
        $oldid = $dimension->id;

        $dimension->euroaspireid = $this->get_new_parentid('euroaspire');

        // Insert the euroaspire_dimension record.
        $newitemid = $DB->insert_record('euroaspire_dimension', $dimension);
        $this->set_mapping('euroaspire_dimension', $oldid, $newitemid, false); // No files associated.
    }

    protected function process_euroaspire_submission($submission) {
        global $DB;

        $submission = (object)$submission;
        $oldid = $submission->id;

        $submission->userid = $this->get_mappingid('user', $submission->userid);
        $submission->euroaspireid = $this->get_new_parentid('euroaspire');

        // Insert the euroaspire_records record.
        $newitemid = $DB->insert_record('euroaspire_submission', $submission);
        $this->set_mapping('euroaspire_submission', $oldid, $newitemid, true); // Has files.
    }

    protected function process_euroaspire_submission_item($item) {
        global $DB;

        $item = (object)$item;
        $oldid = $item->id;

        $item->submissionid = $this->get_new_parentid('euroaspire_submission');

        // Insert the euroaspire_content record.
        $newitemid = $DB->insert_record('euroaspire_submission_item', $item);
        $this->set_mapping('euroaspire_submission_item', $oldid, $newitemid, false); // No files associated.
    }

    protected function process_euroaspire_submission_map($map) {
        global $DB;

        $map = (object)$map;
        $oldid = $map->id;

        $map->competencyid = $this->get_mappingid('euroaspire_competency', $map->competencyid);
        $map->dimensionid = $this->get_mappingid('euroaspire_dimension', $map->dimensionid);
        $map->itemid = $this->get_mappingid('euroaspire_submission_item', $map->itemid);
        $map->submissionid = $this->get_new_parentid('euroaspire_submission');

        // Insert the euroaspire_content record.
        $newitemid = $DB->insert_record('euroaspire_submission_map', $map);
        $this->set_mapping('euroaspire_submission_map', $oldid, $newitemid, false); // No files associated.
    }

    protected function process_euroaspire_submission_grade($grade) {
        global $DB;

        $grade = (object)$grade;
        $oldid = $grade->id;

        $grade->competencyid = $this->get_mappingid('euroaspire_competency', $grade->competencyid);
        $grade->dimensionid = $this->get_mappingid('euroaspire_dimension', $grade->dimensionid);
        $grade->submissionid = $this->get_new_parentid('euroaspire_submission');

        // Insert the euroaspire_content record.
        $newitemid = $DB->insert_record('euroaspire_submission_grade', $grade);
        $this->set_mapping('euroaspire_submission_grade', $oldid, $newitemid, false); // No files associated.
    }

    protected function process_euroaspire_submission_comment($comment) {
        global $DB;

        $comment = (object)$comment;
        $oldid = $comment->id;

        $comment->dimensionid = $this->get_mappingid('euroaspire_dimension', $comment->dimensionid);
        $comment->submissionid = $this->get_new_parentid('euroaspire_submission');

        // Insert the euroaspire_submission_comnt record.
        $newitemid = $DB->insert_record('euroaspire_submission_comnt', $comment);
        $this->set_mapping('euroaspire_submission_comnt', $oldid, $newitemid, false); // No files associated.
    }

    protected function after_execute() {
        // Add euroaspire related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_euroaspire', 'intro', null);
        // Add content related files, matching by itemname (euroaspire_content).
        $this->add_related_files('mod_euroaspire', 'submission_item', 'euroaspire_submission');
        // Fix the pathnamehash values for all submissions.
        $this->fix_pathnamehash();
    }

    protected function fix_pathnamehash() {
        global $DB;

        $contextid = $this->task->get_contextid();
        $euroaspireid = $this->task->get_activityid();
        $submissionids = $DB->get_fieldset_select('euroaspire_submission', 'id', 'euroaspireid = ?', array($euroaspireid));
        $fs = get_file_storage();
        foreach ($submissionids as $submissionid) {
            $select = 'submissionid = ? AND pathnamehash IS NOT NULL';
            $items = $DB->get_records_select('euroaspire_submission_item', $select, array($submissionid), '', 'id, pathnamehash');
            if (!$items) {
                continue;
            }
            $files = $fs->get_area_files($contextid, 'mod_euroaspire', 'submission_item', $submissionid, '', false);
            $filesbyfnhash = array();
            foreach ($files as $file) {
                $filenamehash = sha1($file->get_filepath().$file->get_filename());
                $filesbyfnhash[$filenamehash] = $file->get_pathnamehash();
            }
            unset($files);
            foreach ($items as $item) {
                $fixedpathnamehash = null;
                if (isset($filesbyfnhash[$item->pathnamehash])) {
                    $fixedpathnamehash = $filesbyfnhash[$item->pathnamehash];
                }
                $DB->set_field('euroaspire_submission_item', 'pathnamehash', $fixedpathnamehash, array('id' => $item->id));
            }
        }
    }
}
