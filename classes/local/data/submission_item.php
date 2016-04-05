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
 * A single item (file or url) submitted by a user.
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_euroaspire\local\data;

use data_object;
use moodle_url;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/completion/data_object.php');

class submission_item extends data_object {

    public $table = 'euroaspire_submission_item';
    public $required_fields = array('id', 'submissionid', 'title', 'url', 'pathnamehash', 'timecreated');
    public $text_fields = array('title', 'url');
    public $optional_fields = array();

    /** @var int */
    public $submissionid;
    /** @var string */
    public $title;
    /** @var string */
    public $url;
    /** @var string */
    public $pathnamehash;
    /** @var int */
    public $timecreated;

    const FILEAREA = 'submission_item';

    /** @var submission */
    protected $submission = null;

    /** @var submission_map[] */
    protected $maps = array();

    /**
     * Load the single matching record from the database.
     *
     * @param array $params
     * @return submission_item
     */
    public static function fetch($params) {
        return self::fetch_helper('euroaspire_submission_item', __CLASS__, $params);
    }

    /**
     * Load all matching records from the database.
     * @param array $params
     * @return submission_item[]
     */
    public static function fetch_all($params) {
        return self::fetch_all_helper('euroaspire_submission_item', __CLASS__, $params);
    }

    /**
     * Send back the file, if it is associated with a submission_item, otherwise just return.
     *
     * @param object $course
     * @param \cm_info $cm
     * @param \context $context
     * @param string $filearea
     * @param array $args
     * @param bool $forcedownload
     * @param array $options
     */
    public static function send_file($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
        if ($filearea != self::FILEAREA) {
            return; // Not related to a user submission.
        }

        $submissionitemid = array_shift($args);
        $filename = array_pop($args);
        $filepath = '/'.implode('/', $args);
        if ($filepath != '/') {
            $filepath .= '/';
        }

        $submissionitem = new submission_item(array('id' => $submissionitemid));
        if (!$submissionitem) {
            return; // Submission does not exist.
        }

        $submission = $submissionitem->get_submission();
        if (!$submission->is_mine()) {
            if (!has_capability('mod/euroaspire:grade', $context)) {
                return; // Not my submission + not able to grade => no access to the file.
            }
        }

        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'mod_euroaspire', $filearea, $submissionitem->id, $filepath, $filename);
        if (!$file) {
            return;
        }

        send_stored_file($file, null, 0, $forcedownload, $options);
    }

    /**
     * Is this item a URL (instead of a file).
     *
     * @return bool
     */
    public function is_url() {
        return ($this->url !== null);
    }

    /**
     * Is this item a file (instead of a URL).
     *
     * @return bool
     */
    public function is_file() {
        return ($this->pathnamehash !== null);
    }

    /**
     * Set the submission that this item belongs to.
     * @param submission $submission
     */
    public function set_submission(submission $submission) {
        $this->submission = $submission;
    }

    /**
     * Get the submission that this item belongs to
     */
    public function get_submission() {
        if ($this->submission === null && $this->submissionid) {
            $this->submission = new submission(array('id' => $this->submissionid));
        }
        return $this->submission;
    }

    private function check_fields() {
        if (empty($this->url) && empty($this->pathnamehash)) {
            throw new \coding_exception('Must specify url or pathnamehash');
        }
        if (!empty($this->url) && !empty($this->pathnamehash)) {
            throw new \coding_exception('Cannot specify both url and pathnamehas');
        }
        if (!$this->title) {
            if ($this->url) {
                $this->title = $this->url; // Better than leaving it blank.
            } else {
                throw new \coding_exception('Must set the title for \'file\' items - should be the name of the file');
            }
        }
    }

    public function insert() {
        $this->timecreated = time();
        $this->check_fields();
        return parent::insert();
    }

    public function update() {
        $this->check_fields();
        return parent::update();
    }

    public function delete($removemap = true) {
        if ($removemap) {
            if ($maps = submission_map::fetch_all(array('itemid' => $this->id))) {
                foreach ($maps as $map) {
                    $map->delete();
                }
            }
        }
        return parent::delete();
    }

    /**
     * Get a link to this item's content - either the URL (for url items) or a link to the embedded file.
     *
     * @return string|moodle_url|null
     */
    public function get_link_url() {
        if ($this->url) {
            return $this->url;
        }
        $fs = get_file_storage();
        $file = $fs->get_file_by_hash($this->pathnamehash);
        if (!$file) {
            return null;
        }
        return moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                                               $file->get_itemid(), $file->get_filepath(), $file->get_filename(), true);
    }

    /**
     * Store a map object within the submission object
     * (Note - this is for convenience, it does not do anything to the database).
     *
     * @param submission_map $map
     */
    public function store_mapping(submission_map $map) {
        $this->maps[] = $map;
    }

    /**
     * Retrieve a map object, saved using store_mapping.
     * Returns null if no such mapping exists.
     *
     * @param competency $competency
     * @param dimension $dimension
     * @return submission_map | null
     */
    public function retrieve_mapping(competency $competency, dimension $dimension) {
        foreach ($this->maps as $map) {
            if ($map->competencyid == $competency->id && $map->dimensionid == $dimension->id) {
                return $map;
            }
        }
        return null;
    }

    /**
     * Has this item been mapped onto any competencies / dimensions?
     * WARNING: only valid is submission->get_map() has been called (which calls store_mapping).
     *
     * @return bool
     */
    public function has_any_mappings() {
        return !empty($this->maps);
    }
}