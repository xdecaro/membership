<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

/** Optional adapter to the public Core by xdecaro cross-product reference contract. */
final class CoreIntegrationService
{
    private const COMPONENT = 'com_decaromembership';
    private const MINIMUM_CORE_VERSION = '1.3.0';

    public function isAvailable(): bool
    {
        return class_exists(\xdecaro\Core\Version::class)
            && version_compare((string) \xdecaro\Core\Version::VERSION, self::MINIMUM_CORE_VERSION, '>=')
            && class_exists(\xdecaro\Core\Integration\EntityReference::class)
            && class_exists(\xdecaro\Core\Integration\RelationReference::class);
    }

    public function createEntityReference(string $entity, int|string $id): object
    {
        $this->assertAvailable();

        return new \xdecaro\Core\Integration\EntityReference(self::COMPONENT, $entity, $id);
    }

    public function createRelationReference(
        string $sourceEntity,
        int|string $sourceId,
        string $targetComponent,
        string $targetEntity,
        int|string $targetId,
        string $relationType
    ): object {
        $this->assertAvailable();

        $source = new \xdecaro\Core\Integration\EntityReference(self::COMPONENT, $sourceEntity, $sourceId);
        $target = new \xdecaro\Core\Integration\EntityReference($targetComponent, $targetEntity, $targetId);

        return new \xdecaro\Core\Integration\RelationReference($source, $target, $relationType);
    }

    private function assertAvailable(): void
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException(
                'Core by xdecaro integration is unavailable. Install Core by xdecaro 1.3.0 or newer before using cross-product references.'
            );
        }
    }
}
