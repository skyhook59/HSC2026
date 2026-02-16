<?php
/**
 * Unit Tests for ATS (Against The Spread) Logic
 *
 * To run: vendor/bin/phpunit tests/Unit/AtsTest.php
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../private/inc/ats.php';

class AtsTest extends TestCase
{
    public function testFavoriteCoversSpread()
    {
        $pick = ['picked_team' => 'KC'];
        $line = ['fav_team' => 'KC', 'dog_team' => 'BUF', 'spread' => 3.5];
        $game = [
            'home_team' => 'KC',
            'away_team' => 'BUF',
            'home_score' => 27,
            'away_score' => 20,
            'state' => 'final'
        ];

        $result = ats_outcome($pick, $line, $game);
        $this->assertEquals('win', $result);
    }

    public function testDogCoversSpread()
    {
        $pick = ['picked_team' => 'BUF'];
        $line = ['fav_team' => 'KC', 'dog_team' => 'BUF', 'spread' => 7.0];
        $game = [
            'home_team' => 'KC',
            'away_team' => 'BUF',
            'home_score' => 24,
            'away_score' => 21,
            'state' => 'final'
        ];

        $result = ats_outcome($pick, $line, $game);
        $this->assertEquals('win', $result);
    }

    public function testPush()
    {
        $pick = ['picked_team' => 'KC'];
        $line = ['fav_team' => 'KC', 'dog_team' => 'BUF', 'spread' => 7.0];
        $game = [
            'home_team' => 'KC',
            'away_team' => 'BUF',
            'home_score' => 28,
            'away_score' => 21,
            'state' => 'final'
        ];

        $result = ats_outcome($pick, $line, $game);
        $this->assertEquals('push', $result);
    }

    public function testPendingGame()
    {
        $pick = ['picked_team' => 'KC'];
        $line = ['fav_team' => 'KC', 'dog_team' => 'BUF', 'spread' => 3.5];
        $game = [
            'home_team' => 'KC',
            'away_team' => 'BUF',
            'home_score' => null,
            'away_score' => null,
            'state' => 'pre'
        ];

        $result = ats_outcome($pick, $line, $game);
        $this->assertEquals('pending', $result);
    }
}
