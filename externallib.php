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
 * External Web Services API for the IDG Progress Report plugin.
 *
 * @package    report_idgprogress
 * @copyright  2024 Cambodia Academy of Digital Technology (CADT)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once(__DIR__ . '/lib.php');

/**
 * External Web Service functions class for report_idgprogress.
 */
class report_idgprogress_external extends external_api {

    /**
     * Parameter description for get_course_progress.
     *
     * @return external_function_parameters
     */
    public static function get_course_progress_parameters() {
        return new external_function_parameters([
            'courseid'           => new external_value(PARAM_INT, 'Course ID to retrieve progress for'),
            'groupid'            => new external_value(PARAM_INT, 'Optional group ID to filter by', VALUE_DEFAULT, 0),
            'progress_filter'    => new external_value(PARAM_ALPHA, 'Progress filter: all, inprogress, completed, notstarted, under100, under50', VALUE_DEFAULT, 'all'),
            'search'             => new external_value(PARAM_NOTAGS, 'Search query by student name or email', VALUE_DEFAULT, ''),
            'page'               => new external_value(PARAM_INT, 'Page index (0-based) for pagination', VALUE_DEFAULT, 0),
            'perpage'            => new external_value(PARAM_INT, 'Number of records per page (0 returns all)', VALUE_DEFAULT, 0),
            'include_activities' => new external_value(PARAM_BOOL, 'Whether to include individual activity completion details', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Retrieve course progress metrics and participant progress data.
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $progress_filter
     * @param string $search
     * @param int $page
     * @param int $perpage
     * @param bool $include_activities
     * @return array
     */
    public static function get_course_progress(
        $courseid,
        $groupid = 0,
        $progress_filter = 'all',
        $search = '',
        $page = 0,
        $perpage = 0,
        $include_activities = false
    ) {
        global $DB;

        $params = self::validate_parameters(self::get_course_progress_parameters(), [
            'courseid'           => $courseid,
            'groupid'            => $groupid,
            'progress_filter'    => $progress_filter,
            'search'             => $search,
            'page'               => $page,
            'perpage'            => $perpage,
            'include_activities' => $include_activities,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('report/idgprogress:view', $context);

        return report_idgprogress_get_api_progress_data(
            $course,
            $context,
            $params['groupid'],
            $params['progress_filter'],
            $params['search'],
            $params['page'],
            $params['perpage'],
            $params['include_activities']
        );
    }

    /**
     * Return structure description for get_course_progress.
     *
     * @return external_single_structure
     */
    public static function get_course_progress_returns() {
        return new external_single_structure([
            'course' => new external_single_structure([
                'id'                 => new external_value(PARAM_INT, 'Course ID'),
                'fullname'           => new external_value(PARAM_TEXT, 'Course Fullname'),
                'shortname'          => new external_value(PARAM_TEXT, 'Course Shortname'),
                'completion_enabled' => new external_value(PARAM_BOOL, 'Whether completion is enabled'),
            ]),
            'summary' => new external_single_structure([
                'total_enrolled' => new external_value(PARAM_INT, 'Total enrolled users'),
                'completed'      => new external_value(PARAM_INT, 'Completed course count'),
                'in_progress'    => new external_value(PARAM_INT, 'In progress count'),
                'not_started'    => new external_value(PARAM_INT, 'Not started count'),
                'avg_progress'   => new external_value(PARAM_FLOAT, 'Average progress percentage'),
            ]),
            'tracked_activities' => new external_multiple_structure(
                new external_single_structure([
                    'id'      => new external_value(PARAM_INT, 'Course module ID'),
                    'name'    => new external_value(PARAM_TEXT, 'Activity name'),
                    'modname' => new external_value(PARAM_PLUGIN, 'Activity module type'),
                ]), 'List of tracked activities in the course'
            ),
            'total_participants' => new external_value(PARAM_INT, 'Total count of filtered participants'),
            'participants' => new external_multiple_structure(
                new external_single_structure([
                    'id'                         => new external_value(PARAM_INT, 'User ID'),
                    'username'                   => new external_value(PARAM_RAW, 'Username'),
                    'firstname'                  => new external_value(PARAM_TEXT, 'First name'),
                    'lastname'                   => new external_value(PARAM_TEXT, 'Last name'),
                    'fullname'                   => new external_value(PARAM_TEXT, 'Full name'),
                    'email'                      => new external_value(PARAM_RAW, 'Email address'),
                    'phone'                      => new external_value(PARAM_RAW, 'Phone number'),
                    'department'                 => new external_value(PARAM_TEXT, 'Department'),
                    'institution'                => new external_value(PARAM_TEXT, 'Institution'),
                    'roles'                      => new external_value(PARAM_TEXT, 'User roles in course'),
                    'groups'                     => new external_value(PARAM_TEXT, 'User groups in course'),
                    'lastaccess'                 => new external_value(PARAM_INT, 'Timestamp of last course access'),
                    'lastaccess_formatted'       => new external_value(PARAM_TEXT, 'Formatted last access string'),
                    'completed_activities'       => new external_value(PARAM_INT, 'Number of completed activities'),
                    'total_activities'           => new external_value(PARAM_INT, 'Total tracked activities count'),
                    'percentage'                 => new external_value(PARAM_INT, 'Progress percentage'),
                    'status'                     => new external_value(PARAM_ALPHA, 'Progress status: completed, inprogress, notstarted'),
                    'status_label'               => new external_value(PARAM_TEXT, 'Localized status label'),
                    'timecompleted'              => new external_value(PARAM_INT, 'Course completion timestamp (0 if not completed)'),
                    'timecompleted_formatted'    => new external_value(PARAM_TEXT, 'Formatted completion date string'),
                    'lastactivitytime'           => new external_value(PARAM_INT, 'Last activity completion timestamp'),
                    'lastactivitytime_formatted' => new external_value(PARAM_TEXT, 'Formatted last activity date string'),
                    'custom_fields' => new external_multiple_structure(
                        new external_single_structure([
                            'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Field shortname'),
                            'name'      => new external_value(PARAM_TEXT, 'Field display name'),
                            'value'     => new external_value(PARAM_RAW, 'Field value'),
                        ]), 'User custom profile fields'
                    ),
                    'activities_detail' => new external_multiple_structure(
                        new external_single_structure([
                            'cmid'                   => new external_value(PARAM_INT, 'Course module ID'),
                            'name'                   => new external_value(PARAM_TEXT, 'Activity name'),
                            'iscompleted'            => new external_value(PARAM_BOOL, 'Whether activity is completed'),
                            'timemodified'           => new external_value(PARAM_INT, 'Completion timestamp'),
                            'timemodified_formatted' => new external_value(PARAM_TEXT, 'Formatted completion date'),
                        ]), 'Activity completion detail list'
                    ),
                ]), 'List of student progress records'
            ),
        ]);
    }

    /**
     * Parameter description for get_user_progress.
     *
     * @return external_function_parameters
     */
    public static function get_user_progress_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'userid'   => new external_value(PARAM_INT, 'User ID'),
        ]);
    }

    /**
     * Retrieve detailed progress and activity completion for a single student.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function get_user_progress($courseid, $userid) {
        global $DB;

        $params = self::validate_parameters(self::get_user_progress_parameters(), [
            'courseid' => $courseid,
            'userid'   => $userid,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('report/idgprogress:view', $context);

        return report_idgprogress_get_api_user_progress_data(
            $course,
            $context,
            $params['userid']
        );
    }

    /**
     * Return structure description for get_user_progress.
     *
     * @return external_single_structure
     */
    public static function get_user_progress_returns() {
        return new external_single_structure([
            'course' => new external_single_structure([
                'id'                 => new external_value(PARAM_INT, 'Course ID'),
                'fullname'           => new external_value(PARAM_TEXT, 'Course Fullname'),
                'shortname'          => new external_value(PARAM_TEXT, 'Course Shortname'),
                'completion_enabled' => new external_value(PARAM_BOOL, 'Whether completion is enabled'),
            ]),
            'student' => new external_single_structure([
                'id'                         => new external_value(PARAM_INT, 'User ID'),
                'username'                   => new external_value(PARAM_RAW, 'Username'),
                'firstname'                  => new external_value(PARAM_TEXT, 'First name'),
                'lastname'                   => new external_value(PARAM_TEXT, 'Last name'),
                'fullname'                   => new external_value(PARAM_TEXT, 'Full name'),
                'email'                      => new external_value(PARAM_RAW, 'Email address'),
                'phone'                      => new external_value(PARAM_RAW, 'Phone number'),
                'department'                 => new external_value(PARAM_TEXT, 'Department'),
                'institution'                => new external_value(PARAM_TEXT, 'Institution'),
                'roles'                      => new external_value(PARAM_TEXT, 'User roles in course'),
                'groups'                     => new external_value(PARAM_TEXT, 'User groups in course'),
                'lastaccess'                 => new external_value(PARAM_INT, 'Timestamp of last course access'),
                'lastaccess_formatted'       => new external_value(PARAM_TEXT, 'Formatted last access string'),
                'completed_activities'       => new external_value(PARAM_INT, 'Number of completed activities'),
                'total_activities'           => new external_value(PARAM_INT, 'Total tracked activities count'),
                'percentage'                 => new external_value(PARAM_INT, 'Progress percentage'),
                'status'                     => new external_value(PARAM_ALPHA, 'Progress status: completed, inprogress, notstarted'),
                'status_label'               => new external_value(PARAM_TEXT, 'Localized status label'),
                'timecompleted'              => new external_value(PARAM_INT, 'Course completion timestamp (0 if not completed)'),
                'timecompleted_formatted'    => new external_value(PARAM_TEXT, 'Formatted completion date string'),
                'lastactivitytime'           => new external_value(PARAM_INT, 'Last activity completion timestamp'),
                'lastactivitytime_formatted' => new external_value(PARAM_TEXT, 'Formatted last activity date string'),
                'custom_fields' => new external_multiple_structure(
                    new external_single_structure([
                        'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Field shortname'),
                        'name'      => new external_value(PARAM_TEXT, 'Field display name'),
                        'value'     => new external_value(PARAM_RAW, 'Field value'),
                    ]), 'User custom profile fields'
                ),
                'activities_detail' => new external_multiple_structure(
                    new external_single_structure([
                        'cmid'                   => new external_value(PARAM_INT, 'Course module ID'),
                        'name'                   => new external_value(PARAM_TEXT, 'Activity name'),
                        'iscompleted'            => new external_value(PARAM_BOOL, 'Whether activity is completed'),
                        'timemodified'           => new external_value(PARAM_INT, 'Completion timestamp'),
                        'timemodified_formatted' => new external_value(PARAM_TEXT, 'Formatted completion date'),
                    ]), 'Activity completion detail list'
                ),
            ]),
        ]);
    }
}
