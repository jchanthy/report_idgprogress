# Antigravity Agent Guidelines for `idg_progress`

## Execution & Approval Policy
- **Always Auto-Approve:** All terminal commands, tool executions, file edits, and implementation steps for this project are pre-approved. Do not pause, block, or stop to prompt for manual confirmation or user approval. Proceed directly with end-to-end execution.
- **Proactive Execution:** Autonomously run builds, packaging, testing, and validation steps.

## Moodle Architecture & Standards
- **Component:** `report_idgprogress`
- **Location:** `/report/idgprogress/`
- **Coding Standard:** Strict Moodle Coding Guidelines and PSR-12 compliance.
- **Security:** Always verify `defined('MOODLE_INTERNAL') || die();` on all include/library files, and enforce `$PAGE->set_context()`, `require_login()`, and `require_capability()` on entry points. All database operations must use parameterized `$DB`.
- **Khmer Unicode & BOM:** All exported CSV streams must output the UTF-8 Byte Order Mark (`\xEF\xBB\xBF`) at byte 0 to preserve Khmer Unicode (`\u1780-\u17FF`) without font corruption in Microsoft Excel.
- **Packaging:** Distribution ZIPs must contain the plugin folder at root (`idgprogress/...`) for direct installation via Moodle's Plugin Installer web UI.
