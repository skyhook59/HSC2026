# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HSC (Helga's Super Contest) is a PHP web application for managing NFL picks for the Las Vegas SuperContest. Users submit 5 weekly picks against the spread (ATS), and the system scores results, maintains standings, and handles weekly line imports from Westgate PDFs.

## Architecture

### Directory Structure

```
/HSC
├── public/              # Web-accessible entry points (pages & APIs)
│   ├── *.php           # Main pages (picks.php, standings.php, etc.)
│   ├── api/            # JSON REST endpoints
│   └── assets/         # Static files (CSS, logos)
├── private/            # Backend logic (not web-accessible)
│   ├── inc/            # Shared modules/functions
│   ├── cli/            # Command-line maintenance scripts
│   ├── storage/        # PDF storage (Westgate sheets)
│   └── vendor/         # Composer dependencies (pdfparser)
└── public/vendor/      # Composer dependencies (PHPMailer)
```

### Key Design Principles

- **Separation of concerns**: `public/` handles HTTP requests, `private/inc/` contains business logic
- **Modular includes**: Shared functions in `private/inc/*.php` are included by both web pages and CLI scripts
- **Session-based auth**: Simple session authentication with admin roles (60-day session lifetime)
- **Database-centric**: All state stored in MySQL via PDO with prepared statements
- **Stateless APIs**: RESTful JSON endpoints in `/public/api/`

## Database

**Connection**: MySQL/MariaDB via PDO
- **Host**: `hscdb.db` (Docker container)
- **Database**: `supercontest2025`
- **User**: `skyhook`
- **Config**: `/private/inc/db.php` (supports environment variable overrides)
- **Schema**: See `schema.sql` for full DDL

**Documentation**: See [`database.md`](database.md) for complete schema documentation including:
- Detailed table definitions with all columns and constraints
- Entity relationships and foreign keys
- Indexes and unique constraints
- Views (`v_game_results_with_line`)
- ATS logic explanation and examples
- Common query patterns
- Data integrity rules
- Maintenance operations and performance considerations

**Quick Reference - Key Tables**:
- `users` - User accounts and authentication
- `weeks` - Season/week metadata (lock_at_utc determines submission deadline)
- `games` - NFL games (home/away teams, scores, state)
- `lines` - Betting lines/spreads (fav_team, dog_team, spread)
- `picks` - User picks header (user_id, season_year, week_number)
- `pick_selections` - Individual game picks (pick_id, game_id, team_abbr)
- `results` - Weekly scoring results (wins, losses, pushes, points)
- `standings` - Cumulative season standings
- `maintenance_jobs` - Job throttling (prevents duplicate scoring runs)

## Core Modules (`private/inc/`)

| Module | Purpose |
|--------|---------|
| `db.php` | PDO connection, session setup (60-day lifetime), secret key definitions |
| `auth_guard.php` | Authentication check via `auth_required()` - redirects to login if not authenticated |
| `week.php` | Week logic: `current_season_week()`, `latest_locked_week()` |
| `week_lock_helpers.php` | Lock status checks: `is_week_locked()`, `week_lock_status()` |
| `validate_picks.php` | `hsc_validate_picks()` - validates 5 unique picks, no both sides of same game |
| `scoring.php` | `score_week()` - scores picks ATS, updates results & standings tables |
| `ats.php` | `ats_outcome()` - core ATS logic (favorite covers if margin - spread > 0) |
| `email.php` | `send_email()` - Gmail SMTP via PHPMailer (pick confirmations) |
| `lines_import.php` | `import_supercontest_lines_from_westgate()` - PDF import logic |
| `maintenance.php` | `maintenance_maybe_run_scores()` - auto-score throttled to 5min intervals |

## Common Development Commands

### CLI Scripts (`private/cli/`)

```bash
# Import betting lines from Westgate PDF
php private/cli/import_lines.php 2025 1 [pdf_url]

# Import latest week's lines (auto-detects current week)
php private/cli/import_latest_lines.php

# Score a completed week
php private/cli/score_week.php 2025 1

# Update game scores from external source
php private/cli/update_scores.php

# Initialize season weeks
php private/cli/seed_weeks.php

# Debug: dump week data
php private/cli/dump_week.php

# Debug: test Westgate PDF parsing
php private/cli/debug_westgate_pdf.php
```

### Running the Application

This is a standard PHP application. To run locally:

```bash
# Start PHP built-in server (for testing)
cd public
php -S localhost:8000

# Or configure Apache/Nginx to serve /public as document root
```

**Note**: Database must be running and accessible at `hscdb.db:3306` (or override via environment variables).

### Subdirectory Deployment

The application supports deployment in subdirectories (e.g., `/HSC-test/` for testing). Configure via the `APP_BASE_PATH` environment variable:

```bash
# For production (root deployment)
export APP_BASE_PATH=""

# For test environment in subdirectory
export APP_BASE_PATH="/HSC-test"
```

All URLs are generated using the `url()` helper function and redirects use `redirect()` (both defined in `db.php`), which automatically prepend the base path. See [DEPLOYMENT.md](DEPLOYMENT.md) for detailed deployment instructions.

## Key Workflows

### Pick Submission Flow

1. User visits `/picks.php` (requires authentication)
2. JavaScript loads games/lines from `/api/lines.php`
3. User selects 5 teams (UI prevents both sides of same game)
4. Submit button POSTs to `/api/submit_picks.php` with JSON: `{season, week, teams: [...]}`
5. API validates:
   - Authentication via session
   - Week not locked (unless admin override)
   - Picks via `hsc_validate_picks()` (5 unique teams, valid abbrs)
6. API upserts `picks` row, deletes old `pick_selections`, inserts 5 new selections
7. Confirmation email sent via `send_email()`
8. User redirected to `/week_picks.php` to view all picks

### Scoring Flow

**Trigger options**:
- CLI: `php private/cli/score_week.php 2025 1`
- API: `/public/api/admin/score_week.php` (admin only)
- Auto: Called from `/standings.php` via `maintenance_maybe_run_scores()` (throttled to 5min)

**Process**:
1. `score_week($season, $week)` fetches all picks and games for the week
2. For each user's 5 picks:
   - Calls `ats_outcome()` to determine win/loss/push
   - **ATS Logic**: Calculates `atsDiff = (fav_score - dog_score) - spread`
     - If `atsDiff > 0`: favorite covers → user wins if picked favorite
     - If `atsDiff < 0`: dog covers → user wins if picked dog
     - If `atsDiff == 0`: push → 0.5 points
   - Scoring: Win = 1 pt, Push = 0.5 pts, Loss = 0 pts
3. Inserts/updates `results` table (weekly totals)
4. Aggregates into `standings` table (cumulative season totals)

## API Endpoints

### Primary APIs (`/public/api/`)

- `submit_picks.php` (POST) - Submit 5 picks for a week
- `week_picks.php` (GET) - Fetch all picks for a week (visibility controlled by lock status)
- `lines.php` (GET) - Fetch available games/lines for a week
- `standings.php` (GET) - Fetch standings (season or weekly)
- `entries.php` (GET) - Fetch user entries
- `my_picks.php` (GET) - Fetch current user's picks

### Admin APIs

- `admin/update_scores.php` - Fetch game scores (ESPN) and update games table
- `admin/score_week.php` - Manually trigger week scoring
- `admin_submit_on_behalf.php` - Admin submits picks for another user

### Debug APIs

- `debug/whoami.php` - Test auth (returns current user)
- `debug/validate_picks.php` - Test pick validation

## Authentication & Authorization

**Session Variables**:
- `$_SESSION['user_id']` - User ID (integer)
- `$_SESSION['name']` - User display name
- `$_SESSION['is_admin']` - Admin flag (0 or 1)

**Auth Guard Usage**:
```php
require __DIR__ . '/../private/inc/auth_guard.php';
auth_required();  // Redirects to /index.php if not logged in
```

**Admin Checks**:
```php
$isAdmin = (bool)($_SESSION['is_admin'] ?? false);
if (!$isAdmin) {
    http_response_code(403);
    exit('Admin required');
}
```

**Privileges**:
- **Regular users**: Submit picks, view locked week picks, view standings
- **Admins**: Submit picks on behalf of others, import lines, override week locks, access admin pages

## Dependencies

**PHP Libraries** (via Composer):
- **PHPMailer** (`/public/vendor/phpmailer/phpmailer/`) - Email via Gmail SMTP
  - Config: Gmail SMTP, user `reminder@mcph.ee`
- **Smalot PdfParser** (`/private/vendor/smalot/pdfparser/`) - PDF text extraction
  - Fallback: Uses system `pdftotext` command if available

**External Services**:
- Gmail SMTP (for pick confirmation emails)
- Westgate SuperContest PDFs (for weekly line imports)
- ESPN (for game score updates, via admin API)

## Code Patterns

### Adding a New Feature

1. **Database**: Create/modify tables in MySQL
2. **Validation**: Add validation function in `private/inc/validate_*.php`
3. **Business Logic**: Add functions in `private/inc/*.php`
4. **API**: Create endpoint in `public/api/*.php` that calls business logic
5. **Page**: Create `public/*.php` that may call API or include logic directly
6. **CLI** (optional): Create script in `private/cli/*.php` for batch processing

### Common Patterns

**Loading shared logic**:
```php
require __DIR__ . '/../private/inc/db.php';        // Always load first
require __DIR__ . '/../private/inc/auth_guard.php'; // For authenticated pages
auth_required();
```

**API response format**:
```php
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'data' => $result]);
```

**HTML escaping**:
```php
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
```

**PDO prepared statements**:
```php
$stmt = $db->prepare('SELECT * FROM games WHERE season_year = ? AND week_number = ?');
$stmt->execute([$season, $week]);
$games = $stmt->fetchAll();
```

## Development Notes

### Week Lock Logic

- Picks cannot be submitted after `weeks.lock_at_utc` timestamp
- Admins can override locks via `admin_override` flag on picks
- Lock status checked via `is_week_locked($season, $week)` in `week_lock_helpers.php`
- All picks hidden until week is locked (prevents scouting other entries)

### Team Abbreviations

Standard 2-3 letter NFL team codes (e.g., `SF`, `KC`, `LAR`). Logos stored in `/public/assets/logos/{TEAM}.png`.

### Time Handling

- All timestamps stored in UTC in database (`*_utc` columns)
- PHP default timezone: `America/New_York` (set in CLI scripts and some pages)
- Kickoff times displayed in user's local timezone via JavaScript

### Maintenance Jobs

The `maintenance_jobs` table prevents duplicate background job runs by tracking last execution time:
```php
maintenance_maybe_run_scores(); // Throttled to 5min intervals
```

Called automatically from `/standings.php` to keep scores fresh without manual intervention.

## File Naming Conventions

- **Pages**: `snake_case.php` in `/public/`
- **APIs**: `snake_case.php` in `/public/api/` or `/public/api/admin/`
- **CLI scripts**: `snake_case.php` in `/private/cli/`
- **Includes**: `snake_case.php` in `/private/inc/`

## Security Considerations

**Current implementations**:
- Password hashing via `password_hash()` / `password_verify()`
- Session-based authentication
- Prepared statements (PDO) for all database queries
- HTML escaping via `htmlspecialchars()` for output
- Admin role separation

**Areas requiring attention when modifying**:
- Database credentials in `db.php` should use environment variables only (not hardcoded defaults)
- Email password in `email.php` is hardcoded (should use environment variable)
- No CSRF tokens on forms (simple application, but consider for future)
- No rate limiting on login/API endpoints
