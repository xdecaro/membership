<?php
$root = dirname(__DIR__) . '/component';
$template = file_get_contents($root . '/admin/tmpl/cardbulk/default.php');
$view = file_get_contents($root . '/admin/src/View/Cardbulk/HtmlView.php');
$controller = file_get_contents($root . '/admin/src/Controller/CardbulkController.php');
$js = file_get_contents($root . '/media/js/cardbulk.js');

$checks = [
    'template separates selection and preview' => str_contains($template, '$isPreview') && str_contains($template, 'if (!$isPreview)') && str_contains($template, 'if ($isPreview)'),
    'preview has a back action' => str_contains($template, 'COM_DECAROMEMBERSHIP_CARDBULK_BACK'),
    'view exposes explicit cardbulk step' => str_contains($view, 'public string $step') && str_contains($view, "getCmd('step'"),
    'controller redirects preview to preview step' => str_contains($controller, '&step=preview'),
    'selection can be restored after back' => str_contains($template, 'data-cardbulk-initial-selection') && str_contains($js, 'data-cardbulk-initial-selection'),
];

$failed = false;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ": {$name}\n";
    $failed = $failed || !$ok;
}

exit($failed ? 1 : 0);
