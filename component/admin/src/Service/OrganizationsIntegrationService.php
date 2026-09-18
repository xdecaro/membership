<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use RuntimeException;
use Throwable;

final class OrganizationsIntegrationService
{
    public function isAvailable(): bool
    {
        try {
            $this->provider();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function getOrganization(string $uuid): ?array
    {
        $uuid = strtolower(trim($uuid));
        if ($uuid === '') {
            return null;
        }

        try {
            return $this->provider()->getOrganization($uuid, false);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Organizations lookup is unavailable: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    public function searchOrganizations(string $search = '', int $limit = 200): array
    {
        try {
            return array_values((array) $this->provider()->searchOrganizations(
                ['search' => trim($search)],
                max(1, min(200, $limit)),
                false
            ));
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Organizations search is unavailable: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    public function validateOptionalUuid(?string $uuid): ?string
    {
        $uuid = strtolower(trim((string) $uuid));
        if ($uuid === '') {
            return null;
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
            throw new RuntimeException('Invalid Organizations UUID.');
        }

        $organization = $this->getOrganization($uuid);
        if ($organization === null || strtolower(trim((string) ($organization['uuid'] ?? ''))) !== $uuid) {
            throw new RuntimeException('Organizations record was not found.');
        }

        return $uuid;
    }

    private function provider(): object
    {
        try {
            $component = Factory::getApplication()->bootComponent('com_xdecaroorganizations');
        } catch (Throwable $e) {
            throw new RuntimeException('Organizations component is not available.', 0, $e);
        }

        if (!is_object($component) || !method_exists($component, 'getOrganizationProviderService')) {
            throw new RuntimeException('Organizations public provider is unavailable.');
        }

        $provider = $component->getOrganizationProviderService();

        if (!is_object($provider)
            || !method_exists($provider, 'getOrganization')
            || !method_exists($provider, 'searchOrganizations')) {
            throw new RuntimeException('Organizations public provider is incompatible.');
        }

        return $provider;
    }
}
