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
 * Core library functions for the IDG Progress Report plugin.
 *
 * @package    report_idgprogress
 * @copyright  2024 Cambodia Academy of Digital Technology (CADT)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend course navigation to add IDG Progress Report node.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 * @return void
 */
function report_idgprogress_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (has_capability('report/idgprogress:view', $context)) {
        $url = new moodle_url('/report/idgprogress/index.php', ['id' => $course->id]);
        $reportnode = $navigation->find('coursereports', navigation_node::TYPE_CONTAINER);

        $node = navigation_node::create(
            get_string('pluginname', 'report_idgprogress'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'idgprogress',
            new pix_icon('i/report', '')
        );

        if ($reportnode) {
            $reportnode->add_node($node);
        } else {
            $navigation->add_node($node);
        }
    }
}

/**
 * Retrieve all tracked activities for a course.
 *
 * @param completion_info $completion Completion info instance.
 * @return array Array of course module objects with completion enabled.
 */
function report_idgprogress_get_tracked_activities(completion_info $completion): array {
    if (!$completion->is_enabled()) {
        return [];
    }

    $activities = $completion->get_activities();
    $tracked = [];
    foreach ($activities as $activity) {
        if ($activity->completion != COMPLETION_TRACKING_NONE) {
            $tracked[$activity->id] = $activity;
        }
    }

    return $tracked;
}

/**
 * Retrieve enrolled users eligible for completion tracking, with optional group and search filtering.
 *
 * @param context_course $context Course context.
 * @param int $groupid Filter by group ID (0 for all groups).
 * @param string $search Search query by name or email.
 * @param string $sort SQL sort order.
 * @param int $limitfrom Offset for pagination.
 * @param int $limitnum Number of records to return.
 * @return array Array of user records.
 */
function report_idgprogress_get_enrolled_users(
    context_course $context,
    int $groupid = 0,
    string $search = '',
    string $sort = 'u.lastname ASC, u.firstname ASC',
    int $limitfrom = 0,
    int $limitnum = 0
): array {
    global $DB;

    $userfields = user_picture::fields('u', ['institution', 'department', 'username', 'email']);

    $search = trim($search);
    if ($search === '') {
        return get_enrolled_users(
            $context,
            'moodle/course:isincompletionreports',
            $groupid,
            $userfields,
            $sort,
            $limitfrom,
            $limitnum
        );
    }

    // When searching, query enrolled users using get_enrolled_sql with parameterized conditions.
    [$esql, $params] = get_enrolled_sql($context, 'moodle/course:isincompletionreports', $groupid, true);

    $conditions = [];
    $searchparts = preg_split('/\s+/', $search);
    $idx = 0;
    foreach ($searchparts as $part) {
        if ($part === '') {
            continue;
        }
        $idx++;
        $paramfn = 'fn_' . $idx;
        $paramln = 'ln_' . $idx;
        $paramem = 'em_' . $idx;
        $paramun = 'un_' . $idx;

        $conditions[] = '(' .
            $DB->sql_like('u.firstname', ':' . $paramfn, false, false) . ' OR ' .
            $DB->sql_like('u.lastname', ':' . $paramln, false, false) . ' OR ' .
            $DB->sql_like('u.email', ':' . $paramem, false, false) . ' OR ' .
            $DB->sql_like('u.username', ':' . $paramun, false, false) .
        ')';

        $params[$paramfn] = '%' . $part . '%';
        $params[$paramln] = '%' . $part . '%';
        $params[$paramem] = '%' . $part . '%';
        $params[$paramun] = '%' . $part . '%';
    }

    $wheresql = '';
    if (!empty($conditions)) {
        $wheresql = ' AND ' . implode(' AND ', $conditions);
    }

    $sql = "SELECT {$userfields}
              FROM {user} u
              JOIN ({$esql}) eu ON eu.id = u.id
             WHERE u.deleted = 0 {$wheresql}
          ORDER BY {$sort}";

    return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
}

/**
 * Count enrolled users matching criteria for pagination and metric calculations.
 *
 * @param context_course $context Course context.
 * @param int $groupid Filter by group ID (0 for all groups).
 * @param string $search Search query.
 * @return int Total number of matching enrolled users.
 */
function report_idgprogress_count_enrolled_users(
    context_course $context,
    int $groupid = 0,
    string $search = ''
): int {
    global $DB;

    $search = trim($search);
    if ($search === '') {
        return count_enrolled_users(
            $context,
            'moodle/course:isincompletionreports',
            $groupid
        );
    }

    [$esql, $params] = get_enrolled_sql($context, 'moodle/course:isincompletionreports', $groupid, true);

    $conditions = [];
    $searchparts = preg_split('/\s+/', $search);
    $idx = 0;
    foreach ($searchparts as $part) {
        if ($part === '') {
            continue;
        }
        $idx++;
        $paramfn = 'fn_' . $idx;
        $paramln = 'ln_' . $idx;
        $paramem = 'em_' . $idx;
        $paramun = 'un_' . $idx;

        $conditions[] = '(' .
            $DB->sql_like('u.firstname', ':' . $paramfn, false, false) . ' OR ' .
            $DB->sql_like('u.lastname', ':' . $paramln, false, false) . ' OR ' .
            $DB->sql_like('u.email', ':' . $paramem, false, false) . ' OR ' .
            $DB->sql_like('u.username', ':' . $paramun, false, false) .
        ')';

        $params[$paramfn] = '%' . $part . '%';
        $params[$paramln] = '%' . $part . '%';
        $params[$paramem] = '%' . $part . '%';
        $params[$paramun] = '%' . $part . '%';
    }

    $wheresql = '';
    if (!empty($conditions)) {
        $wheresql = ' AND ' . implode(' AND ', $conditions);
    }

    $sql = "SELECT COUNT(u.id)
              FROM {user} u
              JOIN ({$esql}) eu ON eu.id = u.id
             WHERE u.deleted = 0 {$wheresql}";

    return (int)$DB->count_records_sql($sql, $params);
}

/**
 * Calculate completion status and metrics for an individual student.
 *
 * @param stdClass $course Course object.
 * @param completion_info $completion Completion info instance.
 * @param array $activities Array of tracked course activities.
 * @param stdClass $user User object.
 * @return stdClass Progress data object.
 */
function report_idgprogress_get_student_completion_data(
    stdClass $course,
    completion_info $completion,
    array $activities,
    stdClass $user
): stdClass {
    $totalactivities = count($activities);
    $completedactivities = 0;
    $activitystates = [];

    foreach ($activities as $cmid => $activity) {
        $cdata = $completion->get_data($activity, false, $user->id);
        $iscompleted = in_array(
            (int)$cdata->completionstate,
            [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS],
            true
        );

        if ($iscompleted) {
            $completedactivities++;
        }

        $activitystates[$cmid] = (object)[
            'cmid'         => $cmid,
            'name'         => $activity->name,
            'state'        => (int)$cdata->completionstate,
            'iscompleted'  => $iscompleted,
            'timemodified' => $cdata->timemodified ?? 0,
        ];
    }

    // Determine course-level completion if criteria are set.
    $iscoursecomplete = false;
    $timecompleted = 0;

    if ($completion->is_enabled()) {
        $iscoursecomplete = $completion->is_course_complete($user->id);
        if ($iscoursecomplete) {
            $ccompletion = new completion_completion(['userid' => $user->id, 'course' => $course->id]);
            $timecompleted = !empty($ccompletion->timecompleted) ? (int)$ccompletion->timecompleted : 0;
        }
    }

    // Compute progress percentage.
    $percentage = 0.0;
    if ($totalactivities > 0) {
        $percentage = round(($completedactivities / $totalactivities) * 100, 1);
    } elseif ($iscoursecomplete) {
        $percentage = 100.0;
    }

    // Status classification: completed, inprogress, notstarted.
    if ($iscoursecomplete || ($totalactivities > 0 && $completedactivities === $totalactivities)) {
        $status = 'completed';
    } elseif ($completedactivities > 0) {
        $status = 'inprogress';
    } else {
        $status = 'notstarted';
    }

    return (object)[
        'userid'              => (int)$user->id,
        'user'                => $user,
        'completedactivities' => $completedactivities,
        'totalactivities'     => $totalactivities,
        'percentage'          => $percentage,
        'status'              => $status,
        'iscoursecomplete'    => $iscoursecomplete,
        'timecompleted'       => $timecompleted,
        'activitystates'      => $activitystates,
    ];
}

/**
 * Calculate aggregate summary metrics for the cohort.
 *
 * @param stdClass $course Course object.
 * @param completion_info $completion Completion info instance.
 * @param array $activities Tracked activities.
 * @param array $allusers All enrolled users in scope.
 * @return stdClass Summary metrics.
 */
function report_idgprogress_calculate_summary_metrics(
    stdClass $course,
    completion_info $completion,
    array $activities,
    array $allusers
): stdClass {
    $total = count($allusers);
    $completedcount = 0;
    $inprogresscount = 0;
    $notstartedcount = 0;
    $sumpercentage = 0.0;

    foreach ($allusers as $user) {
        $data = report_idgprogress_get_student_completion_data($course, $completion, $activities, $user);
        $sumpercentage += $data->percentage;

        if ($data->status === 'completed') {
            $completedcount++;
        } elseif ($data->status === 'inprogress') {
            $inprogresscount++;
        } else {
            $notstartedcount++;
        }
    }

    $avgprogress = $total > 0 ? round($sumpercentage / $total, 1) : 0.0;

    return (object)[
        'totalenrolled'   => $total,
        'completedcourse' => $completedcount,
        'inprogress'      => $inprogresscount,
        'notstarted'      => $notstartedcount,
        'avgprogress'     => $avgprogress,
    ];
}

/**
 * Render visual progress bar HTML compliant with Bootstrap 4/5.
 *
 * @param float $percentage Completion percentage.
 * @param string $status Status key ('completed', 'inprogress', 'notstarted').
 * @return string HTML output.
 */
function report_idgprogress_render_progress_bar(float $percentage, string $status): string {
    $colorclass = match ($status) {
        'completed'  => 'bg-success',
        'inprogress' => 'bg-info',
        default      => 'bg-secondary',
    };

    $percenttext = $percentage . '%';

    return '
        <div class="progress" style="height: 18px; min-width: 90px;" title="' . s($percenttext) . '">
            <div class="progress-bar ' . $colorclass . '" role="progressbar"
                 style="width: ' . $percentage . '%;"
                 aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="100">
                <span style="font-size: 11px; font-weight: 600; line-height: 18px;">' . s($percenttext) . '</span>
            </div>
        </div>';
}

/**
 * Render localized status badge HTML.
 *
 * @param string $status Status key ('completed', 'inprogress', 'notstarted').
 * @return string HTML output.
 */
function report_idgprogress_render_status_badge(string $status): string {
    $badgetype = match ($status) {
        'completed'  => 'badge bg-success badge-success text-white',
        'inprogress' => 'badge bg-primary badge-primary text-white',
        default      => 'badge bg-secondary badge-secondary text-white',
    };

    $label = get_string('status_' . $status, 'report_idgprogress');

    return '<span class="' . $badgetype . ' px-2 py-1" style="font-size: 0.85rem; font-weight: 500;">' . s($label) . '</span>';
}
