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
                $label = Text::_((string) ($field['label'] ?? $name));
                throw new RuntimeException(Text::sprintf('COM_DECAROMEMBERSHIP_ERROR_REQUIRED', $label));
            }
        }
        return $data;
    }

    public function validateBusinessRules(string $entity, array $data): void
    {
        if ($entity === 'transfers') {
            $fromOrganization = strtolower(trim((string) ($data['from_organization_uuid'] ?? '')));
            $toOrganization = strtolower(trim((string) ($data['to_organization_uuid'] ?? '')));

            if ($fromOrganization !== '' && $toOrganization !== '' && $fromOrganization === $toOrganization) {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_TRANSFER_SAME_ORGANIZATION'));
            }

            if (($data['status'] ?? '') === 'completed') {
                $delegationStatus = (string) ($data['delegation_status'] ?? '');
                $stickerStatus = (string) ($data['sticker_status'] ?? '');

                if (
                    empty($data['source_confirmed'])
                    || empty($data['destination_confirmed'])
                    || (float) ($data['arrears_amount'] ?? 0) > 0
                    || empty($data['effective_at'])
                    || !in_array($delegationStatus, ['confirmed', 'not_required'], true)
                    || $stickerStatus === '' || $stickerStatus === 'unchecked'
                    || trim((string) ($data['card_position'] ?? '')) === ''
                ) {
                    throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_TRANSFER_INCOMPLETE'));
                }
            }
        }
        if ($entity === 'relations' && (int) ($data['member_id'] ?? 0) > 0 && (int) ($data['member_id'] ?? 0) === (int) ($data['related_member_id'] ?? 0)) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_SELF_RELATION'));
        }
        if ($entity === 'payments' && (float) ($data['amount'] ?? 0) <= 0) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_PAYMENT_AMOUNT_POSITIVE'));
        }
        if ($entity === 'cards') {
            $scope = strtolower(trim((string) ($data['scope'] ?? 'association')));
            $personUuid = strtolower(trim((string) ($data['person_uuid'] ?? '')));
            $issuerUuid = strtolower(trim((string) ($data['issuer_organization_uuid'] ?? '')));
            $status = strtolower(trim((string) ($data['status'] ?? 'pending')));
            $cardNumber = trim((string) ($data['card_number'] ?? ''));

            if ($personUuid !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $personUuid)) {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_PERSON_INVALID'));
            }
            if ($issuerUuid !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $issuerUuid)) {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_ISSUER_INVALID'));
            }

            if ($scope === 'competition') {
                if ($personUuid === '') {
                    throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_PERSON_REQUIRED'));
                }
                if ($issuerUuid === '') {
                    throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_ISSUER_REQUIRED'));
                }
                if (empty($data['valid_from']) || empty($data['expires_at'])) {
                    throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_DCL_VALIDITY_REQUIRED'));
                }
            }

            if ($status === 'active' && $cardNumber === '') {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_ACTIVE_REQUIRED'));
            }

            $validFrom = trim((string) ($data['valid_from'] ?? ''));
            $expiresAt = trim((string) ($data['expires_at'] ?? ''));
            if ($validFrom !== '' && $expiresAt !== '' && $validFrom > $expiresAt) {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_DCL_VALIDITY_RANGE'));
            }
        }
    }

    private function filterValue(mixed $raw, array $field): mixed
    {
        if ($raw === '' || $raw === null) {
            if (($field['unique'] ?? false) || in_array($field['type'], ['number', 'money', 'relation', 'date'], true)) return null;
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
