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
 * English language strings for the IDG Progress Report plugin.
 *
 * @package    report_idgprogress
 * @copyright  2024 Cambodia Academy of Digital Technology (CADT)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'IDG Progress Report';
$string['idgprogress:view'] = 'View IDG Progress Report';
$string['pageheading'] = 'IDG Progress Report';
$string['coursereport'] = 'Course Progress Tracking';
$string['allparticipants'] = 'All participants';
$string['filtergroup'] = 'Filter by group';

// Summary metrics.
$string['totalenrolled'] = 'Total Enrolled';
$string['completedcourse'] = 'Course Completed';
$string['inprogress'] = 'In Progress';
$string['notstarted'] = 'Not Started';
$string['avgprogress'] = 'Average Progress';

// Search & filters.
$string['searchparticipant'] = 'Search participants...';
$string['search'] = 'Search';
$string['clear'] = 'Clear';
$string['exportcsv'] = 'Export to CSV (Excel with BOM)';
$string['exportexcel'] = 'Export Report';

// Table columns.
$string['table_fullname'] = 'Participant Name';
$string['table_gender'] = 'Gender';
$string['table_email'] = 'Email';
$string['table_institution'] = 'Institution / Ministry';
$string['table_department'] = 'Department / Unit';
$string['table_activities'] = 'Completed Activities';
$string['table_progress'] = 'Progress';
$string['table_status'] = 'Course Status';
$string['table_completeddate'] = 'Completed Date';
$string['table_actions'] = 'Actions';
$string['viewprofile'] = 'View profile';

// Statuses & labels.
$string['completionnotenabled'] = 'Completion tracking is not enabled for this course. Please enable completion tracking in course settings to view progress metrics.';
$string['nocriteriaset'] = 'No completion criteria or tracked activities are defined for this course.';
$string['noparticipantsfound'] = 'No participants found matching your criteria.';
$string['completed'] = 'Completed';
$string['notcompleted'] = 'Not completed';
$string['status_completed'] = 'Completed';
$string['status_inprogress'] = 'In Progress';
$string['status_notstarted'] = 'Not Started';
$string['na'] = 'N/A';
$string['overallcompletion'] = 'Overall Course Completion';
$string['activitycompletion'] = 'Activity Completion';
$string['exportfilename'] = 'IDG_Progress_Report';

// Export CSV headers.
$string['exportheader_userid'] = 'User ID';
$string['exportheader_username'] = 'Username';
$string['exportheader_fullname'] = 'Full Name';
$string['exportheader_gender'] = 'Gender';
$string['exportheader_email'] = 'Email';
$string['exportheader_institution'] = 'Institution';
$string['exportheader_department'] = 'Department';
$string['exportheader_completedactivities'] = 'Completed Activities';
$string['exportheader_totalactivities'] = 'Total Activities';
$string['exportheader_progress'] = 'Progress (%)';
$string['exportheader_coursestatus'] = 'Course Status';
$string['exportheader_completeddate'] = 'Course Completed Date';
$string['customfield_header_prefix'] = '{$a}';
$string['activity_header_prefix'] = 'Activity: {$a}';

// Export options and dialog.
$string['exportoptions'] = 'Export Report';
$string['exportformat'] = 'Export Format';
$string['formatexcel'] = 'Microsoft Excel (.xlsx)';
$string['formatcsv'] = 'CSV with BOM (.csv)';
$string['selectfields'] = 'Select Fields to Export';
$string['selectall'] = 'Select All';
$string['deselectall'] = 'Deselect All';
$string['field_userid'] = 'User ID';
$string['field_username'] = 'Username';
$string['field_fullname'] = 'Full Name';
$string['field_gender'] = 'Gender';
$string['field_email'] = 'Email';
$string['field_institution'] = 'Institution / Ministry';
$string['field_department'] = 'Department / Unit';
$string['field_activities_count'] = 'Completed Activities Count';
$string['field_progress'] = 'Progress Percentage (%)';
$string['field_coursestatus'] = 'Course Completion Status';
$string['field_completeddate'] = 'Course Completed Date';
$string['field_activities_detail'] = 'Individual Tracked Activities';
$string['download'] = 'Download';
$string['cancel'] = 'Cancel';

// Privacy metadata.
$string['privacy:metadata'] = 'The IDG Progress Report plugin displays existing completion and enrollment data and does not store any personal data of its own.';
