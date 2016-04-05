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
 * Output the parts of the page.
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_euroaspire\local\control\assessmentmanager;
use mod_euroaspire\local\control\compmanager;
use mod_euroaspire\local\control\dimmanager;
use mod_euroaspire\local\data\competency;
use mod_euroaspire\local\data\submission;
use mod_euroaspire\local\data\submission_grade;
use mod_euroaspire\local\data\submission_item;

defined('MOODLE_INTERNAL') || die();

class mod_euroaspire_renderer extends plugin_renderer_base {

    protected function actions($actions) {
        $out = '';
        foreach ($actions as $action) {
            $button = new single_button($action->url, get_string($action->strname, 'mod_euroaspire'), 'get');
            if ($action->highlight) {
                $button->class .= ' highlightbutton';
            }
            $out .= $this->output->render($button);
        }
        return $out;
    }

    protected function action_buttons() {
        $buttons = '';
        $buttons .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'submitbutton',
                                                          'id' => 'id_submitbutton',
                                                          'value' => get_string('savechanges')));
        $buttons .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'cancel',
                                                          'class' => 'btn-cancel', 'id' => 'id_cancel',
                                                          'value' => get_string('cancel')));

        $buttons = html_writer::div($buttons, 'fitem fitem_actionbuttons fitem_fgroup',
                                    array('id' => 'fgroup_id_buttonar'));

        return $buttons;
    }

    protected function grading_action_buttons() {
        $buttons = '';
        $buttons .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'submitbutton',
                                                          'id' => 'id_submitbutton',
                                                          'value' => get_string('save', 'mod_euroaspire')));
        $buttons .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'submitnextbutton',
                                                          'id' => 'id_submitnextbutton',
                                                          'value' => get_string('savenext', 'mod_euroaspire')));
        $buttons .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'nextbutton',
                                                          'id' => 'id_nextbutton',
                                                          'value' => get_string('next')));
        $buttons .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'cancel',
                                                          'class' => 'btn-cancel', 'id' => 'id_cancel',
                                                          'value' => get_string('cancel')));
        $buttons .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'exportcomments',
                                                          'id' => 'id_exportcomments',
                                                          'value' => get_string('exportcomments', 'mod_euroaspire')));

        $buttons = html_writer::div($buttons, 'fitem fitem_actionbuttons fitem_fgroup',
                                    array('id' => 'fgroup_id_buttonar'));

        return $buttons;
    }

    // -------------------------------------
    // Managing competencies.
    // -------------------------------------
    public function competency_list(compmanager $manager) {
        $out = '';

        // Output list actions.
        $out .= $this->actions($manager->get_list_actions());

        // Output table of competencies.
        $competencies = $manager->get_competencies();
        if (!$competencies) {
            $out .= html_writer::tag('p', get_string('nocompetencies', 'mod_euroaspire'));
        } else {
            $table = new html_table();
            $table->head = array(
                get_string('competency', 'mod_euroaspire'),
                get_string('action', 'mod_euroaspire'),
            );

            foreach ($competencies as $competency) {
                $actions = array();
                foreach ($manager->get_competency_actions($competency) as $actionname => $url) {
                    if ($url) {
                        $linktext = get_string($actionname.'comp', 'mod_euroaspire');
                        $linkicon = $this->output->pix_icon('t/'.$actionname, $linktext);
                        $actions[] = html_writer::link($url, $linkicon);
                    } else {
                        $linkicon = $this->output->pix_icon('spacer', '');
                        $actions[] = html_writer::span($linkicon, 'actiondisabled');
                    }
                }

                $table->data[] = array(
                    $this->format_competency_name($competency),
                    implode(' ', $actions),
                );
            }
            $out .= html_writer::table($table);
        }

        return $out;
    }

    public function competency_edit(compmanager $manager) {
        $out = '';
        $out .= $manager->get_form()->render();
        return $out;
    }

    public function competency_delete(compmanager $manager) {
        $out = '';
        $msg = get_string('confirmdeletecomp', 'mod_euroaspire', $manager->get_competency());
        $out .= $this->output->confirm($msg, $manager->get_confirm_delete_url(), $manager->get_list_url());
        return $out;
    }

    // -------------------------------------
    // Managing dimensions.
    // -------------------------------------
    public function dimension_list(dimmanager $manager) {
        $out = '';

        // Output list actions.
        $out .= $this->actions($manager->get_list_actions());

        // Output table of dimensions.
        $dimensions = $manager->get_dimensions();
        if (!$dimensions) {
            $out .= html_writer::tag('p', get_string('nodimensions', 'mod_euroaspire'));
        } else {
            $table = new html_table();
            $table->head = array(
                get_string('dimension', 'mod_euroaspire'),
                get_string('action', 'mod_euroaspire'),
            );

            foreach ($dimensions as $dimension) {
                $actions = array();
                foreach ($manager->get_dimension_actions($dimension) as $actionname => $url) {
                    if ($url) {
                        $linktext = get_string($actionname.'dim', 'mod_euroaspire');
                        $linkicon = $this->output->pix_icon('t/'.$actionname, $linktext);
                        $actions[] = html_writer::link($url, $linkicon);
                    } else {
                        $linkicon = $this->output->pix_icon('spacer', '');
                        $actions[] = html_writer::span($linkicon, 'actiondisabled');
                    }
                }

                $table->data[] = array(
                    format_string($dimension->title),
                    implode(' ', $actions),
                );
            }
            $out .= html_writer::table($table);
        }

        return $out;
    }

    public function dimension_edit(dimmanager $manager) {
        $out = '';
        $out .= $manager->get_form()->render();
        return $out;
    }

    public function dimension_delete(dimmanager $manager) {
        $out = '';
        $msg = get_string('confirmdeletedim', 'mod_euroaspire', $manager->get_dimension());
        $out .= $this->output->confirm($msg, $manager->get_confirm_delete_url(), $manager->get_list_url());
        return $out;
    }

    // -------------------------------------
    // Submitting assessments.
    // -------------------------------------

    protected function notification_message(assessmentmanager $manager) {
        list($msg, $type) = $manager->get_notification_message();
        if ($msg) {
            return html_writer::div($msg, 'notification '.$type);
        }
        return '';
    }

    // -------------------------------------
    // Intro page.
    // -------------------------------------

    public function assessment_intro(assessmentmanager $manager) {
        $out = '';
        $out .= $this->output->heading($manager->get_formatted_title());
        $out .= $manager->get_formatted_intro();

        if (!$actions = $manager->get_actions()) {
            if ($manager->fully_configured()) {
                $out .= html_writer::tag('p', get_string('noactions', 'mod_euroaspire'));
            }
        } else {
            $out .= $this->actions($actions);
        }

        if ($manager->fully_configured()) {
            if ($manager->can_submit()) {
                $grade = $manager->get_submission()->get_overall_grade_string();
                $out .= html_writer::div(get_string('yourgrade', 'mod_euroaspire', $grade), 'usergrade');
                $out .= $this->my_grade_table($manager);
            }

        } else {
            $out .= html_writeR::tag('p', get_string('notconfigured', 'mod_euroaspire'));
        }

        return $out;
    }

    protected function my_grade_table(assessmentmanager $manager) {
        $out = '';

        $table = new html_table();
        $table->head = array('');
        foreach ($manager->get_dimensions() as $dimension) {
            $table->head[] = format_string($dimension->title);
        }
        $submission = $manager->get_submission();
        foreach ($manager->get_competencies() as $competency) {
            $row = array($this->format_competency_name($competency));
            foreach ($manager->get_dimensions() as $dimension) {
                // A list of currently-mapped items to the cell.
                $links = array();
                $items = $submission->get_mapped_items($competency, $dimension);
                foreach ($items as $item) {
                    $links[] = html_writer::tag('li', $this->submission_item($item, false), array('data-itemid' => $item->id));
                }

                $cell = '';
                $cell .= html_writer::tag('ul', implode('', $links), array('class' => 'mappeditems'));

                // A hidden input to hold information about the current grade for this cell.
                $grade = $submission->get_grade($competency, $dimension);
                $gradeval = $grade ? $grade->grade : submission_grade::GRADE_NONE;
                $gradecomment = $grade ? s($grade->comment) : '';

                // Grading icons.
                $info = (object)array('competency' => s($competency->title), 'dimension' => s($dimension->title));
                $title = '';
                if ($gradeval == submission_grade::GRADE_PASS) {
                    $title = get_string('passedfor', 'mod_euroaspire', $info);
                } else if ($gradeval == submission_grade::GRADE_FAIL) {
                    $title = get_string('failedfor', 'mod_euroaspire', $info);
                }
                $selected = ($gradeval == submission_grade::GRADE_PASS) ? ' selected' : '';
                $cell .= html_writer::span('&nbsp;', 'gradepass'.$selected, array('title' => $title));

                $selected = ($gradeval == submission_grade::GRADE_FAIL) ? ' selected' : '';
                $cell .= html_writer::span('&nbsp;', 'gradefail'.$selected, array('title' => $title));

                // Feedback area.
                $cell .= html_writer::div($gradecomment, 'gradefeedback');

                $row[] = $cell;
            }
            $table->data[] = $row;
        }

        $out .= html_writer::table($table);
        if ($submission->usercomment) {
            $out .= html_writer::div($submission->formatted_user_comment(), 'usercomment');
        }

        return $out;
    }

    // -------------------------------------
    // Adding evidence.
    // -------------------------------------

    public function assessment_add_evidence(assessmentmanager $manager) {
        $out = '';
        $out .= $this->actions($manager->get_actions());
        $out .= $this->notification_message($manager);
        $out .= $manager->get_form()->render();
        return $out;
    }

    // -------------------------------------
    // Mapping evidence.
    // -------------------------------------

    public function assessment_map_evidence(assessmentmanager $manager) {
        $out = '';
        $out .= $this->actions($manager->get_actions());
        $out .= $this->notification_message($manager);
        $out .= $this->assessment_map_table($manager);

        return $out;
    }

    protected function assessment_map_table(assessmentmanager $manager) {
        $out = '';

        $table = new html_table();
        $table->head = array('');
        foreach ($manager->get_dimensions() as $dimension) {
            $table->head[] = format_string($dimension->title);
        }
        $submission = $manager->get_submission();
        foreach ($manager->get_competencies() as $competency) {
            $row = array($this->format_competency_name($competency));
            foreach ($manager->get_dimensions() as $dimension) {
                // A list of currently-mapped items to the cell (with 'remove' icons).
                $links = array();
                $items = $submission->get_mapped_items($competency, $dimension);
                foreach ($items as $item) {
                    $links[] = html_writer::tag('li', $this->submission_item($item, true), array('data-itemid' => $item->id));
                }

                $cell = '';
                $cell .= html_writer::tag('ul', implode('', $links), array('class' => 'mappeditems'));

                // A hidden input to hold information about the currently-mapped items.
                $attrib = $manager->get_mapped_item_input_attribs($competency, $dimension);
                $cell .= html_writer::empty_tag('input', $attrib);

                // An icon to add a new item to the cell.
                $info = (object)array('competency' => s($competency->title), 'dimension' => s($dimension->title));
                $title = get_string('addmap', 'mod_euroaspire', $info);
                $cell .= html_writer::span('&nbsp;', 'addmap',  array('title' => $title));

                $row[] = $cell;
            }
            $table->data[] = $row;
        }

        // User comments row.
        $row = array('');
        foreach ($manager->get_dimensions() as $dimension) {
            $comment = $submission->get_comment($dimension);
            $comment = $comment ? s($comment->comment) : '';
            $row[] = html_writer::tag('textarea', $comment, array(
                'id' => 'id_dimensioncomment_'.$dimension->id,
                'name' => 'dimensioncomment['.$dimension->id.']',
                'cols' => 20,
                'rows' => 4
            ));
        }
        $table->data[] = $row;

        // Wrap the mapping table in a form, with required hidden inputs + submit button.
        $out .= html_writer::table($table);
        $out .= html_writer::input_hidden_params($manager->get_submit_map_url());

        // Add 'submission comment' elements.
        $label = html_writer::label(get_string('submissioncomment', 'mod_euroaspire'), 'id_usercomment');
        $label = html_writer::div($label, 'fitemtitle');
        $textarea = html_writer::tag('textarea', s($submission->usercomment), array('id' => 'id_usercomment',
                                                                                    'name' => 'usercomment',
                                                                                    'cols' => 70,
                                                                                    'rows' => 5));
        $textarea = html_writer::div($textarea, 'felement ftextarea');
        $out .= html_writer::div($label.$textarea, 'fitem fitem_ftextarea');

        $out .= $this->action_buttons();

        $out = html_writer::tag('form', $out, array('action' => $manager->get_submit_map_url(),
                                                    'method' => 'post', 'id' => 'mapitemform',
                                                    'class' => 'mform'));

        return $out;
    }

    /**
     * Output the content of a single submission item.
     *
     * @param submission_item $item
     * @param bool $removeicon
     * @return string
     * @throws coding_exception
     */
    protected function submission_item(submission_item $item, $removeicon) {
        $link = html_writer::link($item->get_link_url(), format_string($item->title), array('target' => '_blank'));
        $title = get_string('removemap', 'mod_euroaspire');
        if ($removeicon) {
            $link .= '&nbsp;'.html_writer::span('&nbsp;', 'removemap', array('title' => $title, 'data-itemid' => $item->id));
        }
        return $link;
    }

    /**
     * Output the conetents of the pop-up dialogue for selecting items to add.
     *
     * @param assessmentmanager $manager
     * @return string
     */
    public function item_select(assessmentmanager $manager) {
        $out = '';
        list($unmapped, $mapped) = $manager->get_submission()->get_mapped_unmapped_items();
        $list = '';
        foreach ($unmapped as $item) {
            $li = html_writer::tag('button', get_string('additem', 'mod_euroaspire'), array('class' => 'selectitem',
                                                                                            'data-itemid' => $item->id));
            $li .= html_writer::span($this->submission_item($item, true), 'itemcontent');
            $list .= html_writer::tag('li', $li, array('id' => 'selectitem-'.$item->id));
        }

        $out .= $this->output->heading(get_string('unmappeditems', 'mod_euroaspire'), 3);
        $out .= html_writer::tag('ul', $list, array('class' => 'unmapped'));

        $list = '';
        foreach ($mapped as $item) {
            $li = html_writer::tag('button', get_string('additem', 'mod_euroaspire'), array('class' => 'selectitem',
                                                                                            'data-itemid' => $item->id));
            $li .= html_writer::span($this->submission_item($item, true), 'itemcontent');
            $list .= html_writer::tag('li', $li, array('id' => 'selectitem-'.$item->id));
        }

        $out .= $this->output->heading(get_string('mappeditems', 'mod_euroaspire'), 3);
        $out .= html_writer::tag('ul', $list, array('class' => 'mapped'));

        $out = html_writer::div($out, 'itemselect');

        return $out;
    }

    protected function format_competency_name(competency $competency) {
        $out = '';
        $out .= format_string($competency->title);
        if ($icon = $competency->get_help_icon()) {
            $out .= '&nbsp;'.$this->render($icon);
        }
        return $out;
    }

    /**
     * Heavily based on render_help_icon (tweaked the alt/title text and different desitination URL).
     *
     * @param competency_help_icon $helpicon
     * @return string
     */
    protected function render_competency_help_icon(competency_help_icon $helpicon) {
        global $CFG;

        // First get the help image icon.
        $src = $this->pix_url('i/info');

        $title = get_string($helpicon->identifier, $helpicon->component);

        $alt = trim($title, ". \t");

        $attributes = array('src' => $src, 'alt' => $alt, 'class' => 'iconhelp');
        $output = html_writer::empty_tag('img', $attributes);

        // Add the link text if given.
        if (!empty($helpicon->linktext)) {
            // The spacing has to be done through CSS.
            $output .= $helpicon->linktext;
        }

        // Now create the link around it - we need https on loginhttps pages.
        $url = new moodle_url($CFG->httpswwwroot.'/mod/euroaspire/assessment/help.php',
                              array('competencyid' => $helpicon->competencyid));

        // Note: this title is displayed only if JS is disabled, otherwise the link will have the new ajax tooltip.
        $title = trim($title, ". \t");

        $attributes = array('href' => $url, 'title' => $title, 'aria-haspopup' => 'true', 'target' => '_blank');
        $output = html_writer::tag('a', $output, $attributes);

        // And finally span.
        return html_writer::tag('span', $output, array('class' => 'helptooltip'));
    }

    // -------------------------------------
    // Grading assessments.
    // -------------------------------------

    public function assessment_grade_all(assessmentmanager $manager) {
        $out = '';

        $out .= $this->actions($manager->get_actions());
        $out .= $this->notification_message($manager);
        $out .= $this->assessment_grade_all_table($manager);

        return $out;
    }

    protected function assessment_grade_all_table(assessmentmanager $manager) {
        $students = $manager->get_gradable_students();

        if (!$students) {
            return html_writer::tag('p', get_string('nogradeablestudents', 'mod_euroaspire'));
        }

        $format = get_string('strftimedatetime', 'langconfig');

        $table = new html_table();
        $table->head = array(
            get_string('name'),
            get_string('grade'),
            get_string('timemodified', 'mod_euroaspire'),
            get_string('timegraded', 'mod_euroaspire'),
            get_string('action')
        );

        foreach ($students as $student) {
            /** @var submission $submission */
            $submission = $student->submission;
            if ($submission->timemodified) {
                $timemodified = userdate($submission->timemodified, $format);
                if ($submission->needs_grading()) {
                    $timemodified = html_writer::span($timemodified, 'needsgrading');
                }
            } else {
                $timemodified = '';
            }
            if ($submission->timegraded) {
                $timegraded = userdate($submission->timegraded, $format);
                if ($submission->needs_grading()) {
                    $timemodified = html_writer::span($timemodified, 'needsgrading');
                }
            } else {
                $timegraded = '';
            }
            $actions = html_writer::link($manager->get_grade_url($student), get_string('grade'));

            $row = array(
                fullname($student),
                $submission->get_overall_grade_string(),
                $timemodified,
                $timegraded,
                $actions
            );
            $table->data[] = $row;
        }
        list($page, $perpage, $totalitems) = $manager->get_paging_info();

        $out = '';
        $out .= $this->output->paging_bar($totalitems, $page, $perpage, $this->page->url);
        $out .= html_writer::table($table);
        $out .= $this->output->paging_bar($totalitems, $page, $perpage, $this->page->url);

        return $out;
    }

    public function assessment_grade(assessmentmanager $manager) {
        $out = '';

        $out .= $this->actions($manager->get_actions());
        $out .= $this->notification_message($manager);
        $out .= $this->output->heading(fullname($manager->get_user()));

        $grade = $manager->get_submission()->get_overall_grade_string();
        $out .= html_writer::div(get_string('overallgrade', 'mod_euroaspire', $grade), 'usergrade');
        $out .= $this->assessment_grade_table($manager);

        return $out;
    }

    protected function assessment_grade_table(assessmentmanager $manager) {
        $out = '';

        $table = new html_table();
        $table->head = array('');
        foreach ($manager->get_dimensions() as $dimension) {
            $table->head[] = format_string($dimension->title);
        }
        $submission = $manager->get_submission();
        foreach ($manager->get_competencies() as $competency) {
            $row = array($this->format_competency_name($competency));
            foreach ($manager->get_dimensions() as $dimension) {
                // A list of currently-mapped items to the cell.
                $links = array();
                $items = $submission->get_mapped_items($competency, $dimension);
                foreach ($items as $item) {
                    $links[] = html_writer::tag('li', $this->submission_item($item, false), array('data-itemid' => $item->id));
                }

                $cell = '';
                $cell .= html_writer::tag('ul', implode('', $links), array('class' => 'mappeditems'));

                // A hidden input to hold information about the current grade for this cell.
                $grade = $submission->get_grade($competency, $dimension);
                $gradeval = $grade ? $grade->grade : submission_grade::GRADE_NONE;
                $gradecomment = $grade ? s($grade->comment) : '';

                $name = 'grade_'.$competency->id.'_'.$dimension->id;
                $id = 'id_'.$name;
                $attrib = array(
                    'type' => 'hidden',
                    'name' => $name,
                    'id' => $id,
                    'value' => $gradeval,
                    'class' => 'grade'
                );
                $cell .= html_writer::empty_tag('input', $attrib);

                // Grading icons.
                $info = (object)array('competency' => s($competency->title), 'dimension' => s($dimension->title));
                $attrib = array('title' => get_string('passfor', 'mod_euroaspire', $info));
                $selected = ($gradeval == submission_grade::GRADE_PASS) ? ' selected' : '';
                $cell .= html_writer::span('&nbsp;', 'gradepass'.$selected, $attrib);

                $attrib = array('title' => get_string('failfor', 'mod_euroaspire', $info));
                $selected = ($gradeval == submission_grade::GRADE_FAIL) ? ' selected' : '';
                $cell .= html_writer::span('&nbsp;', 'gradefail'.$selected, $attrib);

                // Feedback area.
                $name = 'gradecomment_'.$competency->id.'_'.$dimension->id;
                $id = 'id_'.$name;

                $cell .= html_writer::empty_tag('input', array('type' => 'text',
                                                               'name' => $name,
                                                               'id' => $id,
                                                               'value' => $gradecomment));

                $row[] = $cell;
            }
            $table->data[] = $row;
        }

        // User comments row.
        $row = array('');
        foreach ($manager->get_dimensions() as $dimension) {
            $comment = $submission->formatted_dimension_comment($dimension);
            $row[] = html_writer::nonempty_tag('div', $comment, array('class' => 'usercomment'));
        }
        $table->data[] = $row;

        // Wrap the mapping table in a form, with required hidden inputs + submit button.
        $out .= html_writer::table($table);
        $out .= html_writer::input_hidden_params($manager->get_submit_grade_url());
        if ($submission->usercomment) {
            $out .= html_writer::div($submission->formatted_user_comment(), 'usercomment');
        }
        $out .= $this->grading_action_buttons();

        $out = html_writer::tag('form', $out, array('action' => $manager->get_submit_grade_url(),
                                                    'method' => 'post', 'id' => 'gradeitemform'));

        return $out;
    }
}
