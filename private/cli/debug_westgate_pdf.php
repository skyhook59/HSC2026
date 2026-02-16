#!/usr/bin/env php
<?php
/**
 * debug_westgate_pdf.php — column-aware with PREG_OFFSET_CAPTURE
 * Prints JSON lines with how each row is split and parsed.
 */
ini_set('memory_limit', '256M'); set_time_limit(90);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) { require $autoload; fwrite(STDERR, "[debug] autoload on\n"); }

function argval(string $name, ?string $default=null): ?string {
  foreach ($GLOBALS['argv'] as $arg) if (str_starts_with($arg, "--$name=")) return substr($arg, strlen($name)+3);
  return $default;
}

$pdfUrl  = argval('pdf'); $pdfFile = argval('file'); $saveAs = argval('save');

function http_download(string $url, string $dest): void {
  fwrite(STDERR, "[debug] downloading: $url\n");
  $ch = curl_init($url); $fh=fopen($dest,'wb'); if(!$fh) throw new RuntimeException("open $dest fail");
  curl_setopt_array($ch,[CURLOPT_FILE=>$fh,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>45,CURLOPT_USERAGENT=>'HSC/1.0 PDF Debug']);
  $ok=curl_exec($ch); $err=$ok?null:curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); fclose($fh);
  if(!$ok || $code<200 || $code>=300){@unlink($dest); throw new RuntimeException("HTTP $code: $err");}
}
function has_pdftotext(): bool { $out=@shell_exec('pdftotext -v 2>&1'); return is_string($out)&&stripos($out,'pdftotext')!==false; }
function pdf_to_text(string $pdf): string {
  if(has_pdftotext()){ fwrite(STDERR,"[debug] using pdftotext -layout\n"); $out=@shell_exec(sprintf('pdftotext -layout -nopgbrk %s -', escapeshellarg($pdf))); if(is_string($out)&&strlen(trim($out))>0) return $out; }
  if(class_exists('\\Smalot\\PdfParser\\Parser')){ fwrite(STDERR,"[debug] using Smalot\\PdfParser\n"); $parser=new \Smalot\PdfParser\Parser(); return $parser->parseFile($pdf)->getText() ?: ''; }
  throw new RuntimeException("No extractor available");
}
function split_by_time(string $l): ?array {
  if (!preg_match('/\b(\d{1,2}:\d{2}\s?[AP]M)\b/u', $l, $m, PREG_OFFSET_CAPTURE)) return null;
  $t=$m[0][0]; $pos=$m[0][1];
  $left=rtrim(substr($l,0,$pos)); $right=ltrim(substr($l,$pos+strlen($t)));
  return [$left,$right,$t];
}
function nfl_team_map(): array {
  return ['eagles'=>'PHI','cowboys'=>'DAL','chiefs'=>'KC','chargers'=>'LAC','steelers'=>'PIT','jets'=>'NYJ','dolphins'=>'MIA','colts'=>'IND','jaguars'=>'JAX','panthers'=>'CAR','commanders'=>'WAS','giants'=>'NYG','bengals'=>'CIN','browns'=>'CLE','patriots'=>'NE','raiders'=>'LV','cardinals'=>'ARI','saints'=>'NO','buccaneers'=>'TB','falcons'=>'ATL','broncos'=>'DEN','titans'=>'TEN','49ers'=>'SF','seahawks'=>'SEA','packers'=>'GB','lions'=>'DET','rams'=>'LAR','texans'=>'HOU','ravens'=>'BAL','bills'=>'BUF','vikings'=>'MIN','bears'=>'CHI'];
}
function norm(string $s): string { $s=strtolower($s); $s=preg_replace('/[^\p{L}\p{N}\s\.\-@,]/u','',$s); $s=preg_replace('/\s+/',' ', $s); return trim($s); }
function team_abbr_from(string $text): ?string { $map=nfl_team_map(); $key=norm($text); foreach($map as $name=>$abbr){ if($key===$name || str_contains($key,$name)) return $abbr; } $u=strtoupper(trim($text)); if(in_array($u,array_values($map),true)) return $u; return null; }

function parse_line(string $l): ?array {
  $split = split_by_time($l); if(!$split) return null; [$leftRaw,$rightRaw,$timeStr]=$split;
  $leftClean = preg_replace('/^\s*\d+\s+/', '', $leftRaw);
  $leftClean = preg_replace('/\s+@.*$/', '', $leftClean);
  $leftClean = preg_replace('/\*+$/', '', $leftClean);
  $leftTeam = trim($leftClean);

  if (!preg_match('/^\s*\d+\s+(.+?)\*?\s+([+\-]?\d+(?:\.\d+)?|PK|PICK|PICKEM)\s*$/iu', $rightRaw, $rm)) return null;
  $rightTeam = trim($rm[1]); $spreadTok=strtoupper(trim($rm[2]));

  $leftAbbr=team_abbr_from($leftTeam); $rightAbbr=team_abbr_from($rightTeam);
  $fav=null;$dog=null;$abs=null;
  if (in_array($spreadTok,['PK','PICK','PICKEM'],true)){ $fav=$leftAbbr;$dog=$rightAbbr;$abs=0.0; }
  else { $val=(float)$spreadTok; if($val>=0){$fav=$leftAbbr;$dog=$rightAbbr;$abs=abs($val);} else {$fav=$rightAbbr;$dog=$leftAbbr;$abs=abs($val);} }
  return ['raw'=>$l,'left_seg'=>$leftRaw,'right_seg'=>$rightRaw,'time'=>$timeStr,'left_team_str'=>$leftTeam,'right_team_str'=>$rightTeam,'left_abbr'=>$leftAbbr,'right_abbr'=>$rightAbbr,'spread_raw'=>$spreadTok,'fav'=>$fav,'dog'=>$dog,'spread_abs'=>$abs];
}

$work = $pdfFile;
if($pdfUrl){ $tmp=sys_get_temp_dir().'/hsc_dbg_'.bin2hex(random_bytes(4)).'.pdf'; http_download($pdfUrl,$tmp); if($saveAs){ @copy($tmp,$saveAs); fwrite(STDERR,"[debug] saved $saveAs\n"); } $work=$tmp; }
$text = pdf_to_text($work);
$lines = preg_split('/\R+/', $text);
$cnt=0; $ok=0; $warn=0;
foreach($lines as $i=>$line){
  $line=trim($line); if($line==='') continue;
  $parsed = parse_line($line);
  if($parsed){ $ok++; $parsed['lineNo']=$i+1; echo json_encode($parsed, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL; }
  else {
    // print candidates that look like matchup rows (contain AM/PM and + or PK)
    if (preg_match('/\b[AP]M\b/',$line) && preg_match('/(\+|\bPK\b|\bPICK\b)/i',$line)){
      $warn++; echo json_encode(['lineNo'=>$i+1,'raw'=>$line,'note'=>'unparsed_candidate']).PHP_EOL;
    }
  }
  $cnt++;
}
fwrite(STDERR, "[summary] lines=$cnt ok=$ok warn=$warn\n");
