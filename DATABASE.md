# HSC Database Schema

## Overview

**Database**: `supercontest2025`
**Engine**: MariaDB 10.3.29
**Charset**: utf8mb4
**Connection Details**: See `/private/inc/db.php`

This database manages the complete lifecycle of an NFL picks contest, from user authentication through line imports, pick submissions, scoring, and standings calculation.

## Entity Relationship Overview

```
users (1) ──< picks (many)
picks (1) ──< pick_selections (many)
games (1) ──< lines (1)
games (1) ──< pick_selections (many)
users (1) ──< results (many)
users (1) ──< standings (1 per season)
```

## Tables

### `users`

User accounts and authentication.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint(20) | PRIMARY KEY, AUTO_INCREMENT | Unique user identifier |
| `email` | varchar(190) | UNIQUE, NOT NULL | User email (login credential) |
| `name` | varchar(120) | NOT NULL | Display name |
| `password_hash` | varchar(255) | NOT NULL | bcrypt password hash |
| `is_admin` | tinyint(1) | DEFAULT 0 | Admin flag (0=regular, 1=admin) |
| `created_at` | timestamp | DEFAULT current_timestamp() | Account creation timestamp |

**Indexes**:
- PRIMARY KEY on `id`
- UNIQUE KEY on `email`

**Relationships**:
- Referenced by `picks.user_id` (FK: `picks_ibfk_1`)

---

### `weeks`

Season week metadata and lock configuration.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint(20) | PRIMARY KEY, AUTO_INCREMENT | Unique week identifier |
| `season_year` | int(11) | NOT NULL | Season year (e.g., 2025) |
| `week_number` | int(11) | NOT NULL | Week number (1-18) |
| `lock_at_utc` | datetime | NOT NULL | UTC timestamp when picks lock |
| `visible_after_lock` | tinyint(1) | DEFAULT 1 | Whether picks are visible after lock |

**Indexes**:
- PRIMARY KEY on `id`
- UNIQUE KEY on (`season_year`, `week_number`)

**Notes**:
- `lock_at_utc` determines pick submission deadline
- Picks are hidden until week is locked (prevents scouting)
- Admins can override lock via `picks.admin_override`

---

### `games`

NFL game schedule and scores.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint(20) | PRIMARY KEY, AUTO_INCREMENT | Unique game identifier |
| `season_year` | int(11) | NOT NULL | Season year |
| `week_number` | int(11) | NOT NULL | Week number |
| `kickoff_utc` | datetime | NULL | Game kickoff time (UTC) |
| `home_team` | char(3) | NOT NULL | Home team abbreviation (e.g., 'SF') |
| `away_team` | char(3) | NOT NULL | Away team abbreviation |
| `state` | enum | DEFAULT 'pre' | Game state: 'pre', 'in_progress', 'final' |
| `home_score` | int(11) | DEFAULT 0 | Home team score |
| `away_score` | int(11) | DEFAULT 0 | Away team score |
| `winner_team` | char(3) | NULL | Winning team abbreviation |
| `period` | tinyint(4) | NULL | Current period/quarter |
| `clock_seconds` | int(11) | NULL | Remaining clock seconds |
| `last_update_utc` | datetime | NULL | Last score update timestamp |

**Indexes**:
- PRIMARY KEY on `id`

**Relationships**:
- Referenced by `lines.game_id`
- Referenced by `pick_selections.game_id`

**Notes**:
- Team abbreviations are 2-3 character codes (standardized across NFL)
- Scores updated via `/public/api/admin/update_scores.php`
- State transitions: `pre` → `in_progress` → `final`

---

### `lines`

Betting lines (spreads) for games.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint(20) | PRIMARY KEY, AUTO_INCREMENT | Unique line identifier |
| `game_id` | bigint(20) | NOT NULL | References `games.id` |
| `fav_team` | char(3) | NOT NULL | Favorite team abbreviation |
| `dog_team` | char(3) | NOT NULL | Underdog team abbreviation |
| `spread` | decimal(4,1) | NOT NULL | Point spread (favorite gives points) |
| `source` | varchar(40) | DEFAULT 'westgate' | Line source (e.g., 'westgate') |
| `posted_at_utc` | datetime | NOT NULL | When line was posted |

**Indexes**:
- PRIMARY KEY on `id`

**Relationships**:
- Foreign key to `games.game_id` (not explicitly defined but implied)

**Notes**:
- Spread is from favorite's perspective (positive = favorite gives points)
- Imported from Westgate SuperContest PDFs via `/private/cli/import_lines.php`
- One line per game (latest line is used)

---

### `picks`

User pick submissions (header record).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint(20) | PRIMARY KEY, AUTO_INCREMENT | Unique pick submission identifier |
| `user_id` | bigint(20) | NOT NULL, FK | User who submitted picks |
| `season_year` | int(11) | NOT NULL | Season year |
| `week_number` | int(11) | NOT NULL | Week number |
| `submitted_at_utc` | datetime | NOT NULL | Submission timestamp |
| `admin_override` | tinyint(1) | DEFAULT 0 | Admin override flag (bypass lock) |

**Indexes**:
- PRIMARY KEY on `id`
- UNIQUE KEY on (`user_id`, `season_year`, `week_number`)

**Constraints**:
- FK `picks_ibfk_1`: `user_id` → `users.id`

**Relationships**:
- Foreign key from `users.id`
- Referenced by `pick_selections.pick_id` (FK: `fk_ps_pick`)

**Notes**:
- Each user can submit picks once per week (enforced by unique key)
- Resubmission replaces previous picks (deletes old `pick_selections`)
- `admin_override=1` allows submission after week lock

---

### `pick_selections`

Individual game picks (detail records).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint(20) | PRIMARY KEY, AUTO_INCREMENT | Unique selection identifier |
| `pick_id` | bigint(20) | NOT NULL, FK | References `picks.id` |
| `game_id` | bigint(20) | NOT NULL | References `games.id` |
| `team_abbr` | char(3) | NOT NULL | Team abbreviation picked |

**Indexes**:
- PRIMARY KEY on `id`
- UNIQUE KEY on (`pick_id`, `game_id`)

**Constraints**:
- FK `fk_ps_pick`: `pick_id` → `picks.id` ON DELETE CASCADE

**Relationships**:
- Foreign key from `picks.id`
- References `games.id` (implied)

**Notes**:
- Exactly 5 selections per pick (enforced by validation logic)
- Unique constraint prevents picking same game twice
- Cascade delete ensures selections are removed when pick is deleted

---

### `results`

Weekly scoring results per user.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint(20) | PRIMARY KEY, AUTO_INCREMENT | Unique result identifier |
| `season_year` | int(11) | NOT NULL | Season year |
| `week_number` | int(11) | NOT NULL | Week number |
| `user_id` | bigint(20) | NOT NULL | User identifier |
| `wins` | int(11) | DEFAULT 0 | Number of winning picks (ATS) |
| `losses` | int(11) | DEFAULT 0 | Number of losing picks (ATS) |
| `pushes` | int(11) | DEFAULT 0 | Number of push picks (ATS) |
| `points` | decimal(4,1) | DEFAULT 0.0 | Total points (win=1, push=0.5, loss=0) |

**Indexes**:
- PRIMARY KEY on `id`
- UNIQUE KEY on (`season_year`, `week_number`, `user_id`)

**Notes**:
- Populated by `score_week()` function in `/private/inc/scoring.php`
- Scoring: Win = 1 point, Push = 0.5 points, Loss = 0 points
- Updated/inserted when week scoring runs

---

### `standings`

Cumulative season standings per user.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint(20) | PRIMARY KEY, AUTO_INCREMENT | Unique standing identifier |
| `season_year` | int(11) | NOT NULL | Season year |
| `user_id` | bigint(20) | NOT NULL | User identifier |
| `total_wins` | int(11) | DEFAULT 0 | Cumulative wins for season |
| `total_losses` | int(11) | DEFAULT 0 | Cumulative losses for season |
| `total_pushes` | int(11) | DEFAULT 0 | Cumulative pushes for season |
| `total_points` | decimal(5,1) | DEFAULT 0.0 | Cumulative points for season |
| `last_updated_utc` | datetime | NOT NULL | Last update timestamp |

**Indexes**:
- PRIMARY KEY on `id`
- UNIQUE KEY on (`season_year`, `user_id`)

**Notes**:
- Aggregated from `results` table by `score_week()`
- One row per user per season
- Updated incrementally as weeks are scored

---

### `maintenance_jobs`

Job execution tracking for background maintenance tasks.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `job_name` | varchar(64) | PRIMARY KEY | Unique job identifier |
| `last_run_utc` | datetime | NOT NULL | Last execution timestamp |

**Indexes**:
- PRIMARY KEY on `job_name`

**Notes**:
- Used by `maintenance_maybe_run_scores()` in `/private/inc/maintenance.php`
- Prevents duplicate job runs (throttles scoring to 5-minute intervals)
- Example job name: `'score_latest_week'`

---

## Views

### `v_game_results_with_line`

Combines game results with betting lines and calculates ATS winner.

**Columns**:
| Column | Type | Description |
|--------|------|-------------|
| `game_id` | bigint(20) | Game identifier |
| `season_year` | int(11) | Season year |
| `week_number` | int(11) | Week number |
| `kickoff_utc` | datetime | Kickoff time |
| `home_team` | char(3) | Home team abbreviation |
| `away_team` | char(3) | Away team abbreviation |
| `home_score` | int(11) | Home team score |
| `away_score` | int(11) | Away team score |
| `fav_team` | char(3) | Favorite team |
| `dog_team` | char(3) | Underdog team |
| `spread` | decimal(4,1) | Point spread |
| `ats_winner` | varchar(4) | ATS winner ('fav', 'dog', or 'PUSH') |

**Definition**:
```sql
SELECT
  g.id AS game_id,
  g.season_year,
  g.week_number,
  g.kickoff_utc,
  g.home_team,
  g.away_team,
  g.home_score,
  g.away_score,
  l.fav_team,
  l.dog_team,
  l.spread,
  CASE
    WHEN g.state <> 'final' THEN NULL
    WHEN l.fav_team = g.home_team AND g.home_score - g.away_score > l.spread THEN l.fav_team
    WHEN l.fav_team = g.away_team AND g.away_score - g.home_score > l.spread THEN l.fav_team
    WHEN l.fav_team = g.home_team AND g.away_score - g.home_score > -l.spread THEN l.dog_team
    WHEN l.fav_team = g.away_team AND g.home_score - g.away_score > -l.spread THEN l.dog_team
    ELSE 'PUSH'
  END AS ats_winner
FROM games g
JOIN lines l ON g.id = l.game_id
```

**Notes**:
- Provides ATS outcome calculation at database level
- `ats_winner` is NULL for non-final games
- Used for reporting and verification purposes

---

## ATS (Against The Spread) Logic

The core scoring logic determines whether a pick wins, loses, or pushes based on the spread.

### Formula

```
atsDiff = (fav_score - dog_score) - spread
```

- If `atsDiff > 0`: Favorite covers → Winner is favorite team
- If `atsDiff < 0`: Underdog covers → Winner is dog team
- If `atsDiff == 0`: Push → 0.5 points awarded

### Example

Game: SF (-7) vs DAL
Final Score: SF 28, DAL 24

```
atsDiff = (28 - 24) - 7 = -3
```

Since `atsDiff < 0`, the dog (DAL) covers. Anyone who picked DAL wins (1 point), anyone who picked SF loses (0 points).

### Implementation

**Primary implementation**: `/private/inc/ats.php` - `ats_outcome()` function
**Database view**: `v_game_results_with_line` provides ATS winner column
**Usage**: Called by `score_week()` in `/private/inc/scoring.php`

---

## Common Query Patterns

### Get Current Week's Games with Lines

```sql
SELECT g.*, l.fav_team, l.dog_team, l.spread
FROM games g
JOIN lines l ON g.id = l.game_id
WHERE g.season_year = ? AND g.week_number = ?
ORDER BY g.kickoff_utc;
```

### Get User's Picks for a Week

```sql
SELECT ps.team_abbr, ps.game_id, g.home_team, g.away_team
FROM picks p
JOIN pick_selections ps ON p.id = ps.pick_id
JOIN games g ON ps.game_id = g.id
WHERE p.user_id = ? AND p.season_year = ? AND p.week_number = ?;
```

### Get Season Standings

```sql
SELECT u.name, s.total_points, s.total_wins, s.total_losses, s.total_pushes
FROM standings s
JOIN users u ON s.user_id = u.id
WHERE s.season_year = ?
ORDER BY s.total_points DESC, s.total_wins DESC;
```

### Get Weekly Results

```sql
SELECT u.name, r.wins, r.losses, r.pushes, r.points
FROM results r
JOIN users u ON r.user_id = u.id
WHERE r.season_year = ? AND r.week_number = ?
ORDER BY r.points DESC, r.wins DESC;
```

### Check if User Has Submitted Picks

```sql
SELECT COUNT(*) as has_picks
FROM picks
WHERE user_id = ? AND season_year = ? AND week_number = ?;
```

---

## Data Integrity Rules

### Enforced by Database

1. **Unique picks per user per week**: UNIQUE KEY on `picks(user_id, season_year, week_number)`
2. **No duplicate game picks**: UNIQUE KEY on `pick_selections(pick_id, game_id)`
3. **Unique standings per user per season**: UNIQUE KEY on `standings(season_year, user_id)`
4. **Cascade delete**: Deleting a pick removes all associated selections
5. **User referential integrity**: Picks must reference valid user

### Enforced by Application

1. **Exactly 5 picks per week**: Validated by `hsc_validate_picks()` in `/private/inc/validate_picks.php`
2. **No picking both sides**: Cannot pick both teams from same game
3. **Valid team abbreviations**: Must match teams in selected games
4. **Week lock enforcement**: Cannot submit after `weeks.lock_at_utc` (unless admin override)
5. **Game finality**: ATS scoring only runs on games with `state='final'`

---

## Maintenance Operations

### Backup

```bash
# Export database
mysqldump -u skyhook -p supercontest2025 > backup_$(date +%Y%m%d).sql

# Import database
mysql -u skyhook -p supercontest2025 < backup.sql
```

### Reset Season

```sql
-- Clear all picks for a season
DELETE FROM picks WHERE season_year = 2025;

-- Clear results and standings
DELETE FROM results WHERE season_year = 2025;
DELETE FROM standings WHERE season_year = 2025;

-- Clear games and lines
DELETE FROM games WHERE season_year = 2025;
```

### Rebuild Standings

```bash
# Re-score all weeks (rebuilds standings)
php private/cli/score_week.php 2025 1
php private/cli/score_week.php 2025 2
# ... repeat for all completed weeks
```

---

## Performance Considerations

### Indexes

All critical query paths are indexed:
- User lookups: `users.email` (UNIQUE)
- Week lookups: `weeks(season_year, week_number)` (UNIQUE)
- Pick lookups: `picks(user_id, season_year, week_number)` (UNIQUE)
- Standing lookups: `standings(season_year, user_id)` (UNIQUE)

### Query Optimization

- Use prepared statements for all queries (PDO)
- Avoid N+1 queries by joining related tables
- Limit result sets with WHERE clauses
- Use view `v_game_results_with_line` for ATS calculations

### Scaling Considerations

Current design supports:
- **Users**: Thousands (bigint primary keys)
- **Games per week**: ~16 NFL games
- **Picks per week**: 5 per user
- **Weeks per season**: 18

For high user counts, consider:
- Adding indexes on `games(season_year, week_number, state)`
- Caching standings in application layer
- Partitioning `results` table by season
