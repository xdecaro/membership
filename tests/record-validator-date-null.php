<?php

declare(strict_types=1);

namespace Joomla\CMS\Language {
    final class Text
    {
        public static function _(string $key): string { return $key; }
        public static function sprintf(string $key, mixed ...$args): string { return $key . ':' . implode(',', array_map('strval', $args)); }
    }
}

namespace {
    defined('_JEXEC') || define('_JEXEC', 1);

    require_once __DIR__ . '/../component/admin/src/Service/RecordValidator.php';

    use Xdecaro\Component\Decaromembership\Administrator\Service\RecordValidator;

    $validator = new RecordValidator();
    $config = [
        'fields' => [
            'first_registration_date' => [
                'type' => 'date',
            ],
        ],
    ];

    $data = $validator->filter($config, ['first_registration_date' => '']);

    if (!array_key_exists('first_registration_date', $data)) {
        fwrite(STDERR, "Date field missing from filtered data.\n");
        exit(1);
    }

    if ($data['first_registration_date'] !== null) {
        fwrite(STDERR, "Optional empty date must be NULL, got " . var_export($data['first_registration_date'], true) . "\n");
        exit(1);
    }

    $valid = $validator->filter($config, ['first_registration_date' => '2026-09-16']);
    if ($valid['first_registration_date'] !== '2026-09-16') {
        fwrite(STDERR, "Valid date must remain unchanged.\n");
        exit(1);
    }

    echo "Membership RecordValidator optional date NULL contract OK\n";
}
