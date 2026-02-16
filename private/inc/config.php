<?php
/**
 * Application Configuration
 *
 * Centralized configuration for the HSC SuperContest application.
 * Returns an associative array with all application settings.
 *
 * Usage:
 *   $config = require __DIR__ . '/config.php';
 *   $picksRequired = $config['picks']['required_count'];
 */

return [
    // Application settings
    'app' => [
        'name' => 'HSC SuperContest',
        'timezone' => 'America/New_York',
        'session_lifetime' => 60 * 24 * 60 * 60, // 60 days in seconds
        // Base path for URLs (e.g., '/HSC-test' or '' for root)
        // Set via APP_BASE_PATH environment variable
        'base_path' => rtrim(getenv('APP_BASE_PATH') ?: '', '/'),
    ],

    // Picks configuration
    'picks' => [
        'required_count' => 5,
        'admin_override_allowed' => true,
        'confirmation_email_enabled' => true,
    ],

    // Scoring configuration
    'scoring' => [
        'win_points' => 1.0,
        'push_points' => 0.5,
        'loss_points' => 0.0,
    ],

    // Maintenance settings
    'maintenance' => [
        'score_throttle_seconds' => 300, // 5 minutes
        'auto_scoring_enabled' => true,
    ],

    // ESPN API configuration
    'espn' => [
        'base_url' => 'https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard',
        'timeout' => 25,
        'season_types' => [
            'preseason' => 1,
            'regular' => 2,
            'postseason' => 3,
        ],
        'default_season_type' => 2, // regular season
    ],

    // Rate limiting
    'rate_limit' => [
        'login_attempts' => 5,
        'login_window' => 300, // 5 minutes
        'api_attempts' => 60,
        'api_window' => 60, // 1 minute
    ],

    // Week/Season settings
    'season' => [
        'min_week' => 1,
        'max_week' => 18,
        'min_year' => 2020,
        'max_year' => 2100,
    ],

    // Valid NFL team abbreviations
    'teams' => [
        'ARI', 'ATL', 'BAL', 'BUF', 'CAR', 'CHI', 'CIN', 'CLE',
        'DAL', 'DEN', 'DET', 'GB', 'HOU', 'IND', 'JAX', 'KC',
        'LAR', 'LAC', 'LV', 'MIA', 'MIN', 'NE', 'NO', 'NYG',
        'NYJ', 'PHI', 'PIT', 'SEA', 'SF', 'TB', 'TEN', 'WAS'
    ],

    // Team abbreviation mappings (ESPN -> HSC)
    'team_mappings' => [
        'WSH' => 'WAS',
        'JAC' => 'JAX',
        'LA' => 'LAR',
    ],

    // Logging
    'logging' => [
        'enabled' => true,
        'level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
        'max_file_size' => 10 * 1024 * 1024, // 10MB
    ],
];
