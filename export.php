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
 * CSV Export script with UTF-8 BOM for IDG Progress Report.
 *
 * @package    report_idgprogress
 * @copyright  2024 Cambodia Academy of Digital Technology (CADT)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once(__DIR__ . '/lib.php');

$id      = required_param('id', PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);
$search  = optional_param('search', '', PARAM_NOTAGS);

// Validate course and permissions.
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($course->id);
require_capability('report/idgprogress:view', $context);

// Handle group permissions.
$groupmode = groups_get_course_groupmode($course);
$hasallgroups = has_capability('moodle/site:accessallgroups', $context);
if ($groupmode === SEPARATEGROUPS && !$hasallgroups) {
    $allowedgroups = groups_get_all_groups($course->id, $USER->id);
    $allowedgroupids = array_keys($allowedgroups);
    if (!in_array($groupid, $allowedgroupids, true)) {
        $groupid = !empty($allowedgroupids) ? (int)reset($allowedgroupids) : -1;
    }
}

// Retrieve completion configuration and activities.
$completion = new completion_info($course);
$trackedactivities = $completion->is_enabled() ? report_idgprogress_get_tracked_activities($completion) : [];

// Retrieve all cohort participants.
$users = [];
$alluserscustomfields = [];
if ($groupid !== -1) {
    $users = report_idgprogress_get_enrolled_users(
        $context,
        $groupid,
        $search,
        'u.lastname ASC, u.firstname ASC',
        0,
        0
    );
    if (!empty($users)) {
        $alluserscustomfields = report_idgprogress_get_users_custom_fields(array_keys($users));
    }
}

// Retrieve site custom profile fields.
$customprofilefields = report_idgprogress_get_custom_profile_fields();
$othercustomfields = [];
foreach ($customprofilefields as $cf) {
    $ls = strtolower($cf->shortname);
    if ($ls !== 'gender' && $ls !== 'sex' && !str_contains($ls, 'gender') && !str_contains($ls, 'sex')) {
        $othercustomfields[$cf->shortname] = $cf;
    }
}

// Release session lock before initiating file stream.
\core\session\manager::write_close();

// Clear any existing output buffers to guarantee BOM is at byte 0.
while (ob_get_level()) {
    ob_end_clean();
}

// Generate sanitized filename.
$filenamebase = get_string('exportfilename', 'report_idgprogress');
$filename = clean_filename("{$filenamebase}_{$course->shortname}_" . date('Ymd_His') . '.csv');

// Set HTTP headers for UTF-8 CSV attachment.
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// CRITICAL: Output the UTF-8 Byte Order Mark (BOM) at byte 0.
// This ensures Microsoft Excel properly renders Khmer Unicode (\u1780-\u17FF) and international text.
echo "\xEF\xBB\xBF";

// Open output stream.
$output = fopen('php://output', 'w');

// Prepare base header row.
$headers = [
    get_string('exportheader_userid', 'report_idgprogress'),
    get_string('exportheader_username', 'report_idgprogress'),
    get_string('exportheader_fullname', 'report_idgprogress'),
    get_string('exportheader_gender', 'report_idgprogress'),
    get_string('exportheader_email', 'report_idgprogress'),
    get_string('exportheader_institution', 'report_idgprogress'),
    get_string('exportheader_department', 'report_idgprogress'),
];

// Append any other custom profile field headers.
foreach ($othercustomfields as $cf) {
    $fieldname = strip_tags(format_string($cf->name, true, ['context' => $context]));
    $headers[] = get_string('customfield_header_prefix', 'report_idgprogress', $fieldname);
}

$headers[] = get_string('exportheader_completedactivities', 'report_idgprogress');
$headers[] = get_string('exportheader_totalactivities', 'report_idgprogress');
$headers[] = get_string('exportheader_progress', 'report_idgprogress');
$headers[] = get_string('exportheader_coursestatus', 'report_idgprogress');
$headers[] = get_string('exportheader_completeddate', 'report_idgprogress');

// Append tracked activity names to header row.
foreach ($trackedactivities as $activity) {
    $activityname = strip_tags(format_string($activity->name, true, ['context' => $context]));
    $headers[] = get_string('activity_header_prefix', 'report_idgprogress', $activityname);
}

// Write header to CSV.
fputcsv($output, $headers);

// Stream data rows.
foreach ($users as $user) {
    $studentdata = report_idgprogress_get_student_completion_data(
        $course,
        $completion,
        $trackedactivities,
        $user
    );

    $completeddatestr = $studentdata->timecompleted > 0
        ? userdate($studentdata->timecompleted, get_string('strftimedatetime', 'langconfig'))
        : get_string('na', 'report_idgprogress');

    $statuslabel = get_string('status_' . $studentdata->status, 'report_idgprogress');
    $usercustom = $alluserscustomfields[$user->id] ?? [];
    $gender = report_idgprogress_get_user_gender($user, $usercustom);

    $row = [
        $user->id,
        $user->username,
        fullname($user),
        $gender !== '-' ? $gender : '',
        $user->email,
        !empty($user->institution) ? $user->institution : '',
        !empty($user->department) ? $user->department : '',
    ];

    // Append other custom profile field values.
    foreach ($othercustomfields as $shortname => $cf) {
        $row[] = !empty($usercustom[$shortname]) ? (string)$usercustom[$shortname] : '';
    }

    $row[] = $studentdata->completedactivities;
    $row[] = $studentdata->totalactivities;
    $row[] = $studentdata->percentage . '%';
    $row[] = $statuslabel;
    $row[] = $completeddatestr;

    // Append individual activity completion details.
    foreach ($trackedactivities as $cmid => $activity) {
        if (isset($studentdata->activitystates[$cmid])) {
            $astate = $studentdata->activitystates[$cmid];
            if ($astate->iscompleted) {
                $activitydatestr = $astate->timemodified > 0
                    ? userdate($astate->timemodified, get_string('strftimedatetime', 'langconfig'))
                    : '';
                $row[] = get_string('completed', 'report_idgprogress') . ($activitydatestr ? ' (' . $activitydatestr . ')' : '');
            } else {
                $row[] = get_string('notcompleted', 'report_idgprogress');
            }
        } else {
            $row[] = get_string('na', 'report_idgprogress');
        }
    }

    fputcsv($output, $row);
}

fclose($output);
exit;
