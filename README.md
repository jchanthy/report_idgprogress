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
- **Memory-Efficient CSV Stream:** Exports large cohorts row-by-row via `php://output` while releasing session locks (`\core\session\manager::write_close()`).

---

## Directory Structure

```text
report/idgprogress/
├── version.php                         # Plugin version, component declaration, and minimum requirements
├── db/
│   └── access.php                      # Capability definition (report/idgprogress:view)
├── lang/
│   ├── en/
│   │   └── report_idgprogress.php      # English language pack
│   └── km/
│       └── report_idgprogress.php      # Khmer (ភាសាខ្មែរ) language pack
├── lib.php                             # Navigation hooks, completion computation, and UI helpers
├── index.php                           # Interactive dashboard and paged participant table
└── export.php                          # UTF-8 BOM CSV streaming export script
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
