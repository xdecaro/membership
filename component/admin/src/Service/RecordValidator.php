<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use RuntimeException;

final class RecordValidator
{
    public function filter(array $config, array $input): array
    {
        $data = [];
        foreach ($config['fields'] as $name => $field) {
            $raw = $input[$name] ?? ($field['default'] ?? null);
            $data[$name] = $this->filterValue($raw, $field);
            if (($field['required'] ?? false) && ($data[$name] === '' || $data[$name] === null)) {
                throw new RuntimeException(Text::sprintf('COM_DECAROMEMBERSHIP_ERROR_REQUIRED', $name));
            }
        }
        return $data;
    }

    public function validateBusinessRules(string $entity, array $data): void
    {
        if ($entity === 'transfers' && ($data['status'] ?? '') === 'completed') {
            if (empty($data['source_confirmed']) || empty($data['destination_confirmed']) || (float) ($data['arrears_amount'] ?? 0) > 0) {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_TRANSFER_INCOMPLETE'));
            }
        }
        if ($entity === 'relations' && (int) ($data['member_id'] ?? 0) > 0 && (int) ($data['member_id'] ?? 0) === (int) ($data['related_member_id'] ?? 0)) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_SELF_RELATION'));
        }
    }

    private function filterValue(mixed $raw, array $field): mixed
    {
        if ($raw === '' || $raw === null) {
            if (($field['unique'] ?? false) || in_array($field['type'], ['number', 'money', 'relation'], true)) return null;
            if ($field['type'] === 'boolean') return 0;
            if ($field['type'] === 'published') return 1;
            return '';
        }
        return match ($field['type']) {
            'number', 'relation' => (int) $raw,
            'money' => round((float) str_replace(',', '.', (string) $raw), 2),
            'boolean', 'published' => (int) ((bool) $raw),
            'email' => $this->email((string) $raw),
            'date' => $this->date((string) $raw),
            'select' => $this->option((string) $raw, $field),
            'textarea' => trim(strip_tags((string) $raw)),
            default => trim(strip_tags((string) $raw)),
        };
    }

    private function email(string $value): string
    {
        $value = trim($value);
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_EMAIL'));
        return $value;
    }

    private function date(string $value): string
    {
        $value = trim($value);
        if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_DATE'));
        return $value;
    }

    private function option(string $value, array $field): string
    {
        if (!array_key_exists($value, $field['options'] ?? [])) throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_OPTION'));
        return $value;
    }
}
