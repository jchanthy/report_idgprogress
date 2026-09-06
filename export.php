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
 * Export script with field selection supporting Excel (.xlsx) and CSV with UTF-8 BOM.
 *
 * @package    report_idgprogress
 * @copyright  2024 Cambodia Academy of Digital Technology (CADT)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/excellib.class.php');
require_once(__DIR__ . '/lib.php');

$id              = required_param('id', PARAM_INT);
$groupid         = optional_param('group', 0, PARAM_INT);
$search          = optional_param('search', '', PARAM_NOTAGS);
$format          = optional_param('format', 'excel', PARAM_ALPHA);
$requestedfields = optional_param_array('fields', [], PARAM_ALPHANUMEXT);
$progressfilter  = optional_param('progress_filter', 'all', PARAM_ALPHA);
$progressmin     = optional_param('progress_min', 0, PARAM_INT);
$progressmax     = optional_param('progress_max', 100, PARAM_INT);

if ($format === 'xlsx') {
    $format = 'excel';
}
if (!in_array($format, ['excel', 'csv'], true)) {
    $format = 'excel';
}

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

// Batch-fetch course-level groups, roles, and last access.
$courseusergroups = report_idgprogress_get_course_user_groups($course->id);
$courseuserroles = report_idgprogress_get_course_user_roles($context->id);
$courselastaccess = report_idgprogress_get_course_lastaccess($course->id);

// Retrieve site custom profile fields.
$customprofilefields = report_idgprogress_get_custom_profile_fields();
$othercustomfields = [];
foreach ($customprofilefields as $cf) {
    $ls = strtolower($cf->shortname);
    $hasgender = ($ls === 'gender' || $ls === 'sex' || strpos($ls, 'gender') !== false || strpos($ls, 'sex') !== false);
    if (!$hasgender) {
        $othercustomfields[$cf->shortname] = $cf;
    }
}

// If no fields specified (direct URL call), default to all standard fields.
if (empty($requestedfields)) {
    $requestedfields = [
        'userid', 'username', 'fullname', 'gender', 'email',
        'institution', 'department', 'activities_count',
        'progress', 'coursestatus', 'completeddate', 'activities_detail'
    ];
    foreach ($othercustomfields as $cf) {
        $requestedfields[] = 'custom_' . $cf->shortname;
    }
}

// Define all available field definitions indexed by field identifier.
$availablecolumns = [
    'userid' => [
        'header' => get_string('exportheader_userid', 'report_idgprogress'),
        'value'  => function($user, $sdata, $ucustom) {
            return $user->id;
        },
    ],
    'username' => [
        'header' => get_string('exportheader_username', 'report_idgprogress'),
        'value'  => function($user, $sdata, $ucustom) {
            return $user->username;
        },
    ],
    'fullname' => [
        'header' => get_string('exportheader_fullname', 'report_idgprogress'),
        'value'  => function($user, $sdata, $ucustom) {
            return fullname($user);
        },
    ],
    'gender' => [
        'header' => get_string('exportheader_gender', 'report_idgprogress'),
        'value'  => function($user, $sdata, $ucustom) {
            $g = report_idgprogress_get_user_gender($user, $ucustom);
            return $g !== '-' ? $g : '';
        },
    ],
    'email' => [
        'header' => get_string('exportheader_email', 'report_idgprogress'),
        'value'  => function($user, $sdata, $ucustom) {
            return $user->email;
        },
    ],
    'institution' => [
        'header' => get_string('exportheader_institution', 'report_idgprogress'),
        'value'  => function($user, $sdata, $ucustom) {
            return !empty($user->institution) ? $user->institution : '';
        },
    ],
    'department' => [
        'header' => get_string('exportheader_department', 'report_idgprogress'),
        'value'  => function($user, $sdata, $ucustom) {
            return !empty($user->department) ? $user->department : '';
        },
    ],
    'phone' => [
        'header' => get_string('phone'),
        'value'  => function($user, $sdata, $ucustom) {
            return !empty($user->phone1) ? $user->phone1 : (!empty($user->phone2) ? $user->phone2 : '');
        },
    ],
    'roles' => [
        'header' => get_string('roles'),
        'value'  => function($user, $sdata, $ucustom) use ($courseuserroles) {
            $r = $courseuserroles[$user->id] ?? [];
            return !empty($r) ? implode(', ', $r) : get_string('student', 'moodle', 'Student');
        },
    ],
    'groups' => [
        'header' => get_string('groups'),
        'value'  => function($user, $sdata, $ucustom) use ($courseusergroups) {
            $g = $courseusergroups[$user->id] ?? [];
            return !empty($g) ? implode(', ', $g) : get_string('nogroups', 'group');
        },
    ],
    'lastaccess' => [
        'header' => get_string('lastcourseaccess'),
        'value'  => function($user, $sdata, $ucustom) use ($courselastaccess) {
            $la = $courselastaccess[$user->id] ?? 0;
            return $la > 0 ? format_time(time() - $la) : get_string('never');
        },
    ],
    'activities_count' => [
        'multi' => true,
        'cols'  => [
            'completedactivities' => [
                'header' => get_string('exportheader_completedactivities', 'report_idgprogress'),
                'value'  => function($user, $sdata, $ucustom) {
                    return $sdata->completedactivities;
                },
            ],
            'totalactivities' => [
                'header' => get_string('exportheader_totalactivities', 'report_idgprogress'),
                'value'  => function($user, $sdata, $ucustom) {
                    return $sdata->totalactivities;
                },
            ],
        ],
    ],
    'progress' => [
        'header' => get_string('exportheader_progress', 'report_idgprogress'),
        'value'  => function($user, $sdata, $ucustom) {
            return $sdata->percentage . '%';
        },
    ],
    'coursestatus' => [
        'header' => get_string('exportheader_coursestatus', 'report_idgprogress'),
        'value'  => function($user, $sdata, $ucustom) {
            return get_string('status_' . $sdata->status, 'report_idgprogress');
        },
    ],
    'completeddate' => [
        'header' => get_string('exportheader_completeddate', 'report_idgprogress'),
        'value'  => function($user, $sdata, $ucustom) {
            if ($sdata->status === 'completed' && $sdata->timecompleted > 0) {
                return userdate($sdata->timecompleted, get_string('strftimedatetime', 'langconfig'));
            }
            if (!empty($sdata->lastactivitytime) && $sdata->lastactivitytime > 0) {
                return get_string('lastactivityprefix', 'report_idgprogress') . ': ' . userdate($sdata->lastactivitytime, get_string('strftimedatetime', 'langconfig'));
            }
            return get_string('na', 'report_idgprogress');
        },
    ],
];

// Register custom profile fields into available columns.
foreach ($othercustomfields as $cf) {
    $fieldkey = 'custom_' . $cf->shortname;
    $fieldname = strip_tags(format_string($cf->name, true, ['context' => $context]));
    $cfshortname = $cf->shortname;
    $availablecolumns[$fieldkey] = [
        'header' => $fieldname,
        'value'  => function($user, $sdata, $ucustom) use ($cfshortname) {
            return !empty($ucustom[$cfshortname]) ? (string)$ucustom[$cfshortname] : '';
        },
    ];
}

// Register individual activity detail columns into available columns.
if (!empty($trackedactivities)) {
    $actcols = [];
    foreach ($trackedactivities as $cmid => $activity) {
        $actname = strip_tags(format_string($activity->name, true, ['context' => $context]));
        $actcols['act_' . $cmid] = [
            'header' => get_string('activity_header_prefix', 'report_idgprogress', $actname),
            'value'  => function($user, $sdata, $ucustom) use ($cmid) {
                if (isset($sdata->activitystates[$cmid])) {
                    $astate = $sdata->activitystates[$cmid];
                    if ($astate->iscompleted) {
                        $datestr = $astate->timemodified > 0
                            ? userdate($astate->timemodified, get_string('strftimedatetime', 'langconfig'))
                            : '';
                        return get_string('completed', 'report_idgprogress') . ($datestr ? ' (' . $datestr . ')' : '');
                    }
                    return get_string('notcompleted', 'report_idgprogress');
                }
                return get_string('na', 'report_idgprogress');
            },
        ];
    }
    $availablecolumns['activities_detail'] = [
        'multi' => true,
        'cols'  => $actcols,
    ];
}

// Build columns strictly in the exact order requested by the user.
$columns = [];
foreach ($requestedfields as $fieldkey) {
    if (!isset($availablecolumns[$fieldkey])) {
        continue;
    }
    $coldef = $availablecolumns[$fieldkey];
    if (!empty($coldef['multi'])) {
        foreach ($coldef['cols'] as $subk => $subdef) {
            $columns[$subk] = $subdef;
        }
    } else {
        $columns[$fieldkey] = $coldef;
    }
}

// Bulk pre-load completion cache for all export users to prevent N+1 queries.
$cohortcache = report_idgprogress_load_cohort_completion_cache(
    (int)$course->id,
    array_keys($trackedactivities),
    array_keys($users)
);

// Release session lock before long file streaming.
\core\session\manager::write_close();

$filenamebase = get_string('exportfilename', 'report_idgprogress');

if ($format === 'excel') {
    // 1. EXCEL (.xlsx) EXPORT VIA MoodleExcelWorkbook
    $filename = clean_filename("{$filenamebase}_{$course->shortname}_" . date('Ymd_His') . '.xlsx');
    $workbook = new MoodleExcelWorkbook($filename);
    $worksheetname = clean_param(mb_substr($course->shortname, 0, 31), PARAM_ALPHANUMEXT);
    if (empty($worksheetname)) {
        $worksheetname = 'Progress';
    }
    $worksheet = $workbook->add_worksheet($worksheetname);

    // Styling format for header.
    $format_header = $workbook->add_format([
        'bold'   => 1,
        'size'   => 11,
        'align'  => 'left',
        'bottom' => 2,
    ]);

    // Write header row.
    $colidx = 0;
    foreach ($columns as $col) {
        $worksheet->write_string(0, $colidx, $col['header'], $format_header);
        $colidx++;
    }

    // Write data rows.
    $rowidx = 1;
    foreach ($users as $user) {
        $studentdata = report_idgprogress_get_student_completion_data(
            $course,
            $completion,
            $trackedactivities,
            $user,
            $cohortcache
        );

        // Progress filter.
        $isincluded = true;
        switch ($progressfilter) {
            case 'completed':
                $isincluded = ($studentdata->status === 'completed' || (float)$studentdata->percentage >= 100.0);
                break;
            case 'inprogress':
                $isincluded = ($studentdata->status === 'inprogress');
                break;
            case 'notstarted':
                $isincluded = ($studentdata->status === 'notstarted' || (float)$studentdata->percentage === 0.0);
                break;
            case 'under100':
                $isincluded = ((float)$studentdata->percentage < 100.0 && $studentdata->status !== 'completed');
                break;
            case 'under50':
                $isincluded = ((float)$studentdata->percentage < 50.0);
                break;
            case 'custom':
                $isincluded = ((float)$studentdata->percentage >= (float)$progressmin && (float)$studentdata->percentage <= (float)$progressmax);
                break;
            case 'all':
            default:
                $isincluded = true;
                break;
        }

        if (!$isincluded) {
            continue;
        }

        $usercustom = $alluserscustomfields[$user->id] ?? [];
        $colidx = 0;
        foreach ($columns as $col) {
            $val = $col['value']($user, $studentdata, $usercustom);
            if (is_numeric($val) && !is_string($val)) {
                $worksheet->write_number($rowidx, $colidx, $val);
            } else {
                $worksheet->write_string($rowidx, $colidx, (string)$val);
            }
            $colidx++;
        }
        $rowidx++;
    }

    $workbook->close();
    exit;

} else {
    // 2. CSV EXPORT WITH UTF-8 BOM PRESERVATION
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = clean_filename("{$filenamebase}_{$course->shortname}_" . date('Ymd_His') . '.csv');

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // CRITICAL: Output the UTF-8 Byte Order Mark (BOM) at byte 0.
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Write header row.
    $headerrow = array_column($columns, 'header');
    fputcsv($output, $headerrow);

    // Stream data rows.
    foreach ($users as $user) {
        $studentdata = report_idgprogress_get_student_completion_data(
            $course,
            $completion,
            $trackedactivities,
            $user,
            $cohortcache
        );

        // Progress filter.
        $isincluded = true;
        switch ($progressfilter) {
            case 'completed':
                $isincluded = ($studentdata->status === 'completed' || (float)$studentdata->percentage >= 100.0);
                break;
            case 'inprogress':
                $isincluded = ($studentdata->status === 'inprogress');
                break;
            case 'notstarted':
                $isincluded = ($studentdata->status === 'notstarted' || (float)$studentdata->percentage === 0.0);
                break;
            case 'under100':
                $isincluded = ((float)$studentdata->percentage < 100.0 && $studentdata->status !== 'completed');
                break;
            case 'under50':
                $isincluded = ((float)$studentdata->percentage < 50.0);
                break;
            case 'custom':
                $isincluded = ((float)$studentdata->percentage >= (float)$progressmin && (float)$studentdata->percentage <= (float)$progressmax);
                break;
            case 'all':
            default:
                $isincluded = true;
                break;
        }

        if (!$isincluded) {
            continue;
        }

        $usercustom = $alluserscustomfields[$user->id] ?? [];
        $row = [];
        foreach ($columns as $col) {
            $row[] = $col['value']($user, $studentdata, $usercustom);
        }
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}
