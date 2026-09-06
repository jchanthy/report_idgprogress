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
 * Main entry point for the IDG Progress Report.
 *
 * @package    report_idgprogress
 * @copyright  2024 Cambodia Academy of Digital Technology (CADT)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once(__DIR__ . '/lib.php');

$id       = required_param('id', PARAM_INT);
$groupid  = optional_param('group', 0, PARAM_INT);
$search   = optional_param('search', '', PARAM_NOTAGS);
$page     = optional_param('page', 0, PARAM_INT);
$perpage  = optional_param('perpage', 25, PARAM_INT);

// Validate course and user authentication.
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($course->id);
require_capability('report/idgprogress:view', $context);

// Handle group mode permissions.
$groupmode = groups_get_course_groupmode($course);
$hasallgroups = has_capability('moodle/site:accessallgroups', $context);
if ($groupmode === SEPARATEGROUPS && !$hasallgroups) {
    $allowedgroups = groups_get_all_groups($course->id, $USER->id);
    $allowedgroupids = array_keys($allowedgroups);
    if (!in_array($groupid, $allowedgroupids, true)) {
        $groupid = !empty($allowedgroupids) ? (int)reset($allowedgroupids) : -1;
    }
}

// Setup page URL and layout.
$baseurl = new moodle_url('/report/idgprogress/index.php', ['id' => $course->id]);
if ($groupid !== 0) {
    $baseurl->param('group', $groupid);
}
if ($search !== '') {
    $baseurl->param('search', $search);
}
if ($perpage !== 25) {
    $baseurl->param('perpage', $perpage);
}

$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('report');
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'report_idgprogress') . ': ' . format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

// Completion tracking verification.
$completion = new completion_info($course);
$iscompletionenabled = $completion->is_enabled();
$trackedactivities = $iscompletionenabled ? report_idgprogress_get_tracked_activities($completion) : [];

// Output header.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pageheading', 'report_idgprogress'), 2, 'mb-4');

// Show notification if completion tracking is disabled.
if (!$iscompletionenabled) {
    echo $OUTPUT->notification(get_string('completionnotenabled', 'report_idgprogress'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

if (empty($trackedactivities)) {
    echo $OUTPUT->notification(get_string('nocriteriaset', 'report_idgprogress'), 'info');
}

// Fetch all enrolled users for summary metrics calculation.
$allcohortusers = [];
if ($groupid !== -1) {
    $allcohortusers = report_idgprogress_get_enrolled_users($context, $groupid, $search, 'u.lastname ASC, u.firstname ASC', 0, 0);
}
$metrics = report_idgprogress_calculate_summary_metrics($course, $completion, $trackedactivities, $allcohortusers);

// Render Metrics Dashboard Cards.
?>
<div class="row mb-4 g-3">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-primary h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold"><?php echo s(get_string('totalenrolled', 'report_idgprogress')); ?></div>
                <div class="fs-3 fw-bold text-dark mt-1"><?php echo (int)$metrics->totalenrolled; ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-success h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold"><?php echo s(get_string('completedcourse', 'report_idgprogress')); ?></div>
                <div class="fs-3 fw-bold text-success mt-1"><?php echo (int)$metrics->completedcourse; ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-info h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold"><?php echo s(get_string('inprogress', 'report_idgprogress')); ?></div>
                <div class="fs-3 fw-bold text-info mt-1"><?php echo (int)$metrics->inprogress; ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 border-start border-4 border-warning h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold"><?php echo s(get_string('avgprogress', 'report_idgprogress')); ?></div>
                <div class="fs-3 fw-bold text-warning mt-1"><?php echo $metrics->avgprogress; ?>%</div>
            </div>
        </div>
    </div>
</div>

<!-- Controls: Filter, Search, and Export -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row align-items-center g-3">
            <div class="col-md-5 col-lg-4">
                <?php
                if ($groupmode) {
                    groups_print_course_menu($course, $baseurl);
                }
                ?>
            </div>
            <div class="col-md-7 col-lg-5">
                <form method="get" action="<?php echo s(new moodle_url('/report/idgprogress/index.php')); ?>" class="d-flex gap-2">
                    <input type="hidden" name="id" value="<?php echo (int)$course->id; ?>" />
                    <?php if ($groupid): ?>
                        <input type="hidden" name="group" value="<?php echo (int)$groupid; ?>" />
                    <?php endif; ?>
                    <input type="text" name="search" class="form-control"
                           placeholder="<?php echo s(get_string('searchparticipant', 'report_idgprogress')); ?>"
                           value="<?php echo s($search); ?>" />
                    <button type="submit" class="btn btn-secondary text-nowrap">
                        <i class="fa fa-search" aria-hidden="true"></i> <?php echo s(get_string('search', 'report_idgprogress')); ?>
                    </button>
                    <?php if ($search !== ''): ?>
                        <a href="<?php echo s(new moodle_url('/report/idgprogress/index.php', ['id' => $course->id, 'group' => $groupid])); ?>"
                           class="btn btn-outline-secondary text-nowrap">
                            <?php echo s(get_string('clear', 'report_idgprogress')); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-12 col-lg-3 text-lg-end">
                <?php
                $exporturl = new moodle_url('/report/idgprogress/export.php', [
                    'id'    => $course->id,
                    'group' => $groupid,
                ]);
                if ($search !== '') {
                    $exporturl->param('search', $search);
                }
                ?>
                <a href="<?php echo s($exporturl); ?>" class="btn btn-success text-nowrap">
                    <i class="fa fa-download" aria-hidden="true"></i> <?php echo s(get_string('exportcsv', 'report_idgprogress')); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Pagination and paged user data retrieval.
$totalcount = count($allcohortusers);
$pagedusers = [];
$pagedcustomfields = [];
if ($groupid !== -1 && $totalcount > 0) {
    $pagedusers = report_idgprogress_get_enrolled_users(
        $context,
        $groupid,
        $search,
        'u.lastname ASC, u.firstname ASC',
        $page * $perpage,
        $perpage
    );
    if (!empty($pagedusers)) {
        $pagedcustomfields = report_idgprogress_get_users_custom_fields(array_keys($pagedusers));
    }
}

if (empty($pagedusers)) {
    echo $OUTPUT->notification(get_string('noparticipantsfound', 'report_idgprogress'), 'info');
} else {
    // Render Paged Table.
    ?>
    <div class="table-responsive shadow-sm rounded bg-white mb-4">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="min-width: 200px;"><?php echo s(get_string('table_fullname', 'report_idgprogress')); ?></th>
                    <th scope="col" class="text-center" style="min-width: 80px;"><?php echo s(get_string('table_gender', 'report_idgprogress')); ?></th>
                    <th scope="col"><?php echo s(get_string('table_email', 'report_idgprogress')); ?></th>
                    <th scope="col"><?php echo s(get_string('table_institution', 'report_idgprogress')); ?></th>
                    <th scope="col"><?php echo s(get_string('table_department', 'report_idgprogress')); ?></th>
                    <th scope="col" class="text-center"><?php echo s(get_string('table_activities', 'report_idgprogress')); ?></th>
                    <th scope="col" style="min-width: 140px;"><?php echo s(get_string('table_progress', 'report_idgprogress')); ?></th>
                    <th scope="col" class="text-center"><?php echo s(get_string('table_status', 'report_idgprogress')); ?></th>
                    <th scope="col"><?php echo s(get_string('table_completeddate', 'report_idgprogress')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($pagedusers as $user) {
                    $studentdata = report_idgprogress_get_student_completion_data(
                        $course,
                        $completion,
                        $trackedactivities,
                        $user
                    );

                    $userurl = new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $course->id]);
                    $userpic = $OUTPUT->user_picture($user, ['size' => 35, 'link' => false]);
                    $fullname = fullname($user);
                    $usercustom = $pagedcustomfields[$user->id] ?? [];
                    $gender = report_idgprogress_get_user_gender($user, $usercustom);
                    $institution = !empty($user->institution) ? $user->institution : '-';
                    $department = !empty($user->department) ? $user->department : '-';
                    $activitiesratio = $studentdata->completedactivities . ' / ' . $studentdata->totalactivities;
                    $progressbar = report_idgprogress_render_progress_bar($studentdata->percentage, $studentdata->status);
                    $statusbadge = report_idgprogress_render_status_badge($studentdata->status);
                    $completeddate = $studentdata->timecompleted > 0 ? userdate($studentdata->timecompleted, get_string('strftimedatetime', 'langconfig')) : '-';
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php echo $userpic; ?>
                                <div>
                                    <a href="<?php echo s($userurl); ?>" class="fw-bold text-decoration-none">
                                        <?php echo s($fullname); ?>
                                    </a>
                                    <div class="text-muted small">@<?php echo s($user->username); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border px-2 py-1"><?php echo s($gender); ?></span>
                        </td>
                        <td>
                            <span class="text-muted"><?php echo s($user->email); ?></span>
                        </td>
                        <td><?php echo s($institution); ?></td>
                        <td><?php echo s($department); ?></td>
                        <td class="text-center fw-semibold">
                            <?php echo s($activitiesratio); ?>
                        </td>
                        <td>
                            <?php echo $progressbar; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $statusbadge; ?>
                        </td>
                        <td>
                            <span class="small text-muted"><?php echo s($completeddate); ?></span>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
