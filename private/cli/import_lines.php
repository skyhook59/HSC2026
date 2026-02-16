#!/usr/bin/env php
<?php
/**
 * import_lines.php — Westgate PDF importer (time-split, NOT NULL-safe, writes dog_team)
 */
declare(strict_types=1);
require __DIR__ . '/../inc/db.php';
date_default_timezone_set('America/New_York');

$season = (int)($argv[1] ?? date('Y'));
$week   = (int)($argv[2] ?? 1);
$pdfUrl = $argv[3] ?? null;
if (!$pdfUrl) {
  $pdfUrl = sprintf('https://www.westgateresorts.com/supercontest/download/%d%%20SUPERCONTEST%%20GAME%%20SHEET%%20%%20WEEK%%20%d.pdf?contest=/%d/SuperContest/Card', $season, $week, $season);
}

$storeDir = __DIR__ . '/../storage/westgate';
if (!is_dir($storeDir)) { @mkdir($storeDir, 0700, true); }
$pdfFile = sprintf("%s/%d_wk%d.pdf", $storeDir, $season, $week);
$txtFile = sprintf("%s/%d_wk%d.txt", $storeDir, $season, $week);

function http_download(string $url, string $dest): void {
  $ch = curl_init($url);
  $fh = fopen($dest, 'wb');
  if (!$fh) throw new RuntimeException("Failed to open $dest for write");
  curl_setopt_array($ch, [
    CURLOPT_FILE => $fh,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 45,
    CURLOPT_USERAGENT => 'HSC/1.0 PDF Fetcher',
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_SSL_VERIFYPEER => true,
  ]);
  $ok = curl_exec($ch);
  $err = $ok ? null : curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  fclose($fh);
  if (!$ok || $code < 200 || $code >= 300) {
    @unlink($dest);
    throw new RuntimeException("Download failed HTTP $code: $err");
  }
}
function has_pdftotext(): bool { $out=@shell_exec('pdftotext -v 2>&1'); return is_string($out)&&stripos($out,'pdftotext')!==false; }
function pdf_to_text(string $pdf, string $txtPath): string {
  if (has_pdftotext()) {
    $cmd = sprintf('pdftotext -layout -nopgbrk %s -', escapeshellarg($pdf));
    $out = @shell_exec($cmd);
    if (is_string($out) && strlen(trim($out)) > 0) { file_put_contents($txtPath, $out); return $out; }
  }
  $autoload = __DIR__ . '/../../vendor/autoload.php';
  if (file_exists($autoload)) {
    require_once $autoload;
    if (class_exists('\\Smalot\\PdfParser\\Parser')) {
      $parser = new \Smalot\PdfParser\Parser();
      $doc = $parser->parseFile($pdf);
      $txt = $doc->getText();
      if ($txt) { file_put_contents($txtPath, $txt); return $txt; }
    }
  }
  throw new RuntimeException("No PDF extractor available (install poppler's pdftotext or upload vendor/smalot/pdfparser).");
}

function nfl_team_map(): array {
  return [
    'cardinals'=>'ARI','arizona'=>'ARI',
    'falcons'=>'ATL','atlanta'=>'ATL',
    'ravens'=>'BAL','baltimore'=>'BAL',
    'bills'=>'BUF','buffalo'=>'BUF',
    'panthers'=>'CAR','carolina'=>'CAR',
    'bears'=>'CHI','chicago'=>'CHI',
    'bengals'=>'CIN','cincinnati'=>'CIN',
    'browns'=>'CLE','cleveland'=>'CLE',
    'cowboys'=>'DAL','dallas'=>'DAL',
    'broncos'=>'DEN','denver'=>'DEN',
    'lions'=>'DET','detroit'=>'DET',
    'packers'=>'GB','green bay'=>'GB','greenbay'=>'GB',
    'texans'=>'HOU','houston'=>'HOU',
    'colts'=>'IND','indianapolis'=>'IND',
    'jaguars'=>'JAX','jacksonville'=>'JAX',
    'chiefs'=>'KC','kansas city'=>'KC','kansascity'=>'KC',
    'chargers'=>'LAC','los angeles chargers'=>'LAC','la chargers'=>'LAC','l.a. chargers'=>'LAC',
    'rams'=>'LAR','los angeles rams'=>'LAR','la rams'=>'LAR','l.a. rams'=>'LAR',
    'raiders'=>'LV','las vegas'=>'LV','lasvegas'=>'LV',
    'dolphins'=>'MIA','miami'=>'MIA',
    'vikings'=>'MIN','minnesota'=>'MIN',
    'patriots'=>'NE','new england'=>'NE',
    'saints'=>'NO','new orleans'=>'NO',
    'giants'=>'NYG','new york giants'=>'NYG',
    'jets'=>'NYJ','new york jets'=>'NYJ',
    'eagles'=>'PHI','philadelphia'=>'PHI',
    'steelers'=>'PIT','pittsburgh'=>'PIT',
    'seahawks'=>'SEA','seattle'=>'SEA',
    '49ers'=>'SF','niners'=>'SF','san francisco'=>'SF','forty niners'=>'SF','forty-niners'=>'SF',
    'buccaneers'=>'TB','bucs'=>'TB','tampa bay'=>'TB',
    'titans'=>'TEN','tennessee'=>'TEN',
    'commanders'=>'WAS','washington'=>'WAS',
  ];
}
function norm(string $s): string { $s=strtolower($s); $s=preg_replace('/[^\p{L}\p{N}\s\.\-@,]/u','',$s); $s=preg_replace('/\s+/',' ', $s); return trim($s); }
function team_abbr_from(string $text): ?string {
  $map=nfl_team_map(); $key=norm($text);
  if (isset($map[$key])) return $map[$key];
  foreach ($map as $name=>$abbr) { if (str_contains($key,$name)) return $abbr; }
  $u=strtoupper(trim($text)); if (in_array($u, array_unique(array_values($map)), true)) return $u;
  return null;
}
function split_by_time(string $l): ?array {
  if (!preg_match('/\b(\d{1,2}:\d{2}\s?[AP]M)\b/u', $l, $m, PREG_OFFSET_CAPTURE)) return null;
  $timeStr = $m[0][0]; $pos = $m[0][1];
  $left  = rtrim(substr($l, 0, $pos));
  $right = ltrim(substr($l, $pos + strlen($timeStr)));
  return [$left, $right, $timeStr];
}
function parse_pairs_from_text(string $txt): array {
  $pairs=[]; $lines=preg_split('/\R+/', $txt);
  foreach ($lines as $line) {
    $l=trim($line); if ($l==='' || strlen($l)<10) continue;
    $split=split_by_time($l); if(!$split) continue;
    [$leftRaw,$rightRaw,$timeStr] = $split;

    $leftRaw = preg_replace('/^\s*\d+\s+/', '', $leftRaw);
    $leftRaw = preg_replace('/\s+@.*$/', '', $leftRaw);
    $leftRaw = preg_replace('/\*+$/', '', $leftRaw);
    $leftTeam = trim($leftRaw);

    if (!preg_match('/^\s*\d+\s+(.+?)\*?\s+([+\-]?\d+(?:\.\d+)?|PK|PICK|PICKEM)\s*$/iu', $rightRaw, $rm)) continue;
    $rightTeam = trim($rm[1]); $spreadTok=strtoupper(trim($rm[2]));

    $leftAbbr  = team_abbr_from($leftTeam);
    $rightAbbr = team_abbr_from($rightTeam);
    if (!$leftAbbr || !$rightAbbr) continue;

    if (in_array($spreadTok, ['PK','PICK','PICKEM'], true))      { $fav=$leftAbbr;  $dog=$rightAbbr; $abs=0.0; }
    else { $val=(float)$spreadTok; if ($val>=0){ $fav=$leftAbbr; $dog=$rightAbbr; $abs=abs($val);} else { $fav=$rightAbbr; $dog=$leftAbbr; $abs=abs($val);} }

    $pairs[$fav.'-'.$dog] = ['fav_team'=>$fav,'dog_team'=>$dog,'spread'=>$abs];
  }
  return array_values($pairs);
}
function placeholder_kickoff(): string { return '1970-01-01 00:00:00'; }

try {
  http_download($pdfUrl, $pdfFile);
  $text = pdf_to_text($pdfFile, $txtFile);
  $pairs = parse_pairs_from_text($text);
  if (count($pairs) < 6) throw new RuntimeException("Parsed too few pairs from PDF (".count($pairs)."). See $txtFile.");

  $db->beginTransaction();
  $insGame = $db->prepare("INSERT INTO games (season_year, week_number, home_team, away_team, kickoff_utc, state)
                           VALUES (?, ?, ?, ?, ?, 'pre')");
  $findGame = $db->prepare("SELECT id FROM games WHERE season_year=? AND week_number=? AND ((home_team=? AND away_team=?) OR (home_team=? AND away_team=?))");
  // ✅ Write dog_team too
  $repLine  = $db->prepare("REPLACE INTO `lines` (game_id, fav_team, dog_team, spread, source, posted_at_utc)
                            VALUES (?, ?, ?, ?, 'westgate_pdf', UTC_TIMESTAMP())");

  $upserts=0;
  foreach ($pairs as $p) {
    $fav=$p['fav_team']; $dog=$p['dog_team']; $spread=(float)$p['spread'];

    $findGame->execute([$season,$week,$dog,$fav,$fav,$dog]);
    $g=$findGame->fetch(PDO::FETCH_ASSOC);
    if(!$g){
      $insGame->execute([$season,$week,$dog,$fav, placeholder_kickoff()]);
      $gameId=(int)$db->lastInsertId();
      if ($gameId===0) { $q=$db->prepare("SELECT id FROM games WHERE season_year=? AND week_number=? AND home_team=? AND away_team=?"); $q->execute([$season,$week,$dog,$fav]); $gameId=(int)$q->fetchColumn(); }
    } else { $gameId=(int)$g['id']; }

    $repLine->execute([$gameId, $fav, $dog, $spread]);
    $upserts++;
  }

  $db->commit();
  echo json_encode(['ok'=>true,'season'=>$season,'week'=>$week,'upserts'=>$upserts,'pairs'=>count($pairs),'stored_pdf'=>basename($pdfFile),'stored_txt'=>basename($txtFile)]).PHP_EOL;
} catch (Throwable $e) {
  if ($db->inTransaction()) $db->rollBack();
  fwrite(STDERR, "[import_lines] ".$e->getMessage().PHP_EOL);
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'pdf'=>$pdfUrl]).PHP_EOL;
  exit(1);
}
