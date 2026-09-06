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

$id             = required_param('id', PARAM_INT);
$groupid        = optional_param('group', 0, PARAM_INT);
$search         = optional_param('search', '', PARAM_NOTAGS);
$page           = optional_param('page', 0, PARAM_INT);
$perpage        = optional_param('perpage', 25, PARAM_INT);
$progressfilter = optional_param('progress_filter', 'all', PARAM_ALPHA);
$progressmin    = optional_param('progress_min', 0, PARAM_INT);
$progressmax    = optional_param('progress_max', 100, PARAM_INT);

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
if ($progressfilter !== 'all') {
    $baseurl->param('progress_filter', $progressfilter);
}
if ($progressfilter === 'custom') {
    $baseurl->param('progress_min', $progressmin);
    $baseurl->param('progress_max', $progressmax);
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

<style>
/* IDG Progress Toolbar Normalization */
.idg-toolbar-card {
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 0.5rem;
}
.idg-toolbar-col {
    min-width: 0;
}
.idg-toolbar-card .groupselector,
.idg-toolbar-card .groupselector .singleselect,
.idg-toolbar-card .singleselect,
.idg-toolbar-card .groupselector form {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    display: block !important;
    float: none !important;
}
.idg-toolbar-card .groupselector label {
    display: block !important;
    margin: 0 0 0.35rem 0 !important;
    font-weight: 600 !important;
    font-size: 0.85rem !important;
    color: #495057 !important;
    line-height: 1.2 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
.idg-toolbar-card .groupselector select,
.idg-toolbar-card .groupselector .custom-select,
.idg-toolbar-card .groupselector .singleselect select,
.idg-toolbar-card .groupselector .form-control {
    width: 100% !important;
    max-width: 100% !important;
    height: 38px !important;
    min-height: 38px !important;
    display: block !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    border-radius: 0.375rem !important;
    border: 1px solid #ced4da !important;
    padding: 0.375rem 0.75rem !important;
    font-size: 0.9rem !important;
    line-height: 1.5 !important;
    background-color: #fff !important;
    vertical-align: middle !important;
}
.idg-toolbar-card .idg-search-form {
    margin: 0 !important;
    width: 100% !important;
}
.idg-toolbar-card .idg-search-form .input-group {
    width: 100% !important;
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: stretch !important;
}
.idg-toolbar-card .idg-search-form .form-control {
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.9rem !important;
    line-height: 1.5 !important;
    border-radius: 0.375rem 0 0 0.375rem !important;
    border: 1px solid #ced4da !important;
    min-width: 0 !important;
}
.idg-toolbar-card .idg-search-form .input-group-append {
    display: flex !important;
    margin-left: -1px !important;
}
.idg-toolbar-card .idg-search-form .input-group-append .btn {
    height: 38px !important;
    min-height: 38px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 0.9rem !important;
    line-height: 1.5 !important;
    padding: 0.375rem 0.85rem !important;
    border: 1px solid #6c757d !important;
}
.idg-toolbar-card .idg-search-form .input-group-append .btn:first-child {
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
}
.idg-toolbar-card .idg-search-form .input-group-append .btn:last-child {
    border-top-right-radius: 0.375rem !important;
    border-bottom-right-radius: 0.375rem !important;
}
.idg-toolbar-card .idg-btn-export {
    height: 38px !important;
    min-height: 38px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-weight: 600 !important;
    font-size: 0.9rem !important;
    padding: 0.375rem 1rem !important;
    border-radius: 0.375rem !important;
    vertical-align: middle !important;
}
/* Icon Spacing Normalization */
.idg-toolbar-card .btn i,
.idg-toolbar-card .btn .fa,
.idg-btn-export i,
.idg-btn-export .fa,
.idg-search-form .btn i,
.idg-search-form .btn .fa,
.modal .btn i,
.modal .btn .fa,
.modal .modal-header .fa,
.modal .form-check-label .fa {
    margin-right: 0.5rem !important;
}

/* Modal Styling */
.modal .form-select,
.modal select {
    height: 38px;
    border-radius: 0.375rem;
}

@media (max-width: 767.98px) {
    .idg-toolbar-card .idg-btn-export {
        width: 100% !important;
    }
}

/* Participants Table Styling (Moodle Native Table Match) */
.idg-participants-table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}
.idg-participants-table thead th {
    background-color: #f8f9fa !important;
    border-top: none !important;
    border-bottom: 2px solid #dee2e6 !important;
    padding: 0.8rem 0.85rem !important;
    vertical-align: top !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    color: #1d2125 !important;
}
.idg-participants-table thead th .table-sub-dash {
    color: #6c757d !important;
    font-weight: normal !important;
    margin-top: 2px !important;
}
.idg-participants-table tbody tr:nth-of-type(odd) {
    background-color: #fcfdfe !important;
}
.idg-participants-table tbody tr:nth-of-type(even) {
    background-color: #ffffff !important;
}
.idg-participants-table tbody tr:hover {
    background-color: #f0f4f8 !important;
}
.idg-participants-table tbody td {
    padding: 0.85rem 0.85rem !important;
    vertical-align: middle !important;
    border-bottom: 1px solid #eaedf1 !important;
    font-size: 0.875rem !important;
    color: #212529 !important;
}
.idg-participants-table .idg-user-names a {
    text-decoration: underline !important;
    line-height: 1.35;
}
.idg-participants-table .idg-user-names a:hover {
    color: #0b5ed7 !important;
}
</style>

<!-- Controls: Filter, Search, and Export -->
<div class="card shadow-sm mb-4 idg-toolbar-card">
    <div class="card-body">
        <!-- Row 1: Filters & Export Action -->
        <div class="row align-items-end g-3">
            <?php if ($groupmode): ?>
                <!-- 1. Group Selector -->
                <div class="col-12 col-md-5 col-lg-4 idg-toolbar-col">
                    <div class="idg-group-selector-wrapper">
                        <?php groups_print_course_menu($course, $baseurl); ?>
                    </div>
                </div>

                <!-- 2. Progress Filter -->
                <div class="col-12 col-md-4 col-lg-4 idg-toolbar-col">
                    <form method="get" action="<?php echo s(new moodle_url('/report/idgprogress/index.php')); ?>" class="m-0 p-0" id="idgProgressFilterForm">
                        <input type="hidden" name="id" value="<?php echo (int)$course->id; ?>" />
                        <?php if ($groupid): ?>
                            <input type="hidden" name="group" value="<?php echo (int)$groupid; ?>" />
                        <?php endif; ?>
                        <?php if ($search !== ''): ?>
                            <input type="hidden" name="search" value="<?php echo s($search); ?>" />
                        <?php endif; ?>
                        <label for="dashboardProgressFilter" class="form-label d-block fw-semibold small text-muted mb-1 text-truncate">
                            <?php echo s(report_idgprogress_str('filterbyprogress', 'Filter by Progress')); ?>
                        </label>
                        <select name="progress_filter" id="dashboardProgressFilter" class="form-select form-control" onchange="this.form.submit()">
                            <option value="all" <?php echo $progressfilter === 'all' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_all', 'All Students (All Progress)')); ?></option>
                            <option value="inprogress" <?php echo $progressfilter === 'inprogress' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_inprogress', 'In Progress Only (1% - 99%)')); ?></option>
                            <option value="completed" <?php echo $progressfilter === 'completed' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_completed', 'Completed Only (100%)')); ?></option>
                            <option value="notstarted" <?php echo $progressfilter === 'notstarted' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_notstarted', 'Not Started Only (0%)')); ?></option>
                            <option value="under100" <?php echo $progressfilter === 'under100' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_under100', 'Under 100% Progress (< 100%)')); ?></option>
                            <option value="under50" <?php echo $progressfilter === 'under50' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_under50', 'Under 50% Progress (< 50%)')); ?></option>
                        </select>
                    </form>
                </div>

                <!-- 3. Export Button -->
                <div class="col-12 col-md-3 col-lg-4 idg-toolbar-col text-md-end">
                    <button type="button" class="btn btn-success text-nowrap shadow-sm idg-btn-export"
                            id="btnIdgOpenExport"
                            data-bs-toggle="modal" data-bs-target="#idgExportModal"
                            data-toggle="modal" data-target="#idgExportModal"
                            onclick="report_idgprogress_open_export_modal()">
                        <i class="fa fa-download mr-2 me-2" aria-hidden="true"></i><span><?php echo s(report_idgprogress_str('exportoptions', 'Export Report')); ?></span>
                    </button>
                </div>
            <?php else: ?>
                <!-- Progress Filter (No Groups) -->
                <div class="col-12 col-md-8 col-lg-8 idg-toolbar-col">
                    <form method="get" action="<?php echo s(new moodle_url('/report/idgprogress/index.php')); ?>" class="m-0 p-0" id="idgProgressFilterForm">
                        <input type="hidden" name="id" value="<?php echo (int)$course->id; ?>" />
                        <?php if ($search !== ''): ?>
                            <input type="hidden" name="search" value="<?php echo s($search); ?>" />
                        <?php endif; ?>
                        <label for="dashboardProgressFilter" class="form-label d-block fw-semibold small text-muted mb-1 text-truncate">
                            <?php echo s(report_idgprogress_str('filterbyprogress', 'Filter by Progress')); ?>
                        </label>
                        <select name="progress_filter" id="dashboardProgressFilter" class="form-select form-control" onchange="this.form.submit()">
                            <option value="all" <?php echo $progressfilter === 'all' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_all', 'All Students (All Progress)')); ?></option>
                            <option value="inprogress" <?php echo $progressfilter === 'inprogress' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_inprogress', 'In Progress Only (1% - 99%)')); ?></option>
                            <option value="completed" <?php echo $progressfilter === 'completed' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_completed', 'Completed Only (100%)')); ?></option>
                            <option value="notstarted" <?php echo $progressfilter === 'notstarted' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_notstarted', 'Not Started Only (0%)')); ?></option>
                            <option value="under100" <?php echo $progressfilter === 'under100' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_under100', 'Under 100% Progress (< 100%)')); ?></option>
                            <option value="under50" <?php echo $progressfilter === 'under50' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_under50', 'Under 50% Progress (< 50%)')); ?></option>
                        </select>
                    </form>
                </div>

                <!-- Export Button (No Groups) -->
                <div class="col-12 col-md-4 col-lg-4 idg-toolbar-col text-md-end">
                    <button type="button" class="btn btn-success text-nowrap shadow-sm idg-btn-export"
                            id="btnIdgOpenExport"
                            data-bs-toggle="modal" data-bs-target="#idgExportModal"
                            data-toggle="modal" data-target="#idgExportModal"
                            onclick="report_idgprogress_open_export_modal()">
                        <i class="fa fa-download mr-2 me-2" aria-hidden="true"></i><span><?php echo s(report_idgprogress_str('exportoptions', 'Export Report')); ?></span>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Row 2: Participant Search (Placed Bellow) -->
        <div class="row pt-3 mt-3 border-top">
            <div class="col-12 col-md-8 col-lg-6">
                <form method="get" action="<?php echo s(new moodle_url('/report/idgprogress/index.php')); ?>" class="m-0 p-0 idg-search-form">
                    <input type="hidden" name="id" value="<?php echo (int)$course->id; ?>" />
                    <?php if ($groupid): ?>
                        <input type="hidden" name="group" value="<?php echo (int)$groupid; ?>" />
                    <?php endif; ?>
                    <?php if ($progressfilter !== 'all'): ?>
                        <input type="hidden" name="progress_filter" value="<?php echo s($progressfilter); ?>" />
                    <?php endif; ?>
                    <label for="dashboardSearchInput" class="form-label d-block fw-semibold small text-muted mb-1 text-truncate">
                        <?php echo s(get_string('searchparticipant', 'report_idgprogress')); ?>
                    </label>
                    <div class="input-group">
                        <input type="text" name="search" id="dashboardSearchInput" class="form-control"
                               placeholder="<?php echo s(get_string('searchparticipant', 'report_idgprogress')); ?>"
                               value="<?php echo s($search); ?>" />
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-secondary text-nowrap">
                                <i class="fa fa-search mr-2 me-2" aria-hidden="true"></i><span><?php echo s(get_string('search', 'report_idgprogress')); ?></span>
                            </button>
                            <?php if ($search !== '' || $progressfilter !== 'all'): ?>
                                <a href="<?php echo s(new moodle_url('/report/idgprogress/index.php', $groupid ? ['id' => $course->id, 'group' => $groupid] : ['id' => $course->id])); ?>"
                                   class="btn btn-outline-secondary text-nowrap" title="<?php echo s(get_string('clear', 'report_idgprogress')); ?>">
                                    <?php echo s(get_string('clear', 'report_idgprogress')); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Filter cohort participants by progress status if active.
$filteredcohortusers = [];
if ($groupid !== -1 && !empty($allcohortusers)) {
    if ($progressfilter !== 'all') {
        foreach ($allcohortusers as $uid => $user) {
            $studentdata = report_idgprogress_get_student_completion_data(
                $course,
                $completion,
                $trackedactivities,
                $user,
                $cohortcache
            );

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

            if ($isincluded) {
                $filteredcohortusers[$uid] = $user;
            }
        }
    } else {
        $filteredcohortusers = $allcohortusers;
    }
}

// Pagination and paged user data retrieval.
$totalcount = count($filteredcohortusers);
$pagedusers = [];
$pagedcustomfields = [];
if ($totalcount > 0) {
    $pagedusers = array_slice($filteredcohortusers, $page * $perpage, $perpage, true);
    if (!empty($pagedusers)) {
        $pagedcustomfields = report_idgprogress_get_users_custom_fields(array_keys($pagedusers));
    }
}

// Batch-fetch course-level groups, roles, and last access.
$courseusergroups = report_idgprogress_get_course_user_groups($course->id);
$courseuserroles = report_idgprogress_get_course_user_roles($context->id);
$courselastaccess = report_idgprogress_get_course_lastaccess($course->id);

if (empty($pagedusers)) {
    echo $OUTPUT->notification(get_string('noparticipantsfound', 'report_idgprogress'), 'info');
} else {
    // Render Paged Table.
    ?>
    <div class="table-responsive shadow-sm rounded bg-white mb-4">
        <table class="table table-striped table-hover align-middle mb-0 idg-participants-table">
            <thead>
                <tr>
                    <th scope="col" style="min-width: 220px;">
                        <div class="fw-bold text-dark"><?php echo s(get_string('firstname')); ?></div>
                        <div class="text-muted small">/ <?php echo s(get_string('lastname')); ?> <i class="fa fa-caret-up text-muted ms-1 me-1"></i></div>
                        <div class="text-muted small table-sub-dash">—</div>
                    </th>
                    <th scope="col">
                        <div class="fw-bold text-dark"><?php echo s(get_string('email')); ?></div>
                        <div class="text-muted small table-sub-dash">—</div>
                    </th>
                    <th scope="col">
                        <div class="fw-bold text-dark"><?php echo s(get_string('phone')); ?></div>
                        <div class="text-muted small table-sub-dash">—</div>
                    </th>
                    <th scope="col">
                        <div class="fw-bold text-dark"><?php echo s(get_string('department')); ?></div>
                        <div class="text-muted small table-sub-dash">—</div>
                    </th>
                    <th scope="col">
                        <div class="fw-bold text-dark"><?php echo s(get_string('roles')); ?></div>
                        <div class="text-muted small table-sub-dash">—</div>
                    </th>
                    <th scope="col">
                        <div class="fw-bold text-dark"><?php echo s(get_string('groups')); ?></div>
                        <div class="text-muted small table-sub-dash">—</div>
                    </th>
                    <th scope="col" class="text-center">
                        <div class="fw-bold text-dark"><?php echo s(get_string('table_activities', 'report_idgprogress')); ?></div>
                        <div class="text-muted small table-sub-dash">—</div>
                    </th>
                    <th scope="col" style="min-width: 130px;">
                        <div class="fw-bold text-dark"><?php echo s(get_string('table_progress', 'report_idgprogress')); ?></div>
                        <div class="text-muted small table-sub-dash">—</div>
                    </th>
                    <th scope="col">
                        <div class="fw-bold text-dark"><?php echo s(get_string('lastcourseaccess')); ?></div>
                        <div class="text-muted small table-sub-dash">—</div>
                    </th>
                    <th scope="col" class="text-center">
                        <div class="fw-bold text-dark"><?php echo s(get_string('status')); ?></div>
                        <div class="text-muted small table-sub-dash">—</div>
                    </th>
                    <th scope="col">
                        <div class="fw-bold text-dark"><?php echo s(get_string('table_completeddate', 'report_idgprogress')); ?></div>
                        <div class="text-muted small table-sub-dash">—</div>
                    </th>
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
                    $userpic = $OUTPUT->user_picture($user, ['size' => 38, 'link' => false]);
                    $usercustom = $pagedcustomfields[$user->id] ?? [];

                    // Phone & Department.
                    $phone = !empty($user->phone1) ? $user->phone1 : (!empty($user->phone2) ? $user->phone2 : '');
                    $department = !empty($user->department) ? $user->department : '';

                    // Groups.
                    $usergroups = $courseusergroups[$user->id] ?? [];
                    $groupsdisplay = !empty($usergroups) ? implode(', ', $usergroups) : get_string('nogroups', 'group');

                    // Roles.
                    $userroles = $courseuserroles[$user->id] ?? [];
                    $rolesdisplay = !empty($userroles) ? implode(', ', $userroles) : get_string('student', 'moodle', 'Student');

                    // Last course access.
                    $timeaccess = $courselastaccess[$user->id] ?? 0;
                    $lastaccessstr = $timeaccess > 0 ? format_time(time() - $timeaccess) : get_string('never');

                    // Progress metrics.
                    $activitiesratio = $studentdata->completedactivities . ' / ' . $studentdata->totalactivities;
                    $progressbar = report_idgprogress_render_progress_bar($studentdata->percentage, $studentdata->status);
                    $statusbadge = report_idgprogress_render_status_badge($studentdata->status);
                    if ($studentdata->status === 'completed' && $studentdata->timecompleted > 0) {
                        $completeddatehtml = '<span class="text-success fw-bold" title="' . s(get_string('coursecompleteddate', 'report_idgprogress')) . '">'
                            . '<i class="fa fa-check-circle text-success mr-1 me-1"></i>'
                            . s(userdate($studentdata->timecompleted, get_string('strftimedatetime', 'langconfig')))
                            . '</span>';
                    } elseif (!empty($studentdata->lastactivitytime) && $studentdata->lastactivitytime > 0) {
                        $completeddatehtml = '<span class="text-secondary" title="' . s(get_string('lastactivitydate', 'report_idgprogress')) . '">'
                            . '<i class="fa fa-clock-o text-muted mr-1 me-1"></i>'
                            . '<span class="text-muted small">' . s(get_string('lastactivityprefix', 'report_idgprogress')) . ': </span>'
                            . s(userdate($studentdata->lastactivitytime, get_string('strftimedatetime', 'langconfig')))
                            . '</span>';
                    } else {
                        $completeddatehtml = '<span class="text-muted">-</span>';
                    }
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="me-2 mr-2 flex-shrink-0">
                                    <?php echo $userpic; ?>
                                </div>
                                <div class="idg-user-names lh-sm">
                                    <a href="<?php echo s($userurl); ?>" class="d-block fw-bold text-primary text-decoration-underline" style="font-size: 0.95rem;">
                                        <?php echo s($user->firstname); ?>
                                    </a>
                                    <?php if (!empty($user->lastname)): ?>
                                        <a href="<?php echo s($userurl); ?>" class="d-block fw-bold text-dark text-decoration-underline mt-1" style="font-size: 0.95rem;">
                                            <?php echo s($user->lastname); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="text-dark"><?php echo s($user->email); ?></span>
                        </td>
                        <td>
                            <?php echo !empty($phone) ? s($phone) : '<span class="text-muted">-</span>'; ?>
                        </td>
                        <td>
                            <?php echo !empty($department) ? s($department) : '<span class="text-muted">-</span>'; ?>
                        </td>
                        <td>
                            <span class="text-dark"><?php echo s($rolesdisplay); ?></span>
                        </td>
                        <td>
                            <?php if (!empty($usergroups)): ?>
                                <span class="text-dark"><?php echo s($groupsdisplay); ?></span>
                            <?php else: ?>
                                <span class="text-muted"><?php echo s($groupsdisplay); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-semibold">
                            <?php echo s($activitiesratio); ?>
                        </td>
                        <td>
                            <?php echo $progressbar; ?>
                        </td>
                        <td>
                            <span class="text-dark"><?php echo s($lastaccessstr); ?></span>
                        </td>
                        <td class="text-center">
                            <?php echo $statusbadge; ?>
                        </td>
                        <td>
                            <span class="small"><?php echo $completeddatehtml; ?></span>
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
                                        <i class="fa fa-file-excel-o fa-lg mr-2 me-2"></i> <?php echo s(report_idgprogress_str('formatexcel', 'Microsoft Excel (.xlsx)')); ?>
                                    </label>
                                </div>
                            </div>
                            <div class="card p-3 border flex-fill">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format" id="exportFormatCsv" value="csv">
                                    <label class="form-check-label fw-bold text-primary" for="exportFormatCsv">
                                        <i class="fa fa-file-text-o fa-lg mr-2 me-2"></i> <?php echo s(report_idgprogress_str('formatcsv', 'CSV (.csv)')); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Filter Selection -->
                    <div class="mb-4">
                        <label for="exportProgressFilter" class="form-label fw-bold d-block text-uppercase small text-muted">
                            <?php echo s(report_idgprogress_str('filterbyprogress', 'Filter Students by Progress')); ?>
                        </label>
                        <select name="progress_filter" id="exportProgressFilter" class="form-select form-control" onchange="report_idgprogress_on_progress_filter_change(this.value)">
                            <option value="all" <?php echo $progressfilter === 'all' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_all', 'All Students (All Progress)')); ?></option>
                            <option value="inprogress" <?php echo $progressfilter === 'inprogress' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_inprogress', 'In Progress Only (1% - 99%)')); ?></option>
                            <option value="completed" <?php echo $progressfilter === 'completed' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_completed', 'Completed Only (100%)')); ?></option>
                            <option value="notstarted" <?php echo $progressfilter === 'notstarted' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_notstarted', 'Not Started Only (0%)')); ?></option>
                            <option value="under100" <?php echo $progressfilter === 'under100' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_under100', 'Under 100% Progress (< 100%)')); ?></option>
                            <option value="under50" <?php echo $progressfilter === 'under50' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_under50', 'Under 50% Progress (< 50%)')); ?></option>
                            <option value="custom" <?php echo $progressfilter === 'custom' ? 'selected' : ''; ?>><?php echo s(report_idgprogress_str('filter_custom', 'Custom Progress Range (%)')); ?></option>
                        </select>
                        <div id="idgCustomProgressRange" class="mt-2 row g-2" style="display: none;">
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1"><?php echo s(report_idgprogress_str('minprogress', 'Min Progress (%)')); ?></label>
                                <input type="number" name="progress_min" id="idgProgressMin" class="form-control form-control-sm" min="0" max="100" value="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1"><?php echo s(report_idgprogress_str('maxprogress', 'Max Progress (%)')); ?></label>
                                <input type="number" name="progress_max" id="idgProgressMax" class="form-control form-control-sm" min="0" max="100" value="100">
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
                        <span><?php echo s(report_idgprogress_str('cancel', 'Cancel')); ?></span>
                    </button>
                    <button type="submit" class="btn btn-success fw-bold px-4" onclick="report_idgprogress_on_export_submit()">
                        <i class="fa fa-download mr-2 me-2 text-white"></i><span><?php echo s(report_idgprogress_str('download', 'Download')); ?></span>
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

function report_idgprogress_on_progress_filter_change(val) {
    var rangeDiv = document.getElementById('idgCustomProgressRange');
    if (rangeDiv) {
        rangeDiv.style.display = (val === 'custom') ? 'flex' : 'none';
    }
}


document.addEventListener('DOMContentLoaded', function() {
    report_idgprogress_init_field_order();
});
</script>
<?php
echo $OUTPUT->footer();
