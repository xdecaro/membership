<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

/** Optional adapter to the public Core by xdecaro cross-product reference contract. */
final class CoreIntegrationService
{
    private const COMPONENT = 'com_decaromembership';

    public function isAvailable(): bool
    {
        return class_exists(\Xdecaro\Core\Integration\EntityReference::class)
            && class_exists(\Xdecaro\Core\Integration\RelationReference::class);
    }

    public function createEntityReference(string $entity, int|string $id): object
    {
        $this->assertAvailable();

        return new \Xdecaro\Core\Integration\EntityReference(self::COMPONENT, $entity, $id);
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

        $source = new \Xdecaro\Core\Integration\EntityReference(self::COMPONENT, $sourceEntity, $sourceId);
        $target = new \Xdecaro\Core\Integration\EntityReference($targetComponent, $targetEntity, $targetId);

        return new \Xdecaro\Core\Integration\RelationReference($source, $target, $relationType);
    }

    private function assertAvailable(): void
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException(
                'Core by xdecaro integration is unavailable. Install a compatible Core by xdecaro version before using cross-product references.'
            );
        }
    }
}
