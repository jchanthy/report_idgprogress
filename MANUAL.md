# IDG Progress Report (`report_idgprogress`)
## Complete User & Administrator Manual

**Version:** 1.0.14  
**Author:** Cambodia Academy of Digital Technology (CADT)  
**Supported Platforms:** Moodle 4.1 LTS, 4.2, 4.3, 4.4, 4.5+  
**Languages:** English & Khmer (ភាសាខ្មែរ Unicode)  

---

## Table of Contents
1. [Overview & Purpose](#1-overview--purpose)
2. [System Requirements & Prerequisites](#2-system-requirements--prerequisites)
3. [Installation & Upgrade Guide](#3-installation--upgrade-guide)
4. [Course Configuration Checklist](#4-course-configuration-checklist)
5. [Teacher & Manager Dashboard Guide](#5-teacher--manager-dashboard-guide)
   - [Accessing the Report](#51-accessing-the-report)
   - [Summary Metrics Cards](#52-summary-metrics-cards)
   - [Toolbar Controls & Filtering](#53-toolbar-controls--filtering)
   - [Participant Table Layout](#54-participant-table-layout)
6. [Exporting Reports (Excel & CSV)](#6-exporting-reports-excel--csv)
   - [Export Formats](#61-export-formats)
   - [Dynamic Column Ordering](#62-dynamic-column-ordering)
   - [Khmer Unicode & Excel Compatibility](#63-khmer-unicode--excel-compatibility)
7. [External REST & Web Services API Guide](#7-external-rest--web-services-api-guide)
   - [API Architecture](#71-api-architecture)
   - [Creating a Web Service Token in Moodle](#72-creating-a-web-service-token-in-moodle)
   - [Direct REST Endpoint (`api.php`)](#73-direct-rest-endpoint-apiphp)
   - [Enterprise Moodle Web Services (`server.php`)](#74-enterprise-moodle-web-services-serverphp)
   - [Testing with Postman](#75-testing-with-postman)
   - [Code Examples (cURL & Python)](#76-code-examples-curl--python)
8. [Troubleshooting & FAQ](#8-troubleshooting--faq)

---

## 1. Overview & Purpose

The **IDG Progress Report** (`report_idgprogress`) is an enterprise-grade course reporting plugin built for Moodle. It was designed specifically for training Cambodian civil servants, university students, and government officials across digital government transformation programs.

### Key Highlights
- **Bilingual Interface:** Instant localized rendering in English and Khmer Unicode (`\u1780-\u17FF`).
- **Native Participants Display:** Mirrored layout of Moodle's native participant list with two-line names (Khmer on line 1, Latin on line 2), circular avatar initials, phone numbers, department, groups, and roles.
- **Real-Time Progress Slicing:** Filter students directly on the web page by progress status (In Progress, Completed, Not Started, Under 100%, Under 50%) before exporting.
- **Dynamic Export Ordering:** You choose which columns to export and in what exact sequence (first clicked = Column A).
- **Khmer UTF-8 BOM Guarantee:** Exports include the UTF-8 Byte Order Mark (`\xEF\xBB\xBF`) at byte 0, guaranteeing zero font corruption in Microsoft Excel.
- **External Integration Ready:** Built-in REST API and Moodle Web Services enable external Student Information Systems (SIS), HR portals, and Telegram notification bots to query progress automatically.

---

## 2. System Requirements & Prerequisites

- **Moodle Version:** 4.1 LTS (Build 2022112800) or newer (including Moodle 4.2, 4.3, 4.4, 4.5+).
- **PHP Version:** PHP 7.4, 8.0, 8.1, or 8.2+.
- **Database:** MariaDB 10.4+, MySQL 8.0+, or PostgreSQL 13+.
- **Course Prerequisites:** Course completion tracking must be enabled in course settings.

---

## 3. Installation & Upgrade Guide

### Method A: Web UI Installation (Recommended)
1. Download the distribution ZIP: `report_idgprogress.zip`.
2. Log into your Moodle site as an **Administrator**.
3. Navigate to: **Site administration > Plugins > Install plugins**.
4. Drag and drop `report_idgprogress.zip` into the file picker.
5. Click **Install plugin from the ZIP file**.
6. Verify validation passes with **Status: OK**, then click **Upgrade Moodle database now**.

### Method B: Manual File Copy
1. Extract `report_idgprogress.zip` so the folder `idgprogress` is created.
2. Place the `idgprogress` folder into your Moodle directory under `/report/`:
   ```bash
   /path/to/moodle/report/idgprogress/
   ```
3. Run the CLI upgrade or visit **Site administration > Notifications**:
   ```bash
   php /path/to/moodle/admin/cli/upgrade.php
   ```

---

## 4. Course Configuration Checklist

For the report to calculate progress, your course must have completion tracking turned on:

1. Go to your course and click **Settings** (Edit course settings).
2. Scroll down to **Completion tracking**.
3. Set **Enable completion tracking** to **Yes**.
4. In course activities (Quizzes, Assignments, SCORM, Lessons):
   - In **Activity completion**, select **"Show activity as complete when conditions are met"** (e.g., student must view, receive a grade, or submit).
5. *(Optional)* Go to **Course Navigation > More > Course completion** and specify completion conditions if course-level completion badges are needed.

---

## 5. Teacher & Manager Dashboard Guide

### 5.1 Accessing the Report
1. Open your course.
2. In the course secondary navigation bar, click **Reports** (or **More > Reports**).
3. Select **IDG Progress Report**.

### 5.2 Summary Metrics Cards
At the top of the dashboard, 5 cards provide a high-level overview of the entire cohort:
- **Total Enrolled:** Total number of eligible participants enrolled in the course or selected group.
- **Course Completed:** Students who have finished 100% of tracked criteria.
- **In Progress:** Students actively working through activities (1% – 99%).
- **Not Started:** Students with 0 completed activities (0%).
- **Average Progress:** Mean completion percentage across all enrolled students.

### 5.3 Toolbar Controls & Filtering
The toolbar contains two cleanly separated rows:
- **Row 1 (Filters & Action):**
  - **Separate Groups Dropdown:** Switch between course cohorts/classes (if group mode is enabled).
  - **Filter Students by Progress:** Instant in-memory filter:
    - *All Students (All Progress)*
    - *In Progress Only (1% - 99%)*
    - *Completed Only (100%)*
    - *Not Started Only (0%)*
    - *Under 100% Progress (< 100%)*
    - *Under 50% Progress (< 50%)*
  - **Export Report Button:** Opens the export configuration modal.
- **Row 2 (Participant Search):**
  - Search box to filter students in real-time by First Name, Last Name, Username, or Email Address.
  - **Search** and **Clear** buttons.

### 5.4 Participant Table Layout
The table matches Moodle's native Participants view:
1. **First name / Last name:**
   - Circular user avatar with initials fallback (`RA`, `AA`, `បB`, `ជC`).
   - Line 1: First name (Khmer name) as a clickable profile link.
   - Line 2: Last name (Latin name) as a clickable profile link.
2. **Email address:** Participant's email address.
3. **Phone:** Participant's mobile number (`phone1` or `phone2`).
4. **Department:** Government ministry or departmental unit (`department`).
5. **Roles:** User role in the course (e.g. *Student*).
6. **Groups:** Assigned course group (e.g. `EXAM-FSA-260617` or `No groups`).
7. **Completed Activities:** Ratio of completed activities (e.g. `4 / 12`).
8. **Progress:** Colored visual progress bar with exact percentage (e.g. `75%`).
9. **Last access to course:** Time since last visit (e.g. `42 days 9 hours` or `Never`).
10. **Course Status:** Rounded pill badge:
    - Green: **Completed** (បានបញ្ចប់)
    - Blue: **In Progress** (កំពុងដំណើរការ)
    - Grey: **Not Started** (មិនទាន់ចាប់ផ្ដើម)
11. **Completed Date:**
    - If 100% completed: Course completion date (`✓ YYYY-MM-DD HH:MM`).
    - If in progress: Most recently completed activity date (`🕒 Activity: YYYY-MM-DD HH:MM`).
    - If not started: `-`.

---

## 6. Exporting Reports (Excel & CSV)

Click the green **Export Report** button on the dashboard to open the export modal.

### 6.1 Export Formats
- **Microsoft Excel (.xlsx):** Generates native `.xlsx` spreadsheets with bold header styling, automatic column auto-fit, and gridlines.
- **CSV (.csv):** Standard comma-separated values file with **UTF-8 BOM**, ideal for importing into external databases or government archives.

### 6.2 Dynamic Column Ordering
The plugin uses an interactive **numbered badge sequence**:
- Tick the checkboxes in the exact order you want them to appear in your spreadsheet:
  - 1st ticked &rarr; Badge **1** &rarr; Becomes Column A.
  - 2nd ticked &rarr; Badge **2** &rarr; Becomes Column B.
  - 3rd ticked &rarr; Badge **3** &rarr; Becomes Column C.
- Clicking **Select All** selects all fields in default sequence; **Deselect All** resets selections.

### 6.3 Khmer Unicode & Excel Compatibility
In Cambodia, Excel often opens CSV files with broken characters (mojibake) if the Byte Order Mark is missing. This plugin automatically prefixes all exported CSV files with the UTF-8 BOM (`\xEF\xBB\xBF`), allowing Excel on Windows to render Khmer Unicode (`ភាសាខ្មែរ`) cleanly without requiring any manual import wizards.

---

## 7. External REST & Web Services API Guide

External systems (such as Student Information Systems, HR portals, mobile applications, and Telegram bots) can query course completion data programmatically.

### 7.1 API Architecture
The plugin offers two access methods:
1. **Direct REST API (`/report/idgprogress/api.php`)**: Fast, lightweight JSON endpoint supporting HTTP Bearer tokens and CORS.
2. **Official Moodle Web Services (`/webservice/rest/server.php`)**: Enterprise gateway with strict function declarations in `db/services.php`.

### 7.2 Creating a Web Service Token in Moodle
1. Go to **Site administration > Server > Web services > Overview**:
   - Set **Enable web services** to **Yes**.
   - Under **Enable protocols**, ensure **REST protocol** is active.
2. Go to **Site administration > Server > Web services > External services**:
   - Ensure **IDG Progress API** (`idg_progress_service`) is enabled.
3. Go to **Site administration > Server > Web services > Manage tokens**:
   - Click **Add token**.
   - **User:** Select an Admin or Teacher account with access to the course.
   - **Service:** Select **IDG Progress API**.
   - Click **Save changes**.
   - Copy the generated token string (e.g. `9f8e7d6c5b4a3...`).

---

### 7.3 Direct REST Endpoint (`api.php`)

**Base URL:** `https://your-moodle.edu.kh/report/idgprogress/api.php`  
**Authentication:** `Authorization: Bearer <TOKEN>` header or `?wstoken=<TOKEN>` query parameter.

#### Query Course Progress
```http
GET /report/idgprogress/api.php?courseid=1284 HTTP/1.1
Host: your-moodle.edu.kh
Authorization: Bearer YOUR_WSTOKEN
```

**Supported Query Parameters:**
| Parameter | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `courseid` | int | *required* | Course ID in Moodle |
| `groupid` | int | `0` | Filter by specific group ID (`0` = all) |
| `progress_filter` | string | `all` | `all`, `inprogress`, `completed`, `notstarted`, `under100`, `under50` |
| `search` | string | `""` | Search keyword for student name, username, or email |
| `page` | int | `0` | Pagination page index (0-based) |
| `perpage` | int | `0` | Items per page (`0` returns all records) |
| `include_activities` | bool | `0` | Pass `1` to receive the full per-activity completion breakdown |

#### Query Single Student Detailed Progress
```http
GET /report/idgprogress/api.php?courseid=1284&action=user_progress&userid=567 HTTP/1.1
Host: your-moodle.edu.kh
Authorization: Bearer YOUR_WSTOKEN
```

---

### 7.4 Enterprise Moodle Web Services (`server.php`)

External systems using Moodle's core Web Service dispatcher can call:
- **`report_idgprogress_get_course_progress`**
- **`report_idgprogress_get_user_progress`**

```http
POST /webservice/rest/server.php HTTP/1.1
Host: your-moodle.edu.kh
Content-Type: application/x-www-form-urlencoded

wstoken=YOUR_WSTOKEN&wsfunction=report_idgprogress_get_course_progress&moodlewsrestformat=json&courseid=1284
```

---

### 7.5 Testing with Postman

1. **Create Request:** Set Method to `GET`.
2. **Enter URL:** `https://your-moodle.edu.kh/report/idgprogress/api.php?courseid=1284`.
3. **Set Auth:** Go to the **Authorization** tab &rarr; Type: **Bearer Token** &rarr; Paste your token.
4. **Send:** Click **Send**. You will receive HTTP `200 OK` with JSON data.

---

### 7.6 Code Examples (cURL & Python)

#### cURL
```bash
curl -X GET "https://your-moodle.edu.kh/report/idgprogress/api.php?courseid=1284&progress_filter=under50" \
     -H "Authorization: Bearer YOUR_WSTOKEN"
```

#### Python
```python
import requests

MOODLE_URL = "https://your-moodle.edu.kh"
TOKEN = "YOUR_WSTOKEN"
COURSE_ID = 1284

headers = {
    "Authorization": f"Bearer {TOKEN}"
}
params = {
    "courseid": COURSE_ID,
    "progress_filter": "inprogress",
    "include_activities": 1
}

response = requests.get(f"{MOODLE_URL}/report/idgprogress/api.php", headers=headers, params=params)

if response.status_code == 200:
    data = response.json()
    print(f"Course: {data['data']['course']['fullname']}")
    print(f"Enrolled: {data['data']['summary']['total_enrolled']}")
    for student in data['data']['participants']:
        print(f" - {student['fullname']}: {student['percentage']}% ({student['status']})")
else:
    print(f"Error {response.status_code}: {response.text}")
```

---

## 8. Troubleshooting & FAQ

### Q1: The report says "Completion tracking is not enabled for this course."
- **Solution:** Go to **Course Settings > Completion tracking** and set **Enable completion tracking** to **Yes**.

### Q2: Some students show 0% even though they viewed course materials.
- **Solution:** Verify that the activities in question have completion conditions set to *"Show activity as complete when conditions are met"*. Activities with completion set to *"Do not indicate activity completion"* are not tracked.

### Q3: API returns `401 Unauthorized`.
- **Solution:** Make sure your token is passed via `Authorization: Bearer <token>` or `?wstoken=<token>` and has not expired in Moodle under **Site administration > Server > Web services > Manage tokens**.

### Q4: API returns `403 Forbidden`.
- **Solution:** The user assigned to the token must have the capability `report/idgprogress:view` in that course. Assign the token to an Administrator, Manager, or an enrolled Teacher.

### Q5: Will the API cause server lag if a course has 500+ participants?
- **Solution:** No. The plugin uses single-batch SQL queries for cohort completions, groups, roles, and access logs, executing in ~15–30 milliseconds with zero N+1 overhead.
