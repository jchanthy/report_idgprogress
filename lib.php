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
        $userfieldsapi = \core_user\fields::for_userpic()->including(
            'institution', 'department', 'username', 'email', 'phone1', 'phone2', 'lastaccess'
        );
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
    } else {
        $userfields = user_picture::fields('u', [
            'institution', 'department', 'username', 'email', 'phone1', 'phone2', 'lastaccess'
        ]);
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

    return '<span class="' . $badgetype . ' rounded-pill px-3 py-1 fw-bold font-weight-bold" style="font-size: 0.8rem; letter-spacing: 0.2px;">' . s($label) . '</span>';
}

/**
 * Retrieve all group memberships for users in a course in a single fast query.
 *
 * @param int $courseid Course ID.
 * @return array Associative array of [userid => ['groupname1', 'groupname2', ...]].
 */
function report_idgprogress_get_course_user_groups(int $courseid): array {
    global $DB;
    $groupsbyuser = [];
    try {
        $sql = "SELECT gm.id, gm.userid, g.name AS groupname
                  FROM {groups_members} gm
                  JOIN {groups} g ON g.id = gm.groupid
                 WHERE g.courseid = :courseid
              ORDER BY g.name ASC";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        foreach ($records as $rec) {
            $groupsbyuser[$rec->userid][] = $rec->groupname;
        }
    } catch (\Throwable $e) {
        // Fallback gracefully.
    }
    return $groupsbyuser;
}

/**
 * Retrieve user course roles in a single batch query.
 *
 * @param int $contextid Context ID of the course.
 * @return array Associative array of [userid => ['Student', ...]].
 */
function report_idgprogress_get_course_user_roles(int $contextid): array {
    global $DB;
    $rolesbyuser = [];
    try {
        $sql = "SELECT ra.id, ra.userid, r.name AS rolename, r.shortname
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.contextid = :contextid
              ORDER BY r.sortorder ASC, r.id ASC";
        $records = $DB->get_records_sql($sql, ['contextid' => $contextid]);
        foreach ($records as $rec) {
            $name = !empty($rec->rolename) ? $rec->rolename : ucfirst($rec->shortname);
            $rolesbyuser[$rec->userid][] = $name;
        }
    } catch (\Throwable $e) {
        // Fallback gracefully.
    }
    return $rolesbyuser;
}

/**
 * Retrieve last course access timestamp for all users in a course.
 *
 * @param int $courseid Course ID.
 * @return array Associative array of [userid => timeaccess].
 */
function report_idgprogress_get_course_lastaccess(int $courseid): array {
    global $DB;
    try {
        return $DB->get_records_menu(
            'user_lastaccess',
            ['courseid' => $courseid],
            '',
            'userid, timeaccess'
        );
    } catch (\Throwable $e) {
        return [];
    }
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

/**
 * Retrieve comprehensive course progress payload for API and external systems.
 *
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @param int $groupid Filter by group ID (0 = all).
 * @param string $progressfilter Progress filter ('all', 'inprogress', 'completed', 'notstarted', 'under100', 'under50').
 * @param string $search Search query.
 * @param int $page Pagination page index (0-based).
 * @param int $perpage Number of records per page (0 = all).
 * @param bool $includeactivities Whether to include per-activity completion details.
 * @return array Formatted array matching external API response schema.
 */
function report_idgprogress_get_api_progress_data(
    stdClass $course,
    context_course $context,
    int $groupid = 0,
    string $progressfilter = 'all',
    string $search = '',
    int $page = 0,
    int $perpage = 0,
    bool $includeactivities = false
): array {
    global $CFG, $DB;
    require_once($CFG->libdir . '/completionlib.php');

    $completion = new completion_info($course);
    $iscompletionenabled = $completion->is_enabled();
    $trackedactivities = $iscompletionenabled ? report_idgprogress_get_tracked_activities($completion) : [];

    // Summary metrics on the full cohort.
    $allcohortusers = [];
    if ($groupid !== -1) {
        $allcohortusers = report_idgprogress_get_enrolled_users($context, $groupid, $search, 'u.lastname ASC, u.firstname ASC', 0, 0);
    }
    $metrics = report_idgprogress_calculate_summary_metrics($course, $completion, $trackedactivities, $allcohortusers);
    $cohortcache = $metrics->cohortcache ?? null;

    // Filter cohort participants by progress status if active.
    $filteredcohortusers = [];
    if ($groupid !== -1 && !empty($allcohortusers)) {
        if ($progressfilter !== 'all') {
            foreach ($allcohortusers as $uid => $user) {
                $sdata = report_idgprogress_get_student_completion_data(
                    $course,
                    $completion,
                    $trackedactivities,
                    $user,
                    $cohortcache
                );
                $include = false;
                switch ($progressfilter) {
                    case 'inprogress':
                        $include = ($sdata->status === 'inprogress');
                        break;
                    case 'completed':
                        $include = ($sdata->status === 'completed');
                        break;
                    case 'notstarted':
                        $include = ($sdata->status === 'notstarted');
                        break;
                    case 'under100':
                        $include = ($sdata->percentage < 100);
                        break;
                    case 'under50':
                        $include = ($sdata->percentage < 50);
                        break;
                    default:
                        $include = true;
                        break;
                }
                if ($include) {
                    $filteredcohortusers[$uid] = $user;
                }
            }
        } else {
            $filteredcohortusers = $allcohortusers;
        }
    }

    $totalparticipants = count($filteredcohortusers);
    if ($perpage > 0) {
        $pagedusers = array_slice($filteredcohortusers, $page * $perpage, $perpage, true);
    } else {
        $pagedusers = $filteredcohortusers;
    }

    $userids = array_keys($pagedusers);
    $pagedcustomfields = !empty($userids) ? report_idgprogress_get_users_custom_fields($userids) : [];
    $sitecustomfields = report_idgprogress_get_custom_profile_fields();
    $courseusergroups = report_idgprogress_get_course_user_groups($course->id);
    $courseuserroles = report_idgprogress_get_course_user_roles($context->id);
    $courselastaccess = report_idgprogress_get_course_lastaccess($course->id);

    // Format tracked activities list.
    $trackedlist = [];
    foreach ($trackedactivities as $cmid => $act) {
        $trackedlist[] = [
            'id'      => (int)$cmid,
            'name'    => strip_tags(format_string($act->name, true, ['context' => $context])),
            'modname' => (string)$act->modname,
        ];
    }

    // Format participants list.
    $participantslist = [];
    foreach ($pagedusers as $user) {
        $sdata = report_idgprogress_get_student_completion_data(
            $course,
            $completion,
            $trackedactivities,
            $user,
            $cohortcache
        );

        $ucustom = $pagedcustomfields[$user->id] ?? [];
        $phone = !empty($user->phone1) ? (string)$user->phone1 : (!empty($user->phone2) ? (string)$user->phone2 : '');
        $department = !empty($user->department) ? (string)$user->department : '';
        $institution = !empty($user->institution) ? (string)$user->institution : '';
        $ugroups = $courseusergroups[$user->id] ?? [];
        $uroles = $courseuserroles[$user->id] ?? [];
        $timeaccess = $courselastaccess[$user->id] ?? 0;
        $lastaccessstr = $timeaccess > 0 ? format_time(time() - $timeaccess) : get_string('never');

        $timecompleted = ($sdata->status === 'completed' && $sdata->timecompleted > 0) ? (int)$sdata->timecompleted : 0;
        $timecompletedstr = $timecompleted > 0 ? userdate($timecompleted, get_string('strftimedatetime', 'langconfig')) : '';

        $lastacttime = (!empty($sdata->lastactivitytime) && $sdata->lastactivitytime > 0) ? (int)$sdata->lastactivitytime : 0;
        $lastacttimestr = $lastacttime > 0 ? userdate($lastacttime, get_string('strftimedatetime', 'langconfig')) : '';

        // Custom fields array.
        $customfieldslist = [];
        foreach ($sitecustomfields as $cf) {
            $val = !empty($ucustom[$cf->shortname]) ? (string)$ucustom[$cf->shortname] : '';
            $customfieldslist[] = [
                'shortname' => (string)$cf->shortname,
                'name'      => strip_tags(format_string($cf->name, true, ['context' => $context])),
                'value'     => $val,
            ];
        }

        // Per-activity breakdown if requested.
        $actdetails = [];
        if ($includeactivities && !empty($trackedactivities)) {
            foreach ($trackedactivities as $cmid => $act) {
                $iscomp = false;
                $acttime = 0;
                $acttimestr = '';
                if (isset($sdata->activitystates[$cmid])) {
                    $astate = $sdata->activitystates[$cmid];
                    $iscomp = (bool)$astate->iscompleted;
                    $acttime = (int)$astate->timemodified;
                    if ($acttime > 0) {
                        $acttimestr = userdate($acttime, get_string('strftimedatetime', 'langconfig'));
                    }
                }
                $actdetails[] = [
                    'cmid'                   => (int)$cmid,
                    'name'                   => strip_tags(format_string($act->name, true, ['context' => $context])),
                    'iscompleted'            => $iscomp,
                    'timemodified'           => $acttime,
                    'timemodified_formatted' => $acttimestr,
                ];
            }
        }

        $participantslist[] = [
            'id'                         => (int)$user->id,
            'username'                   => (string)$user->username,
            'firstname'                  => (string)$user->firstname,
            'lastname'                   => (string)$user->lastname,
            'fullname'                   => (string)fullname($user),
            'email'                      => (string)$user->email,
            'phone'                      => $phone,
            'department'                 => $department,
            'institution'                => $institution,
            'roles'                      => !empty($uroles) ? implode(', ', $uroles) : get_string('student', 'moodle', 'Student'),
            'groups'                     => !empty($ugroups) ? implode(', ', $ugroups) : get_string('nogroups', 'group'),
            'lastaccess'                 => (int)$timeaccess,
            'lastaccess_formatted'       => (string)$lastaccessstr,
            'completed_activities'       => (int)$sdata->completedactivities,
            'total_activities'           => (int)$sdata->totalactivities,
            'percentage'                 => (int)$sdata->percentage,
            'status'                     => (string)$sdata->status,
            'status_label'               => (string)get_string('status_' . $sdata->status, 'report_idgprogress'),
            'timecompleted'              => $timecompleted,
            'timecompleted_formatted'    => $timecompletedstr,
            'lastactivitytime'           => $lastacttime,
            'lastactivitytime_formatted' => $lastacttimestr,
            'custom_fields'              => $customfieldslist,
            'activities_detail'          => $actdetails,
        ];
    }

    return [
        'course' => [
            'id'                 => (int)$course->id,
            'fullname'           => (string)format_string($course->fullname, true, ['context' => $context]),
            'shortname'          => (string)format_string($course->shortname, true, ['context' => $context]),
            'completion_enabled' => (bool)$iscompletionenabled,
        ],
        'summary' => [
            'total_enrolled' => (int)$metrics->totalenrolled,
            'completed'      => (int)$metrics->completedcount,
            'in_progress'    => (int)$metrics->inprogresscount,
            'not_started'    => (int)$metrics->notstartedcount,
            'avg_progress'   => (float)$metrics->avgprogress,
        ],
        'tracked_activities' => $trackedlist,
        'total_participants' => (int)$totalparticipants,
        'participants'       => $participantslist,
    ];
}

/**
 * Retrieve detailed single-student progress payload for API.
 *
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @param int $userid User ID.
 * @return array Formatted student progress record.
 */
function report_idgprogress_get_api_user_progress_data(
    stdClass $course,
    context_course $context,
    int $userid
): array {
    global $CFG, $DB;
    require_once($CFG->libdir . '/completionlib.php');

    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
    $completion = new completion_info($course);
    $iscompletionenabled = $completion->is_enabled();
    $trackedactivities = $iscompletionenabled ? report_idgprogress_get_tracked_activities($completion) : [];

    $sdata = report_idgprogress_get_student_completion_data(
        $course,
        $completion,
        $trackedactivities,
        $user,
        null
    );

    $allcustom = report_idgprogress_get_users_custom_fields([$user->id]);
    $usercustom = $allcustom[$user->id] ?? [];
    $sitecustomfields = report_idgprogress_get_custom_profile_fields();
    $courseusergroups = report_idgprogress_get_course_user_groups($course->id);
    $courseuserroles = report_idgprogress_get_course_user_roles($context->id);
    $courselastaccess = report_idgprogress_get_course_lastaccess($course->id);

    $phone = !empty($user->phone1) ? (string)$user->phone1 : (!empty($user->phone2) ? (string)$user->phone2 : '');
    $department = !empty($user->department) ? (string)$user->department : '';
    $institution = !empty($user->institution) ? (string)$user->institution : '';
    $ugroups = $courseusergroups[$user->id] ?? [];
    $uroles = $courseuserroles[$user->id] ?? [];
    $timeaccess = $courselastaccess[$user->id] ?? 0;
    $lastaccessstr = $timeaccess > 0 ? format_time(time() - $timeaccess) : get_string('never');

    $timecompleted = ($sdata->status === 'completed' && $sdata->timecompleted > 0) ? (int)$sdata->timecompleted : 0;
    $timecompletedstr = $timecompleted > 0 ? userdate($timecompleted, get_string('strftimedatetime', 'langconfig')) : '';

    $lastacttime = (!empty($sdata->lastactivitytime) && $sdata->lastactivitytime > 0) ? (int)$sdata->lastactivitytime : 0;
    $lastacttimestr = $lastacttime > 0 ? userdate($lastacttime, get_string('strftimedatetime', 'langconfig')) : '';

    $customfieldslist = [];
    foreach ($sitecustomfields as $cf) {
        $val = !empty($usercustom[$cf->shortname]) ? (string)$usercustom[$cf->shortname] : '';
        $customfieldslist[] = [
            'shortname' => (string)$cf->shortname,
            'name'      => strip_tags(format_string($cf->name, true, ['context' => $context])),
            'value'     => $val,
        ];
    }

    $actdetails = [];
    foreach ($trackedactivities as $cmid => $act) {
        $iscomp = false;
        $acttime = 0;
        $acttimestr = '';
        if (isset($sdata->activitystates[$cmid])) {
            $astate = $sdata->activitystates[$cmid];
            $iscomp = (bool)$astate->iscompleted;
            $acttime = (int)$astate->timemodified;
            if ($acttime > 0) {
                $acttimestr = userdate($acttime, get_string('strftimedatetime', 'langconfig'));
            }
        }
        $actdetails[] = [
            'cmid'                   => (int)$cmid,
            'name'                   => strip_tags(format_string($act->name, true, ['context' => $context])),
            'iscompleted'            => $iscomp,
            'timemodified'           => $acttime,
            'timemodified_formatted' => $acttimestr,
        ];
    }

    return [
        'course' => [
            'id'                 => (int)$course->id,
            'fullname'           => (string)format_string($course->fullname, true, ['context' => $context]),
            'shortname'          => (string)format_string($course->shortname, true, ['context' => $context]),
            'completion_enabled' => (bool)$iscompletionenabled,
        ],
        'student' => [
            'id'                         => (int)$user->id,
            'username'                   => (string)$user->username,
            'firstname'                  => (string)$user->firstname,
            'lastname'                   => (string)$user->lastname,
            'fullname'                   => (string)fullname($user),
            'email'                      => (string)$user->email,
            'phone'                      => $phone,
            'department'                 => $department,
            'institution'                => $institution,
            'roles'                      => !empty($uroles) ? implode(', ', $uroles) : get_string('student', 'moodle', 'Student'),
            'groups'                     => !empty($ugroups) ? implode(', ', $ugroups) : get_string('nogroups', 'group'),
            'lastaccess'                 => (int)$timeaccess,
            'lastaccess_formatted'       => (string)$lastaccessstr,
            'completed_activities'       => (int)$sdata->completedactivities,
            'total_activities'           => (int)$sdata->totalactivities,
            'percentage'                 => (int)$sdata->percentage,
            'status'                     => (string)$sdata->status,
            'status_label'               => (string)get_string('status_' . $sdata->status, 'report_idgprogress'),
            'timecompleted'              => $timecompleted,
            'timecompleted_formatted'    => $timecompletedstr,
            'lastactivitytime'           => $lastacttime,
            'lastactivitytime_formatted' => $lastacttimestr,
            'custom_fields'              => $customfieldslist,
            'activities_detail'          => $actdetails,
        ],
    ];
}



