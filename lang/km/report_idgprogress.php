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
 * Khmer language strings for the IDG Progress Report plugin.
 *
 * @package    report_idgprogress
 * @copyright  2024 Cambodia Academy of Digital Technology (CADT)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'របាយការណ៍វឌ្ឍនភាព IDG';
$string['idgprogress:view'] = 'មើលរបាយការណ៍វឌ្ឍនភាព IDG';
$string['pageheading'] = 'របាយការណ៍វឌ្ឍនភាព IDG';
$string['coursereport'] = 'ការតាមដានវឌ្ឍនភាពវគ្គសិក្សា';
$string['allparticipants'] = 'សិក្ខាកាមទាំងអស់';
$string['filtergroup'] = 'ត្រងតាមក្រុម';

// Summary metrics.
$string['totalenrolled'] = 'សិក្ខាកាមសរុប';
$string['completedcourse'] = 'បានបញ្ចប់វគ្គសិក្សា';
$string['inprogress'] = 'កំពុងដំណើរការ';
$string['notstarted'] = 'មិនទាន់ចាប់ផ្ដើម';
$string['avgprogress'] = 'វឌ្ឍនភាពមធ្យម';

// Search & filters.
$string['searchparticipant'] = 'ស្វែងរកសិក្ខាកាម...';
$string['search'] = 'ស្វែងរក';
$string['clear'] = 'សម្អាត';
$string['exportcsv'] = 'ទាញយកជា CSV (គាំទ្រ Excel និងភាសាខ្មែរ)';
$string['exportexcel'] = 'ទាញយករបាយការណ៍';

// Table columns.
$string['table_fullname'] = 'ឈ្មោះសិក្ខាកាម';
$string['table_gender'] = 'ភេទ';
$string['table_email'] = 'អ៊ីមែល';
$string['table_institution'] = 'ស្ថាប័ន / ក្រសួង';
$string['table_department'] = 'នាយកដ្ឋាន / អង្គភាព';
$string['table_activities'] = 'សកម្មភាពបានបញ្ចប់';
$string['table_progress'] = 'វឌ្ឍនភាព';
$string['table_status'] = 'ស្ថានភាពវគ្គសិក្សា';
$string['table_completeddate'] = 'កាលបរិច្ឆេទបញ្ចប់';
$string['table_actions'] = 'សកម្មភាព';
$string['viewprofile'] = 'មើលព័ត៌មានរូបសង្ខេប';

// Statuses & labels.
$string['completionnotenabled'] = 'មុខងារតាមដានការបញ្ចប់មិនទាន់បានបើកនៅក្នុងវគ្គសិក្សានេះទេ។ សូមបើកមុខងារតាមដានការបញ្ចប់ក្នុងការកំណត់វគ្គសិក្សាដើម្បីមើលរបាយការណ៍វឌ្ឍនភាព។';
$string['nocriteriaset'] = 'មិនមានលក្ខខណ្ឌវិនិច្ឆ័យ ឬសកម្មភាពតាមដានការបញ្ចប់ត្រូវបានកំណត់សម្រាប់វគ្គសិក្សានេះទេ។';
$string['noparticipantsfound'] = 'រកមិនឃើញសិក្ខាកាមដែលត្រូវនឹងលក្ខខណ្ឌស្វែងរកទេ។';
$string['completed'] = 'បានបញ្ចប់';
$string['notcompleted'] = 'មិនទាន់បញ្ចប់';
$string['status_completed'] = 'បានបញ្ចប់';
$string['status_inprogress'] = 'កំពុងដំណើរការ';
$string['status_notstarted'] = 'មិនទាន់ចាប់ផ្ដើម';
$string['na'] = 'គ្មាន';
$string['overallcompletion'] = 'ការបញ្ចប់វគ្គសិក្សារួម';
$string['activitycompletion'] = 'ការបញ្ចប់សកម្មភាព';
$string['exportfilename'] = 'IDG_Progress_Report';

// Export CSV headers.
$string['exportheader_userid'] = 'អត្តសញ្ញាណអ្នកប្រើប្រាស់';
$string['exportheader_username'] = 'ឈ្មោះគណនី';
$string['exportheader_fullname'] = 'ឈ្មោះពេញ';
$string['exportheader_gender'] = 'ភេទ';
$string['exportheader_email'] = 'អ៊ីមែល';
$string['exportheader_institution'] = 'ស្ថាប័ន';
$string['exportheader_department'] = 'នាយកដ្ឋាន';
$string['exportheader_completedactivities'] = 'សកម្មភាពបានបញ្ចប់';
$string['exportheader_totalactivities'] = 'សកម្មភាពសរុប';
$string['exportheader_progress'] = 'វឌ្ឍនភាព (%)';
$string['exportheader_coursestatus'] = 'ស្ថានភាពវគ្គសិក្សា';
$string['exportheader_completeddate'] = 'កាលបរិច្ឆេទបញ្ចប់វគ្គសិក្សា';
$string['customfield_header_prefix'] = '{$a}';
$string['activity_header_prefix'] = 'សកម្មភាព: {$a}';

// Export options and dialog.
$string['exportoptions'] = 'ទាញយករបាយការណ៍';
$string['exportformat'] = 'ទម្រង់ឯកសារ';
$string['formatexcel'] = 'Microsoft Excel (.xlsx)';
$string['formatcsv'] = 'CSV គាំទ្រភាសាខ្មែរ (.csv)';
$string['selectfields'] = 'ជ្រើសរើសទិន្នន័យសម្រាប់ទាញយក';
$string['selectall'] = 'ជ្រើសរើសទាំងអស់';
$string['deselectall'] = 'ដោះការជ្រើសរើស';
$string['field_userid'] = 'អត្តសញ្ញាណអ្នកប្រើប្រាស់';
$string['field_username'] = 'ឈ្មោះគណនី';
$string['field_fullname'] = 'ឈ្មោះពេញ';
$string['field_gender'] = 'ភេទ';
$string['field_email'] = 'អ៊ីមែល';
$string['field_institution'] = 'ស្ថាប័ន / ក្រសួង';
$string['field_department'] = 'នាយកដ្ឋាន / អង្គភាព';
$string['field_activities_count'] = 'ចំនួនសកម្មភាពបានបញ្ចប់';
$string['field_progress'] = 'ភាគរយវឌ្ឍនភាព (%)';
$string['field_coursestatus'] = 'ស្ថានភាពបញ្ចប់វគ្គសិក្សា';
$string['field_completeddate'] = 'កាលបរិច្ឆេទបញ្ចប់វគ្គសិក្សា';
$string['field_activities_detail'] = 'ព័ត៌មានលម្អិតនៃសកម្មភាពនីមួយៗ';
$string['download'] = 'ទាញយក';
$string['cancel'] = 'បោះបង់';

// Privacy metadata.
$string['privacy:metadata'] = 'កម្មវិធីជំនួយរបាយការណ៍វឌ្ឍនភាព IDG បង្ហាញតែទិន្នន័យការបញ្ចប់ និងការចុះឈ្មោះដែលមានស្រាប់ ហើយមិនរក្សាទុកទិន្នន័យផ្ទាល់ខ្លួនណាមួយឡើយ។';
