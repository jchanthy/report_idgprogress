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
                <button type="button" class="btn btn-success text-nowrap shadow-sm"
                        data-bs-toggle="modal" data-bs-target="#idgExportModal"
                        data-toggle="modal" data-target="#idgExportModal">
                    <i class="fa fa-download me-1" aria-hidden="true"></i> <?php echo s(get_string('exportoptions', 'report_idgprogress')); ?>
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

// Render Export Options & Field Selection Modal.
$sitecustomfields = report_idgprogress_get_custom_profile_fields();
?>
<!-- Modal for Export Options & Field Selection -->
<div class="modal fade" id="idgExportModal" tabindex="-1" aria-labelledby="idgExportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="get" action="<?php echo s(new moodle_url('/report/idgprogress/export.php')); ?>">
                <input type="hidden" name="id" value="<?php echo (int)$course->id; ?>" />
                <?php if ($groupid): ?>
                    <input type="hidden" name="group" value="<?php echo (int)$groupid; ?>" />
                <?php endif; ?>
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="search" value="<?php echo s($search); ?>" />
                <?php endif; ?>

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="idgExportModalLabel">
                        <i class="fa fa-download text-success me-2"></i> <?php echo s(get_string('exportoptions', 'report_idgprogress')); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <!-- Format Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold d-block text-uppercase small text-muted">
                            <?php echo s(get_string('exportformat', 'report_idgprogress')); ?>
                        </label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="card p-3 border flex-fill">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format" id="exportFormatExcel" value="excel" checked>
                                    <label class="form-check-label fw-bold text-success" for="exportFormatExcel">
                                        <i class="fa fa-file-excel-o fa-lg me-1"></i> <?php echo s(get_string('formatexcel', 'report_idgprogress')); ?>
                                    </label>
                                </div>
                            </div>
                            <div class="card p-3 border flex-fill">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format" id="exportFormatCsv" value="csv">
                                    <label class="form-check-label fw-bold text-primary" for="exportFormatCsv">
                                        <i class="fa fa-file-text-o fa-lg me-1"></i> <?php echo s(get_string('formatcsv', 'report_idgprogress')); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Field Selection -->
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <label class="form-label fw-bold mb-0 text-uppercase small text-muted">
                                <?php echo s(get_string('selectfields', 'report_idgprogress')); ?>
                            </label>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="report_idgprogress_toggle_fields(true)">
                                    <?php echo s(get_string('selectall', 'report_idgprogress')); ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="report_idgprogress_toggle_fields(false)">
                                    <?php echo s(get_string('deselectall', 'report_idgprogress')); ?>
                                </button>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="userid" id="f_userid">
                                    <label class="form-check-label" for="f_userid"><?php echo s(get_string('field_userid', 'report_idgprogress')); ?></label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="username" id="f_username" checked>
                                    <label class="form-check-label" for="f_username"><?php echo s(get_string('field_username', 'report_idgprogress')); ?></label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="fullname" id="f_fullname" checked>
                                    <label class="form-check-label fw-semibold" for="f_fullname"><?php echo s(get_string('field_fullname', 'report_idgprogress')); ?></label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="gender" id="f_gender" checked>
                                    <label class="form-check-label fw-semibold text-primary" for="f_gender">
                                        <i class="fa fa-venus-mars me-1"></i><?php echo s(get_string('field_gender', 'report_idgprogress')); ?>
                                    </label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="email" id="f_email" checked>
                                    <label class="form-check-label" for="f_email"><?php echo s(get_string('field_email', 'report_idgprogress')); ?></label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="institution" id="f_institution" checked>
                                    <label class="form-check-label" for="f_institution"><?php echo s(get_string('field_institution', 'report_idgprogress')); ?></label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="department" id="f_department" checked>
                                    <label class="form-check-label" for="f_department"><?php echo s(get_string('field_department', 'report_idgprogress')); ?></label>
                                </div>
                            </div>
                            <?php
                            foreach ($sitecustomfields as $cf):
                                $ls = strtolower($cf->shortname);
                                if ($ls === 'gender' || $ls === 'sex' || str_contains($ls, 'gender') || str_contains($ls, 'sex')) {
                                    continue;
                                }
                                $cfname = format_string($cf->name);
                            ?>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="custom_<?php echo s($cf->shortname); ?>" id="f_cf_<?php echo s($cf->shortname); ?>" checked>
                                    <label class="form-check-label" for="f_cf_<?php echo s($cf->shortname); ?>">
                                        <?php echo s(get_string('customfield_header_prefix', 'report_idgprogress', $cfname)); ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="activities_count" id="f_activities_count" checked>
                                    <label class="form-check-label" for="f_activities_count"><?php echo s(get_string('field_activities_count', 'report_idgprogress')); ?></label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="progress" id="f_progress" checked>
                                    <label class="form-check-label" for="f_progress"><?php echo s(get_string('field_progress', 'report_idgprogress')); ?></label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="coursestatus" id="f_coursestatus" checked>
                                    <label class="form-check-label" for="f_coursestatus"><?php echo s(get_string('field_coursestatus', 'report_idgprogress')); ?></label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="completeddate" id="f_completeddate" checked>
                                    <label class="form-check-label" for="f_completeddate"><?php echo s(get_string('field_completeddate', 'report_idgprogress')); ?></label>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input export-field-cb" type="checkbox" name="fields[]" value="activities_detail" id="f_activities_detail" checked>
                                    <label class="form-check-label" for="f_activities_detail"><?php echo s(get_string('field_activities_detail', 'report_idgprogress')); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">
                        <?php echo s(get_string('cancel', 'report_idgprogress')); ?>
                    </button>
                    <button type="submit" class="btn btn-success fw-bold px-4">
                        <i class="fa fa-download me-1"></i> <?php echo s(get_string('download', 'report_idgprogress')); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function report_idgprogress_toggle_fields(selectAll) {
    document.querySelectorAll('.export-field-cb').forEach(function(cb) {
        cb.checked = selectAll;
    });
}
</script>
<?php
echo $OUTPUT->footer();
