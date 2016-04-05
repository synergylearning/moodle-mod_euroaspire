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
 * Define all the backup steps that will be used by the backup_euroaspire_activity_task
 */

/**
 * Define the complete euroaspire structure for backup, with file and id annotations
 */
class backup_euroaspire_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $euroaspire = new backup_nested_element('euroaspire', array('id'), array(
            'name', 'intro', 'introformat', 'timecreated', 'timemodified', 'passtocomplete'));

        $competencies = new backup_nested_element('competencies');

        $competency = new backup_nested_element('competency', array('id'), array(
            'title', 'description', 'descriptionformat', 'sortorder'));

        $dimensions = new backup_nested_element('dimensions');

        $dimension = new backup_nested_element('dimension', array('id'), array(
            'title', 'sortorder'));

        $submissions = new backup_nested_element('submissions');

        $submission = new backup_nested_element('submission', array('id'), array(
            'userid', 'grade', 'timecreated', 'timemodified', 'timegraded', 'usercomment'));

        $items = new backup_nested_element('items');

        $item = new euroaspire_submission_item_backup_nested_element('item', array('id'), array(
            'title', 'url', 'pathnamehash', 'timecreated'));

        $maps = new backup_nested_element('maps');

        $map = new backup_nested_element('map', array('id'), array(
            'competencyid', 'dimensionid', 'itemid'));

        $grades = new backup_nested_element('grades');

        $grade = new backup_nested_element('grade', array('id'), array(
            'competencyid', 'dimensionid', 'grade', 'comment', 'graderid', 'timecreated', 'timemodified'));

        $comments = new backup_nested_element('comments');

        $comment = new backup_nested_element('comment', array('id'), array('dimensionid', 'comment'));

        // Build the tree.
        $euroaspire->add_child($competencies);
        $competencies->add_child($competency);

        $euroaspire->add_child($dimensions);
        $dimensions->add_child($dimension);

        $euroaspire->add_child($submissions);
        $submissions->add_child($submission);

        $submission->add_child($items);
        $items->add_child($item);

        $submission->add_child($maps);
        $maps->add_child($map);

        $submission->add_child($grades);
        $grades->add_child($grade);

        $submission->add_child($comments);
        $comments->add_child($comment);

        // Define sources.
        $euroaspire->set_source_table('euroaspire', array('id' => backup::VAR_ACTIVITYID));

        $competency->set_source_table('euroaspire_competency', array('euroaspireid' => backup::VAR_ACTIVITYID));

        $dimension->set_source_table('euroaspire_dimension', array('euroaspireid' => backup::VAR_ACTIVITYID));

        if ($userinfo) {
            $submission->set_source_table('euroaspire_submission', array('euroaspireid' => backup::VAR_ACTIVITYID));

            $item->set_source_table('euroaspire_submission_item', array('submissionid' => backup::VAR_PARENTID));

            $map->set_source_table('euroaspire_submission_map', array('submissionid' => backup::VAR_PARENTID));

            $grade->set_source_table('euroaspire_submission_grade', array('submissionid' => backup::VAR_PARENTID));

            $comment->set_source_table('euroaspire_submission_comnt', array('submissionid' => backup::VAR_PARENTID));
        }

        // Define id annotations.
        $submission->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'graderid');

        // Define file annotations.
        $euroaspire->annotate_files('mod_euroaspire', 'intro', null); // This file area hasn't itemid.
        $submission->annotate_files('mod_euroaspire', 'submission_item', 'id'); // By submission->id.

        // Return the root element (euroaspire), wrapped into standard activity structure.
        return $this->prepare_activity_structure($euroaspire);
    }
}

// Overridden to rewrite the 'pathnamehash' field to something that can be restored successfully.
class euroaspire_submission_item_backup_nested_element extends backup_nested_element {
    public function fill_values($values) {
        if (!empty($values->pathnamehash)) {
            $values->pathnamehash = $this->get_filenamehash_from_pathnamehash($values->submissionid, $values->pathnamehash);
        }
        parent::fill_values($values);
    }

    protected function get_filenamehash_from_pathnamehash($submissionid, $pathnamehash) {
        global $DB;
        static $cachedfiles = array();
        static $cachedsubmissionid = null;

        if ($cachedsubmissionid != $submissionid) {
            $cachedsubmissionid = $submissionid;
            $sql = 'SELECT ctx.id
                      FROM {context} ctx
                      JOIN {course_modules} cm ON cm.id = ctx.instanceid
                      JOIN {modules} m ON m.id = cm.module
                      JOIN {euroaspire_submission} s ON s.euroaspireid = cm.instance
                     WHERE ctx.contextlevel = :contextmodule AND s.id = :submissionid AND m.name = :euroaspire';
            $params = array('contextmodule' => CONTEXT_MODULE, 'submissionid' => $submissionid, 'euroaspire' => 'euroaspire');
            $contextid = $DB->get_field_sql($sql, $params);
            $fs = get_file_storage();
            $cachedfiles = $fs->get_area_files($contextid, 'mod_euroaspire', 'submission_item', $submissionid, '', false);
        }
        if (!isset($cachedfiles[$pathnamehash])) {
            return null;
        }
        /** @var stored_file $file */
        $file = $cachedfiles[$pathnamehash];
        return sha1($file->get_filepath().$file->get_filename());
    }
}
