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
$cohortcache = $metrics->cohortcache ?? null;

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
                <button type="button" class="btn btn-success text-nowrap shadow-sm"
                        id="btnIdgOpenExport"
                        data-bs-toggle="modal" data-bs-target="#idgExportModal"
                        data-toggle="modal" data-target="#idgExportModal"
                        onclick="report_idgprogress_open_export_modal()">
                    <i class="fa fa-download me-1" aria-hidden="true"></i> <?php echo s(report_idgprogress_str('exportoptions', 'Export Report')); ?>
                </button>
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
                        $user,
                        $cohortcache
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

// Render Export Options & Field Selection Modal.
$sitecustomfields = report_idgprogress_get_custom_profile_fields();
?>
<!-- Modal for Export Options & Field Selection -->
<div class="modal fade" id="idgExportModal" tabindex="-1" aria-labelledby="idgExportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form id="idgExportForm" method="get" action="<?php echo s(new moodle_url('/report/idgprogress/export.php')); ?>" onsubmit="report_idgprogress_on_export_submit()">
                <input type="hidden" name="id" value="<?php echo (int)$course->id; ?>" />
                <?php if ($groupid): ?>
                    <input type="hidden" name="group" value="<?php echo (int)$groupid; ?>" />
                <?php endif; ?>
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="search" value="<?php echo s($search); ?>" />
                <?php endif; ?>

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="idgExportModalLabel">
                        <i class="fa fa-download text-success me-2"></i> <?php echo s(report_idgprogress_str('exportoptions', 'Export Report')); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" onclick="report_idgprogress_close_export_modal()" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Format Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold d-block text-uppercase small text-muted">
                            <?php echo s(report_idgprogress_str('exportformat', 'Export Format')); ?>
                        </label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="card p-3 border flex-fill">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format" id="exportFormatExcel" value="excel" checked>
                                    <label class="form-check-label fw-bold text-success" for="exportFormatExcel">
                                        <i class="fa fa-file-excel-o fa-lg me-1"></i> <?php echo s(report_idgprogress_str('formatexcel', 'Microsoft Excel (.xlsx)')); ?>
                                    </label>
                                </div>
                            </div>
                            <div class="card p-3 border flex-fill">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format" id="exportFormatCsv" value="csv">
                                    <label class="form-check-label fw-bold text-primary" for="exportFormatCsv">
                                        <i class="fa fa-file-text-o fa-lg me-1"></i> <?php echo s(report_idgprogress_str('formatcsv', 'CSV (.csv)')); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Field Selection -->
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <label class="form-label fw-bold mb-0 text-uppercase small text-muted">
                                <?php echo s(report_idgprogress_str('selectfields', 'Select Fields to Export')); ?>
                            </label>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="report_idgprogress_toggle_fields(true)">
                                    <?php echo s(report_idgprogress_str('selectall', 'Select All')); ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="report_idgprogress_toggle_fields(false)">
                                    <?php echo s(report_idgprogress_str('deselectall', 'Deselect All')); ?>
                                </button>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="userid" id="f_userid" onchange="report_idgprogress_on_field_toggle(this)">
                                    <label class="form-check-label" for="f_userid">
                                        <?php echo s(report_idgprogress_str('field_userid', 'User ID')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="username" id="f_username" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_username">
                                        <?php echo s(report_idgprogress_str('field_username', 'Username')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="fullname" id="f_fullname" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_fullname">
                                        <?php echo s(report_idgprogress_str('field_fullname', 'Full Name')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="gender" id="f_gender" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_gender">
                                        <?php echo s(report_idgprogress_str('field_gender', 'Gender')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="email" id="f_email" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_email">
                                        <?php echo s(report_idgprogress_str('field_email', 'Email')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="institution" id="f_institution" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_institution">
                                        <?php echo s(report_idgprogress_str('field_institution', 'Institution / Ministry')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="department" id="f_department" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_department">
                                        <?php echo s(report_idgprogress_str('field_department', 'Department / Unit')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <?php
                            foreach ($sitecustomfields as $cf):
                                $ls = strtolower($cf->shortname);
                                if ($ls === 'gender' || $ls === 'sex' || strpos($ls, 'gender') !== false || strpos($ls, 'sex') !== false) {
                                    continue;
                                }
                                $cfname = format_string($cf->name);
                            ?>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="custom_<?php echo s($cf->shortname); ?>" id="f_cf_<?php echo s($cf->shortname); ?>" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_cf_<?php echo s($cf->shortname); ?>">
                                        <?php echo s($cfname); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="activities_count" id="f_activities_count" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_activities_count">
                                        <?php echo s(report_idgprogress_str('field_activities_count', 'Completed Activities Count')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="progress" id="f_progress" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_progress">
                                        <?php echo s(report_idgprogress_str('field_progress', 'Progress Percentage (%)')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="coursestatus" id="f_coursestatus" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_coursestatus">
                                        <?php echo s(report_idgprogress_str('field_coursestatus', 'Course Completion Status')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="completeddate" id="f_completeddate" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_completeddate">
                                        <?php echo s(report_idgprogress_str('field_completeddate', 'Course Completed Date')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="activities_detail" id="f_activities_detail" onchange="report_idgprogress_on_field_toggle(this)" checked>
                                    <label class="form-check-label" for="f_activities_detail">
                                        <?php echo s(report_idgprogress_str('field_activities_detail', 'Individual Tracked Activities')); ?>
                                        <span class="badge bg-primary text-white rounded-pill ms-1 idg-order-badge" style="display: none; font-size: 0.72rem; padding: 0.2em 0.5em;"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal" onclick="report_idgprogress_close_export_modal()">
                        <?php echo s(report_idgprogress_str('cancel', 'Cancel')); ?>
                    </button>
                    <button type="submit" class="btn btn-success fw-bold px-4" onclick="report_idgprogress_on_export_submit()">
                        <i class="fa fa-download me-1"></i> <?php echo s(report_idgprogress_str('download', 'Download')); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var reportIdgSelectedFieldsOrder = [];

function report_idgprogress_init_field_order() {
    reportIdgSelectedFieldsOrder = [];
    document.querySelectorAll('.export-field-cb').forEach(function(cb) {
        if (cb.checked) {
            reportIdgSelectedFieldsOrder.push(cb.value);
        }
    });
    report_idgprogress_update_order_badges();
}

function report_idgprogress_on_field_toggle(cb) {
    var val = cb.value;
    var idx = reportIdgSelectedFieldsOrder.indexOf(val);
    if (cb.checked) {
        if (idx === -1) {
            reportIdgSelectedFieldsOrder.push(val);
        }
    } else {
        if (idx !== -1) {
            reportIdgSelectedFieldsOrder.splice(idx, 1);
        }
    }
    report_idgprogress_update_order_badges();
}

function report_idgprogress_update_order_badges() {
    var orderMap = {};
    reportIdgSelectedFieldsOrder.forEach(function(val, i) {
        orderMap[val] = i + 1;
    });

    document.querySelectorAll('.export-field-cb').forEach(function(cb) {
        var parent = cb.closest('.form-check');
        if (!parent) {
            return;
        }
        var badge = parent.querySelector('.idg-order-badge');
        if (!badge) {
            return;
        }

        if (cb.checked && orderMap[cb.value] !== undefined) {
            badge.textContent = orderMap[cb.value];
            badge.style.display = 'inline-block';
        } else {
            badge.textContent = '';
            badge.style.display = 'none';
        }
    });
}

function report_idgprogress_open_export_modal() {
    var modalEl = document.getElementById('idgExportModal');
    if (!modalEl) {
        return;
    }
    report_idgprogress_init_field_order();
    if (window.bootstrap && window.bootstrap.Modal) {
        var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    } else if (typeof jQuery !== 'undefined' && typeof jQuery(modalEl).modal === 'function') {
        jQuery(modalEl).modal('show');
    } else {
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        modalEl.removeAttribute('aria-hidden');
        modalEl.setAttribute('aria-modal', 'true');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'idg-modal-backdrop';
        document.body.appendChild(backdrop);
    }
}

function report_idgprogress_close_export_modal() {
    var modalEl = document.getElementById('idgExportModal');
    if (!modalEl) {
        return;
    }
    if (window.bootstrap && window.bootstrap.Modal) {
        var modal = window.bootstrap.Modal.getInstance(modalEl);
        if (modal) {
            modal.hide();
        }
    } else if (typeof jQuery !== 'undefined' && typeof jQuery(modalEl).modal === 'function') {
        jQuery(modalEl).modal('hide');
    }
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
    var backdrops = document.querySelectorAll('.modal-backdrop, #idg-modal-backdrop');
    backdrops.forEach(function(el) {
        el.remove();
    });
}

function report_idgprogress_on_export_submit() {
    var form = document.getElementById('idgExportForm');
    if (form) {
        var cbs = form.querySelectorAll('.export-field-cb');
        cbs.forEach(function(cb) {
            cb.removeAttribute('name');
        });

        form.querySelectorAll('.idg-ordered-field-input').forEach(function(el) {
            el.remove();
        });

        reportIdgSelectedFieldsOrder.forEach(function(val) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'fields[]';
            hidden.value = val;
            hidden.className = 'idg-ordered-field-input';
            form.appendChild(hidden);
        });

        setTimeout(function() {
            cbs.forEach(function(cb) {
                cb.setAttribute('name', 'fields[]');
            });
        }, 150);
    }

    setTimeout(function() {
        report_idgprogress_close_export_modal();
    }, 350);
    return true;
}

function report_idgprogress_toggle_fields(selectAll) {
    reportIdgSelectedFieldsOrder = [];
    document.querySelectorAll('.export-field-cb').forEach(function(cb) {
        cb.checked = selectAll;
        if (selectAll) {
            reportIdgSelectedFieldsOrder.push(cb.value);
        }
    });
    report_idgprogress_update_order_badges();
}

document.addEventListener('DOMContentLoaded', function() {
    report_idgprogress_init_field_order();
});
</script>
<?php
echo $OUTPUT->footer();
