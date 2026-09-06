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

    if (class_exists('\core_user\fields')) {
        $userfieldsapi = \core_user\fields::for_userpic()->including('institution', 'department', 'username', 'email');
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
    } else {
        $userfields = user_picture::fields('u', ['institution', 'department', 'username', 'email']);
    }

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
/**
 * Bulk pre-fetch all activity and course completion records for an entire cohort.
 * Replaces thousands of individual database queries with just 2 batched queries.
 *
 * @param int $courseid Course ID.
 * @param array $activityids Array of course module IDs (tracked activities).
 * @param array $userids Array of user IDs.
 * @return stdClass Object containing ->modules[userid][cmid] and ->course[userid].
 */
function report_idgprogress_load_cohort_completion_cache(int $courseid, array $activityids, array $userids): stdClass {
    global $DB;

    $cache = (object)[
        'modules' => [],
        'course'  => [],
    ];

    if (empty($userids)) {
        return $cache;
    }

    $cleanuserids = array_values(array_unique(array_filter(array_map('intval', $userids))));
    if (empty($cleanuserids)) {
        return $cache;
    }

    $cleanactids = array_values(array_unique(array_filter(array_map('intval', $activityids))));

    // Chunk user IDs in batches of 500 to stay well within SQL parameter limits.
    $userchunks = array_chunk($cleanuserids, 500);

    foreach ($userchunks as $chunk) {
        [$usql, $uparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'u');

        // 1. Bulk load course-level completions.
        $csql = "SELECT id, userid, timecompleted
                   FROM {course_completions}
                  WHERE course = :courseid AND userid {$usql}";
        $cparams = array_merge(['courseid' => $courseid], $uparams);

        try {
            $coursecompletions = $DB->get_records_sql($csql, $cparams);
            foreach ($coursecompletions as $cc) {
                $cache->course[$cc->userid] = (object)[
                    'iscomplete'    => !empty($cc->timecompleted),
                    'timecompleted' => (int)$cc->timecompleted,
                ];
            }
        } catch (\Throwable $e) {
            // Ignore failure, fallback gracefully.
        }

        // 2. Bulk load activity completion states.
        if (!empty($cleanactids)) {
            $actchunks = array_chunk($cleanactids, 500);
            foreach ($actchunks as $actchunk) {
                [$asql, $aparams] = $DB->get_in_or_equal($actchunk, SQL_PARAMS_NAMED, 'a');

                $msql = "SELECT id, coursemoduleid, userid, completionstate, timemodified
                           FROM {course_modules_completion}
                          WHERE coursemoduleid {$asql} AND userid {$usql}";
                $mparams = array_merge($aparams, $uparams);

                try {
                    $modcompletions = $DB->get_records_sql($msql, $mparams);
                    foreach ($modcompletions as $mc) {
                        if (!isset($cache->modules[$mc->userid])) {
                            $cache->modules[$mc->userid] = [];
                        }
                        $cache->modules[$mc->userid][$mc->coursemoduleid] = (object)[
                            'completionstate' => (int)$mc->completionstate,
                            'timemodified'    => (int)$mc->timemodified,
                        ];
                    }
                } catch (\Throwable $e) {
                    // Ignore failure, fallback gracefully.
                }
            }
        }
    }

    return $cache;
}

/**
 * Calculate individual student completion data, percentage, and activity breakdown.
 *
 * @param stdClass $course Course object.
 * @param completion_info $completion Completion info instance.
 * @param array $activities Array of tracked course activities.
 * @param stdClass $user User object.
 * @param stdClass|null $cohortcache Pre-fetched cohort completion cache for high performance.
 * @return stdClass Progress data object.
 */
function report_idgprogress_get_student_completion_data(
    stdClass $course,
    completion_info $completion,
    array $activities,
    stdClass $user,
    ?stdClass $cohortcache = null
): stdClass {
    $totalactivities = count($activities);
    $completedactivities = 0;
    $activitystates = [];

    foreach ($activities as $cmid => $activity) {
        $cmid = (int)$activity->id;

        if ($cohortcache !== null) {
            if (isset($cohortcache->modules[$user->id][$cmid])) {
                $cstate = $cohortcache->modules[$user->id][$cmid]->completionstate;
                $ctimemodified = $cohortcache->modules[$user->id][$cmid]->timemodified;
            } else {
                $cstate = COMPLETION_INCOMPLETE;
                $ctimemodified = 0;
            }
        } else {
            $cdata = $completion->get_data($activity, false, $user->id);
            $cstate = (int)$cdata->completionstate;
            $ctimemodified = $cdata->timemodified ?? 0;
        }

        $iscompleted = in_array(
            $cstate,
            [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS],
            true
        );

        if ($iscompleted) {
            $completedactivities++;
        }

        $activitystates[$cmid] = (object)[
            'cmid'         => $cmid,
            'name'         => $activity->name,
            'state'        => $cstate,
            'iscompleted'  => $iscompleted,
            'timemodified' => $ctimemodified,
        ];
    }

    // Determine course-level completion if criteria are set.
    $iscoursecomplete = false;
    $timecompleted = 0;

    if ($cohortcache !== null) {
        if (isset($cohortcache->course[$user->id])) {
            $iscoursecomplete = $cohortcache->course[$user->id]->iscomplete;
            $timecompleted = $cohortcache->course[$user->id]->timecompleted;
        }
    } else {
        if ($completion->is_enabled()) {
            $iscoursecomplete = $completion->is_course_complete($user->id);
            if ($iscoursecomplete) {
                $ccompletion = new completion_completion(['userid' => $user->id, 'course' => $course->id]);
                $timecompleted = !empty($ccompletion->timecompleted) ? (int)$ccompletion->timecompleted : 0;
            }
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

    // Find the latest completed activity timestamp.
    $lastactivitytime = 0;
    foreach ($activitystates as $actstate) {
        if (!empty($actstate->iscompleted) && !empty($actstate->timemodified) && (int)$actstate->timemodified > $lastactivitytime) {
            $lastactivitytime = (int)$actstate->timemodified;
        }
    }

    // If student completed all activities but Moodle course_completions record
    // is missing or cron has not written it yet, fallback to the timestamp of
    // the final completed activity.
    if ($status === 'completed' && $timecompleted <= 0) {
        $timecompleted = $lastactivitytime;
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
        'lastactivitytime'    => $lastactivitytime,
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
 * @param stdClass|null $cohortcache Optional pre-fetched completion cache.
 * @return stdClass Summary metrics including ->cohortcache.
 */
function report_idgprogress_calculate_summary_metrics(
    stdClass $course,
    completion_info $completion,
    array $activities,
    array $allusers,
    ?stdClass $cohortcache = null
): stdClass {
    $total = count($allusers);
    $completedcount = 0;
    $inprogresscount = 0;
    $notstartedcount = 0;
    $sumpercentage = 0.0;

    if ($cohortcache === null && !empty($allusers) && !empty($activities)) {
        $cohortcache = report_idgprogress_load_cohort_completion_cache(
            (int)$course->id,
            array_keys($activities),
            array_keys($allusers)
        );
    }

    foreach ($allusers as $user) {
        $data = report_idgprogress_get_student_completion_data($course, $completion, $activities, $user, $cohortcache);
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
        'cohortcache'     => $cohortcache,
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
    switch ($status) {
        case 'completed':
            $colorclass = 'bg-success';
            break;
        case 'inprogress':
            $colorclass = 'bg-info';
            break;
        default:
            $colorclass = 'bg-secondary';
            break;
    }

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
    switch ($status) {
        case 'completed':
            $badgetype = 'badge bg-success badge-success text-white';
            break;
        case 'inprogress':
            $badgetype = 'badge bg-primary badge-primary text-white';
            break;
        default:
            $badgetype = 'badge bg-secondary badge-secondary text-white';
            break;
    }

    $label = get_string('status_' . $status, 'report_idgprogress');

    return '<span class="' . $badgetype . ' px-2 py-1" style="font-size: 0.85rem; font-weight: 500;">' . s($label) . '</span>';
}

/**
 * Retrieve all custom user profile fields defined on this Moodle site.
 *
 * @return array Array of user_info_field records indexed by id.
 */
function report_idgprogress_get_custom_profile_fields(): array {
    global $DB;
    try {
        return $DB->get_records('user_info_field', null, 'sortorder ASC, id ASC');
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * Bulk load custom profile field data for a list of user IDs in a single query.
 *
 * @param array $userids Array of user IDs.
 * @return array Array of [userid => [field_shortname => formatted_value]].
 */
function report_idgprogress_get_users_custom_fields(array $userids): array {
    global $DB;

    if (empty($userids)) {
        return [];
    }

    $fields = report_idgprogress_get_custom_profile_fields();
    if (empty($fields)) {
        return [];
    }

    // Pre-parse menu/dropdown options for fast display mapping.
    $menuoptions = [];
    foreach ($fields as $field) {
        if ($field->datatype === 'menu' && !empty($field->param1)) {
            $lines = explode("\n", str_replace("\r", "", $field->param1));
            $options = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $options[] = $trimmed;
                }
            }
            $menuoptions[$field->id] = $options;
        }
    }

    $userids = array_unique(array_map('intval', $userids));
    [$insql, $params] = $DB->get_in_or_equal($userids);

    try {
        $records = $DB->get_records_sql(
            "SELECT d.id, d.userid, d.fieldid, d.data
               FROM {user_info_data} d
              WHERE d.userid {$insql}",
            $params
        );
    } catch (\Throwable $e) {
        return [];
    }

    $userdata = [];
    foreach ($userids as $uid) {
        $userdata[$uid] = [];
    }

    foreach ($records as $rec) {
        if (!isset($fields[$rec->fieldid])) {
            continue;
        }
        $field = $fields[$rec->fieldid];
        $val = trim((string)$rec->data);

        // Map numeric index to menu label if applicable.
        if ($field->datatype === 'menu' && isset($menuoptions[$field->id])) {
            $opts = $menuoptions[$field->id];
            if (is_numeric($val) && isset($opts[(int)$val])) {
                $val = $opts[(int)$val];
            }
        }

        $userdata[$rec->userid][$field->shortname] = $val;
    }

    return $userdata;
}

/**
 * Extract gender value from user record or custom profile fields.
 *
 * @param stdClass $user User record.
 * @param array $usercustomfields Array of custom field shortname => value for this user.
 * @return string Gender string or '-' if not specified.
 */
function report_idgprogress_get_user_gender(stdClass $user, array $usercustomfields = []): string {
    // 1. Check exact common shortnames in custom fields.
    $targetkeys = ['gender', 'sex', 'Gender', 'Sex', 'user_gender', 'gender_km'];
    foreach ($targetkeys as $key) {
        if (!empty($usercustomfields[$key])) {
            return trim($usercustomfields[$key]);
        }
    }

    // 2. Check case-insensitively across custom fields.
    foreach ($usercustomfields as $k => $v) {
        $lk = strtolower($k);
        $hasgender = ($lk === 'gender' || $lk === 'sex' || strpos($lk, 'gender') !== false || strpos($lk, 'sex') !== false || strpos($k, 'ភេទ') !== false);
        if ($hasgender && !empty($v)) {
            return trim((string)$v);
        }
    }

    // 3. Check directly on user object properties.
    if (!empty($user->gender)) {
        return trim((string)$user->gender);
    }
    if (!empty($user->profile_field_gender)) {
        return trim((string)$user->profile_field_gender);
    }
    if (!empty($user->profile['gender'])) {
        return trim((string)$user->profile['gender']);
    }

    return '-';
}

/**
 * Safe string retrieval with graceful fallback to prevent debugging() warnings
 * if Moodle string cache has not yet been refreshed.
 *
 * @param string $identifier The key identifier.
 * @param string $fallback Fallback string if missing in cache.
 * @param mixed $a Variable argument for get_string.
 * @return string The resolved localized string.
 */
function report_idgprogress_str(string $identifier, string $fallback = '', $a = null): string {
    $sm = get_string_manager();
    if ($sm->string_exists($identifier, 'report_idgprogress')) {
        return get_string($identifier, 'report_idgprogress', $a);
    }
    // Check known alternative keys.
    if ($identifier === 'exportoptions' && $sm->string_exists('exportexcel', 'report_idgprogress')) {
        return get_string('exportexcel', 'report_idgprogress');
    }
    if ($identifier === 'table_gender' && $sm->string_exists('gender', 'moodle')) {
        return get_string('gender', 'moodle');
    }

    if ($fallback !== '') {
        return $a !== null ? str_replace('{$a}', (string)$a, $fallback) : $fallback;
    }
    return $identifier;
}


