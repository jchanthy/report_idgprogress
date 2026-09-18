# IDG Progress Report (`report_idgprogress`)

A production-ready Moodle Course Report plugin developed for the Cambodia Academy of Digital Technology (CADT). Designed specifically for tracking participant and civil servant learning progress across courses with native bilingual support (English and Khmer Unicode `\u1780-\u17FF`).

---

## Key Features

- **Moodle 4.1 – 4.5+ Compatible:** Built according to Moodle 4.x/5.x architectural guidelines, strict PSR-12 coding style, and modern responsive UI.
- **Khmer Unicode Preservation:** Full support for Khmer script in names, departments, and ministries. All CSV exports stream the UTF-8 Byte Order Mark (`\xEF\xBB\xBF`) at byte 0, ensuring Microsoft Excel opens Cambodian civil servant details without font corruption or mojibake.
- **Custom User Profile Fields & Gender:** Automatically extracts and displays Gender (ភេទ) in the dashboard and exports all site-defined custom profile fields (e.g., position, civil servant ID, phone) dynamically.
- **Completion Tracking API Integration:** Uses `$CFG->libdir . '/completionlib.php'` and `completion_info` to evaluate both course-level completion and activity-level criteria.
- **Group & Search Filtering:** Supports Moodle group modes (Separate Groups, Visible Groups) and real-time parameterized search by name, username, or email.
- **Cohort Analytics:** Summary dashboard cards displaying total enrollment, completed courses, in-progress learners, and average completion percentage.
- **External REST & Web Services API:** Provides dual-mode API endpoints (`db/services.php` + `externallib.php` for official Moodle Web Services and `api.php` for direct REST calls) so external systems (SIS, HR, mobile apps, dashboards) can query real-time course progress metrics and student activity completion.

---

## Directory Structure

```text
report/idgprogress/
├── version.php                         # Plugin version, component declaration, and minimum requirements
├── db/
│   ├── access.php                      # Capability definition (report/idgprogress:view)
│   └── services.php                    # External Web Services definitions (functions & service)
├── lang/
│   ├── en/
│   │   └── report_idgprogress.php      # English language pack
│   └── km/
│       └── report_idgprogress.php      # Khmer (ភាសាខ្មែរ) language pack
├── lib.php                             # Navigation hooks, completion computation, and UI helpers
├── externallib.php                     # Moodle Web Services API class (report_idgprogress_external)
├── api.php                             # Dedicated standalone REST API endpoint for external systems
├── index.php                           # Interactive dashboard and paged participant table
├── export.php                          # UTF-8 BOM CSV streaming export script
└── styles.css                          # Native participants-style CSS rules
```

---

## External API Usage

External systems can access progress data using either **Direct REST** or **Moodle Web Services**:

### 1. Direct REST Endpoint (`api.php`)

```bash
# Query course progress via Authorization Header:
curl -H "Authorization: Bearer YOUR_WSTOKEN" \
     "https://your-moodle.edu.kh/report/idgprogress/api.php?courseid=1284"

# Query course progress with filters:
curl -H "Authorization: Bearer YOUR_WSTOKEN" \
     "https://your-moodle.edu.kh/report/idgprogress/api.php?courseid=1284&progress_filter=inprogress&include_activities=1"

# Query individual student detailed progress:
curl -H "Authorization: Bearer YOUR_WSTOKEN" \
     "https://your-moodle.edu.kh/report/idgprogress/api.php?courseid=1284&action=user_progress&userid=567"
```

### 2. Moodle Core Web Services (`/webservice/rest/server.php`)

- **Service Name:** `IDG Progress API` (`idg_progress_service`)
- **Functions:**
  - `report_idgprogress_get_course_progress`: Retrieve course metrics, tracked activities, and participant progress list.
  - `report_idgprogress_get_user_progress`: Retrieve detailed activity-by-activity breakdown for a specific student.

Example Web Service call:
```bash
curl -X POST "https://your-moodle.edu.kh/webservice/rest/server.php" \
     -d "wstoken=YOUR_WSTOKEN" \
     -d "wsfunction=report_idgprogress_get_course_progress" \
     -d "moodlewsrestformat=json" \
     -d "courseid=1284"
```


---

## Installation

1. Copy the `idgprogress` directory into your Moodle instance under `/report/`:
   ```bash
   cp -r report/idgprogress /path/to/moodle/report/idgprogress
   ```
2. Log in to your Moodle site as an administrator and navigate to **Site administration > Notifications** (or run `php admin/cli/upgrade.php`).
3. Complete the plugin installation.

---

## Capabilities & Permissions

- `report/idgprogress:view`:
  - Context level: Course (`CONTEXT_COURSE`)
  - Risk bitmask: `RISK_PERSONAL`
  - Default archetypes: `teacher`, `editingteacher`, `manager`

---

## License

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
