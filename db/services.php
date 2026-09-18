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
 * Web services definition for the IDG Progress Report plugin.
 *
 * @package    report_idgprogress
 * @copyright  2024 Cambodia Academy of Digital Technology (CADT)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'report_idgprogress_get_course_progress' => [
        'classname'    => 'report_idgprogress_external',
        'methodname'   => 'get_course_progress',
        'classpath'    => 'report/idgprogress/externallib.php',
        'description'  => 'Retrieve course progress metrics and participant progress data for external systems',
        'type'         => 'read',
        'capabilities' => 'report/idgprogress:view',
        'ajax'         => true,
    ],
    'report_idgprogress_get_user_progress' => [
        'classname'    => 'report_idgprogress_external',
        'methodname'   => 'get_user_progress',
        'classpath'    => 'report/idgprogress/externallib.php',
        'description'  => 'Retrieve detailed progress and activity completion for a single student',
        'type'         => 'read',
        'capabilities' => 'report/idgprogress:view',
        'ajax'         => true,
    ],
];

$services = [
    'IDG Progress API' => [
        'functions' => [
            'report_idgprogress_get_course_progress',
            'report_idgprogress_get_user_progress',
        ],
        'restrictedusers' => 0,
        'enabled'         => 1,
        'shortname'       => 'idg_progress_service',
    ],
];
