<?php
$root = dirname(__DIR__) . '/component';
$css = file_get_contents($root . '/media/css/admin.css');

if ($css === false) {
    fwrite(STDERR, "admin.css non leggibile\n");
    exit(2);
}

$checks = [
    'info dl second track can shrink' => '/\.dm-info dl\{[^}]*grid-template-columns:minmax\(120px,\.4fr\) minmax\(0,1fr\)/',
    'info dd has min-width zero' => '/\.dm-info dd\{[^}]*min-width:0/',
    'info dd can wrap long updater URLs' => '/\.dm-info dd\{[^}]*overflow-wrap:anywhere[^}]*word-break:break-word/',
];

$failed = false;
foreach ($checks as $label => $pattern) {
    $ok = preg_match($pattern, $css) === 1;
    echo ($ok ? 'PASS' : 'FAIL') . ": {$label}\n";
    $failed = $failed || !$ok;
}

exit($failed ? 1 : 0);
