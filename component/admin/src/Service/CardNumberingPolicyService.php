<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class CardNumberingPolicyService
{
    public const MODE_AUTOMATIC = 'automatic';
    public const MODE_MANUAL = 'manual';
    public const MODE_EXTERNAL = 'external';

    public function __construct(private DatabaseInterface $db)
    {
    }

    public function resolve(string $issuerOrganizationUuid, string $scope): array
    {
        $issuerOrganizationUuid = strtolower(trim($issuerOrganizationUuid));
        $scope = $this->normalizeScope($scope);

        if ($issuerOrganizationUuid !== '') {
            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__decaromembership_card_numbering_rules'))
                ->where($this->db->quoteName('issuer_organization_uuid') . ' = :issuer_uuid')
                ->where($this->db->quoteName('scope') . ' = :scope')
                ->where($this->db->quoteName('published') . ' = 1')
                ->bind(':issuer_uuid', $issuerOrganizationUuid)
                ->bind(':scope', $scope)
                ->order($this->db->quoteName('id') . ' DESC');

            $row = $this->db->setQuery($query, 0, 1)->loadAssoc();
            if (is_array($row) && $row !== []) {
                return $this->normalize($row, false);
            }
        }

        return $this->defaults($scope);
    }

    public function defaults(string $scope): array
    {
        $scope = $this->normalizeScope($scope);

        return [
            'id' => 0,
            'issuer_organization_uuid' => '',
            'scope' => $scope,
            'numbering_mode' => $scope === 'competition' ? self::MODE_AUTOMATIC : self::MODE_MANUAL,
            'source' => '',
            'manual_edit' => $scope === 'association' ? 1 : 0,
            'sequence_padding' => 7,
            'published' => 1,
            'is_default' => true,
        ];
    }

    public function duplicateExists(string $issuerOrganizationUuid, string $scope, int $excludeId = 0): bool
    {
        $issuerOrganizationUuid = strtolower(trim($issuerOrganizationUuid));
        if ($issuerOrganizationUuid === '') {
            return false;
        }

        $scope = $this->normalizeScope($scope);
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__decaromembership_card_numbering_rules'))
            ->where($this->db->quoteName('issuer_organization_uuid') . ' = :issuer_uuid')
            ->where($this->db->quoteName('scope') . ' = :scope')
            ->where($this->db->quoteName('published') . ' <> -2')
            ->bind(':issuer_uuid', $issuerOrganizationUuid)
            ->bind(':scope', $scope);

        if ($excludeId > 0) {
            $query->where($this->db->quoteName('id') . ' <> :id')
                ->bind(':id', $excludeId, ParameterType::INTEGER);
        }

        return (int) $this->db->setQuery($query)->loadResult() > 0;
    }

    public function normalize(array $rule, bool $isDefault = false): array
    {
        $mode = strtolower(trim((string) ($rule['numbering_mode'] ?? '')));
        if (!in_array($mode, [self::MODE_AUTOMATIC, self::MODE_MANUAL, self::MODE_EXTERNAL], true)) {
            $mode = self::MODE_MANUAL;
        }

        $padding = max(1, min(12, (int) ($rule['sequence_padding'] ?? 7)));

        return [
            'id' => (int) ($rule['id'] ?? 0),
            'issuer_organization_uuid' => strtolower(trim((string) ($rule['issuer_organization_uuid'] ?? ''))),
            'scope' => $this->normalizeScope((string) ($rule['scope'] ?? 'association')),
            'numbering_mode' => $mode,
            'source' => trim((string) ($rule['source'] ?? '')),
            'manual_edit' => !empty($rule['manual_edit']) ? 1 : 0,
            'sequence_padding' => $padding,
            'published' => (int) ($rule['published'] ?? 1),
            'is_default' => $isDefault,
        ];
    }

    private function normalizeScope(string $scope): string
    {
        return strtolower(trim($scope)) === 'competition' ? 'competition' : 'association';
    }
}
