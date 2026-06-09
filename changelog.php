<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Changelog';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$changelogPath = __DIR__ . '/../docs/changelog.md';
$raw = file_exists($changelogPath) ? file_get_contents($changelogPath) : 'No changelog found.';

function cl_inline(string $text): string {
    // inline code
    $text = preg_replace('/`([^`]+)`/', '<code class="bg-slate-100 px-1 py-0.5 rounded text-sm font-mono text-slate-800">$1</code>', $text);
    // bold
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    return $text;
}

$lines      = explode("\n", $raw);
$output     = '';
$inList     = false;
$inCode     = false;
$codeBuffer = '';

foreach ($lines as $line) {
    // --- code fence ---
    if (preg_match('/^```/', $line)) {
        if (!$inCode) {
            if ($inList) { $output .= "</ul>\n"; $inList = false; }
            $inCode     = true;
            $codeBuffer = '';
        } else {
            $inCode  = false;
            $output .= '<pre class="bg-slate-100 border border-slate-200 rounded-lg p-4 overflow-x-auto text-sm font-mono text-slate-800 mb-4">'
                     . htmlspecialchars($codeBuffer)
                     . "</pre>\n";
        }
        continue;
    }

    if ($inCode) {
        $codeBuffer .= $line . "\n";
        continue;
    }

    // --- headings ---
    if (preg_match('/^# (.+)$/', $line, $m)) {
        if ($inList) { $output .= "</ul>\n"; $inList = false; }
        $output .= '<h1 class="text-3xl font-semibold text-black mb-6">' . cl_inline(htmlspecialchars($m[1])) . "</h1>\n";
        continue;
    }
    if (preg_match('/^## \[(.+?)\](.*)$/', $line, $m)) {
        if ($inList) { $output .= "</ul>\n"; $inList = false; }
        $output .= '<h2 class="text-2xl font-semibold text-secondary mt-8 mb-3 pb-2 border-b border-outline-variant">'
                 . '[' . htmlspecialchars($m[1]) . ']' . htmlspecialchars($m[2])
                 . "</h2>\n";
        continue;
    }
    if (preg_match('/^### (.+)$/', $line, $m)) {
        if ($inList) { $output .= "</ul>\n"; $inList = false; }
        $output .= '<h3 class="text-base font-semibold text-on-surface mt-5 mb-2">' . cl_inline(htmlspecialchars($m[1])) . "</h3>\n";
        continue;
    }

    // --- horizontal rule ---
    if (trim($line) === '---') {
        if ($inList) { $output .= "</ul>\n"; $inList = false; }
        $output .= '<hr class="my-6 border-outline-variant">' . "\n";
        continue;
    }

    // --- list item: - **bold** rest ---
    if (preg_match('/^- \*\*(.+?)\*\*(.*)$/', $line, $m)) {
        if (!$inList) { $output .= '<ul class="list-disc pl-5 space-y-1 mb-4 text-on-surface-variant">' . "\n"; $inList = true; }
        $output .= '<li class="mb-1"><span class="font-semibold text-on-surface">'
                 . htmlspecialchars($m[1]) . '</span>'
                 . cl_inline(htmlspecialchars($m[2]))
                 . "</li>\n";
        continue;
    }

    // --- list item: - text ---
    if (preg_match('/^- (.+)$/', $line, $m)) {
        if (!$inList) { $output .= '<ul class="list-disc pl-5 space-y-1 mb-4 text-on-surface-variant">' . "\n"; $inList = true; }
        $output .= '<li class="mb-1">' . cl_inline(htmlspecialchars($m[1])) . "</li>\n";
        continue;
    }

    // --- empty line ---
    if (trim($line) === '') {
        if ($inList) { $output .= "</ul>\n"; $inList = false; }
        $output .= "\n";
        continue;
    }

    // --- bold-only line (sub-heading like **1. Title**) ---
    if (preg_match('/^\*\*(.+?)\*\*(.*)$/', $line, $m)) {
        if ($inList) { $output .= "</ul>\n"; $inList = false; }
        $output .= '<p class="font-semibold text-on-surface mt-4 mb-1">'
                 . htmlspecialchars($m[1])
                 . cl_inline(htmlspecialchars($m[2]))
                 . "</p>\n";
        continue;
    }

    // --- regular paragraph ---
    if ($inList) { $output .= "</ul>\n"; $inList = false; }
    $output .= '<p class="text-on-surface-variant mb-2">' . cl_inline(htmlspecialchars($line)) . "</p>\n";
}

if ($inList) { $output .= "</ul>\n"; }

$changelogContent = $output;
?>

<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-semibold text-black">System Changelog</h1>
</div>

<div class="bg-white shadow-sm border border-outline-variant rounded-lg p-8">
    <div class="max-w-none">
        <?= $changelogContent ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
