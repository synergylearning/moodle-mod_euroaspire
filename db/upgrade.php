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
 * DB upgrade
 *
 * @package   mod_euroaspire
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_euroaspire_upgrade($oldversion = 0) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2015022500) {

        // Define field timegraded to be added to euroaspire_submission.
        $table = new xmldb_table('euroaspire_submission');
        $field = new xmldb_field('timegraded', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timemodified');

        // Conditionally launch add field timegraded.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Euroaspire savepoint reached.
        upgrade_mod_savepoint(true, 2015022500, 'euroaspire');
    }

    if ($oldversion < 2015022601) {

        // Define field usercomment to be added to euroaspire_submission.
        $table = new xmldb_table('euroaspire_submission');
        $field = new xmldb_field('usercomment', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timegraded');

        // Conditionally launch add field usercomment.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Euroaspire savepoint reached.
        upgrade_mod_savepoint(true, 2015022601, 'euroaspire');
    }

    if ($oldversion < 2015022603) {

        // Define field passtocomplete to be added to euroaspire.
        $table = new xmldb_table('euroaspire');
        $field = new xmldb_field('passtocomplete', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field passtocomplete.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Euroaspire savepoint reached.
        upgrade_mod_savepoint(true, 2015022603, 'euroaspire');
    }

    if ($oldversion < 2015042001) {

        // Define table euroaspire_submission_comnt to be created.
        $table = new xmldb_table('euroaspire_submission_comnt');

        // Adding fields to table euroaspire_submission_comnt.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('submissionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('dimensionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('comment', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table euroaspire_submission_comnt.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('submissionid', XMLDB_KEY_FOREIGN, array('submissionid'), 'euroaspire_submission', array('id'));
        $table->add_key('dimensionid', XMLDB_KEY_FOREIGN, array('dimensionid'), 'euroaspire_dimension', array('id'));

        // Conditionally launch create table for euroaspire_submission_comnt.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Euroaspire savepoint reached.
        upgrade_mod_savepoint(true, 2015042001, 'euroaspire');
    }

    return true;
}
