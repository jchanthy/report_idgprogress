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
 * Dedicated REST API endpoint for external systems to query IDG Progress data.
 *
 * Usage:
 *   GET /report/idgprogress/api.php?courseid=123&wstoken=YOUR_TOKEN
 *   Header: Authorization: Bearer YOUR_TOKEN
 *
 * @package    report_idgprogress
 * @copyright  2024 Cambodia Academy of Digital Technology (CADT)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Send CORS and JSON headers.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle CORS preflight.
if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Output JSON error and terminate request.
 *
 * @param string $message Error description.
 * @param int $httpcode HTTP status code.
 */
function report_idgprogress_api_error(string $message, int $httpcode = 400) {
    http_response_code($httpcode);
    echo json_encode([
        'success' => false,
        'error'   => $message,
        'code'    => $httpcode,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 1. Extract authentication token.
$token = '';
$authheader = '';
if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $authheader = $_SERVER['HTTP_AUTHORIZATION'];
} else if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authheader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} else if (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (!empty($headers['Authorization'])) {
        $authheader = $headers['Authorization'];
    } else if (!empty($headers['authorization'])) {
        $authheader = $headers['authorization'];
    }
}

if (!empty($authheader)) {
    if (preg_match('/Bearer\s+(\S+)/i', $authheader, $matches)) {
        $token = $matches[1];
    } else {
        $token = trim($authheader);
    }
}

if ($token === '') {
    $token = optional_param('wstoken', '', PARAM_ALPHANUM);
}
if ($token === '') {
    $token = optional_param('token', '', PARAM_ALPHANUM);
}

if ($token === '') {
    report_idgprogress_api_error('Missing authentication token. Pass via Authorization: Bearer <wstoken> header or wstoken query parameter.', 401);
}

// 2. Validate token against Moodle external_tokens table.
$tokendata = $DB->get_record_sql(
    "SELECT t.id, t.token, t.userid, t.validuntil, u.id AS uid, u.deleted, u.suspended
       FROM {external_tokens} t
       JOIN {user} u ON u.id = t.userid
      WHERE t.token = :token
        AND (t.validuntil = 0 OR t.validuntil > :now)
        AND u.deleted = 0
        AND u.suspended = 0",
    ['token' => $token, 'now' => time()]
);

if (!$tokendata) {
    report_idgprogress_api_error('Invalid or expired authentication token.', 401);
}

// Set active user context for permission checks.
$apiuser = \core_user::get_user($tokendata->userid, '*', MUST_EXIST);
\core\session\manager::set_user($apiuser);

// 3. Parse input parameters.
$action            = optional_param('action', 'course_progress', PARAM_ALPHAEXT);
$courseid          = optional_param('courseid', 0, PARAM_INT);
$groupid           = optional_param('groupid', 0, PARAM_INT);
$progressfilter    = optional_param('progress_filter', 'all', PARAM_ALPHA);
$search            = optional_param('search', '', PARAM_NOTAGS);
$page              = optional_param('page', 0, PARAM_INT);
$perpage           = optional_param('perpage', 0, PARAM_INT);
$includeactivities = optional_param('include_activities', 0, PARAM_BOOL);
$userid            = optional_param('userid', 0, PARAM_INT);

if (!$courseid) {
    report_idgprogress_api_error('Missing required parameter: courseid.', 400);
}

$course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
if (!$course) {
    report_idgprogress_api_error("Course with ID {$courseid} not found.", 404);
}

$context = context_course::instance($course->id, IGNORE_MISSING);
if (!$context) {
    report_idgprogress_api_error('Course context could not be loaded.', 500);
}

if (!has_capability('report/idgprogress:view', $context, $apiuser)) {
    report_idgprogress_api_error('User does not have capability report/idgprogress:view for this course.', 403);
}

// 4. Execute requested action.
try {
    if ($action === 'user_progress') {
        if (!$userid) {
            report_idgprogress_api_error('Missing required parameter: userid for action=user_progress.', 400);
        }
        $payload = report_idgprogress_get_api_user_progress_data($course, $context, $userid);
    } else {
        $payload = report_idgprogress_get_api_progress_data(
            $course,
            $context,
            $groupid,
            $progressfilter,
            $search,
            $page,
            $perpage,
            $includeactivities
        );
    }

    http_response_code(200);
    echo json_encode([
        'success'   => true,
        'timestamp' => time(),
        'data'      => $payload,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    report_idgprogress_api_error('Server error: ' . $e->getMessage(), 500);
}
