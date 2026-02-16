<?php
// public/admin_import_lines.php
// Admin-only page to trigger import_lines.php (Westgate PDF importer) from the browser.

require __DIR__ . '/../private/inc/db.php';
require __DIR__ . '/../private/inc/auth_guard.php';
require __DIR__ . '/../private/inc/csrf.php';

auth_required();
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo "Admins only";
    exit;
}

$season        = (int)date('Y');
$week          = '';
$pdfUrl        = '';
$result        = null;
$output_raw    = null;
$exit_code     = null;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!csrf_verify()) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $season = isset($_POST['season']) ? (int)$_POST['season'] : (int)date('Y');
        $week   = trim($_POST['week'] ?? '');
        $pdfUrl = trim($_POST['pdf_url'] ?? '');

    if ($week === '') {
        $error_message = "Week is required.";
    } else {
        $weekInt = (int)$week;

        // Build CLI command to call your existing importer.
        $cliScript = realpath(__DIR__ . '/../private/cli/import_lines.php');
        if ($cliScript === false) {
            $error_message = "Could not locate /../private/cli/import_lines.php";
        } else {
            // Use the same PHP binary that’s running this script.
            $phpBin = PHP_BINARY ?: 'php';

            $cmdParts = [
                escapeshellcmd($phpBin),
                escapeshellarg($cliScript),
                escapeshellarg((string)$season),
                escapeshellarg((string)$weekInt),
            ];

            if ($pdfUrl !== '') {
                $cmdParts[] = escapeshellarg($pdfUrl);
            }

            $cmd = implode(' ', $cmdParts) . ' 2>&1';

            $lines    = [];
            $exitCode = 0;
            exec($cmd, $lines, $exitCode);

            $output_raw = implode("\n", $lines);
            $exit_code  = $exitCode;

            // Try to parse the last line as JSON (import_lines.php echoes JSON).
            $jsonLine = null;
            if (!empty($lines)) {
                $jsonLine = trim($lines[count($lines) - 1]);
            }

            $decoded = $jsonLine ? json_decode($jsonLine, true) : null;
            if (is_array($decoded)) {
                $result = $decoded;
                if (empty($decoded['ok'])) {
                    $error_message = $decoded['error'] ?? 'Importer reported an error.';
                }
            } else {
                // Could not parse JSON; treat as error but still show raw output.
                $error_message = "Importer did not return valid JSON. See raw output below.";
            }
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin – Import SuperContest Lines</title>
  <style>
    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      max-width: 900px;
      margin: 2rem auto;
      padding: 0 1rem 3rem;
      background: #f5f5f7;
    }
    h1 {
      margin-bottom: 0.25rem;
    }
    .subtle {
      color: #666;
      font-size: 0.9rem;
      margin-bottom: 1rem;
    }
    .card {
      background: #fff;
      border-radius: 10px;
      padding: 1.25rem 1.5rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      margin-top: 1rem;
    }
    label {
      display: block;
      margin-top: 0.75rem;
      font-weight: 600;
      font-size: 0.9rem;
    }
    input[type="number"], input[type="text"] {
      margin-top: 0.25rem;
      padding: 0.35rem 0.5rem;
      font-size: 0.95rem;
      border-radius: 4px;
      border: 1px solid #ccc;
      width: 220px;
      max-width: 100%;
    }
    .btn {
      display: inline-block;
      margin-top: 1rem;
      padding: 0.45rem 0.9rem;
      border-radius: 6px;
      border: none;
      cursor: pointer;
      font-size: 0.95rem;
    }
    .btn--primary {
      background: #2563eb;
      color: #fff;
    }
    .btn--primary:hover {
      background: #1d4ed8;
    }
    .message {
      margin-top: 1rem;
      padding: 0.75rem 1rem;
      border-radius: 6px;
      font-size: 0.9rem;
    }
    .message.error {
      background: #fee2e2;
      border: 1px solid #fecaca;
      color: #991b1b;
    }
    .message.success {
      background: #dcfce7;
      border: 1px solid #bbf7d0;
      color: #166534;
    }
    pre {
      background: #0b1020;
      color: #e5e7eb;
      padding: 0.75rem 1rem;
      border-radius: 6px;
      font-size: 0.8rem;
      overflow-x: auto;
      margin-top: 0.75rem;
    }
    a {
      color: #2563eb;
    }
  </style>
</head>
<body>

  <h1>Import SuperContest Lines</h1>
  <div class="subtle">
    Runs the existing <code>cli/import_lines.php</code> script from the browser and imports lines into
    <code>games</code> and <code>lines</code>.
  </div>

  <div class="card">
    <form method="post">
      <?= csrf_field() ?>
      <label for="season">Season year</label>
      <input
        type="number"
        name="season"
        id="season"
        value="<?= htmlspecialchars((string)$season, ENT_QUOTES, 'UTF-8') ?>"
        required
      >

      <label for="week">Week number</label>
      <input
        type="number"
        name="week"
        id="week"
        value="<?= htmlspecialchars((string)$week, ENT_QUOTES, 'UTF-8') ?>"
        placeholder="e.g. 1"
        required
      >

      <label for="pdf_url">
        PDF URL (optional – leave blank to let <code>import_lines.php</code> use its default URL)
      </label>
      <input
        type="text"
        name="pdf_url"
        id="pdf_url"
        value="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>"
        placeholder="Paste Westgate SuperContest PDF URL here"
      >

      <button class="btn btn--primary" type="submit">Run Import</button>
    </form>

    <?php if ($error_message): ?>
      <div class="message error">
        <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php elseif ($result && !empty($result['ok'])): ?>
      <div class="message success">
        <?= htmlspecialchars("Import completed successfully.", ENT_QUOTES, 'UTF-8') ?>
        <?php if (!empty($result['season']) && !empty($result['week'])): ?>
          <div>
            Season <?= (int)$result['season'] ?>, Week <?= (int)$result['week'] ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($result['pdf'])): ?>
          <div>
            PDF: <?= htmlspecialchars($result['pdf'], ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($output_raw !== null): ?>
      <h3 style="margin-top:1.5rem; font-size:0.9rem;">Raw importer output (exit code <?= (int)$exit_code ?>)</h3>
      <pre><?= htmlspecialchars($output_raw, ENT_QUOTES, 'UTF-8') ?></pre>
    <?php endif; ?>
  </div>

  <div class="subtle" style="margin-top:1.5rem;">
    Tip: open the Westgate SuperContest card page, right-click the PDF for the week you want, copy its link,
    and paste it into the “PDF URL” field above. Or leave it blank and rely on the default URL logic inside
    <code>cli/import_lines.php</code>.
  </div>

</body>
</html>