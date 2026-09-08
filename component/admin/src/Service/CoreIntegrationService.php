<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

/**
 * Optional adapter between Membership and the public Xdecaro Core
 * cross-product reference contract.
 *
 * Core is intentionally not a mandatory dependency of Membership here.
 * The adapter can be resolved safely even when Core is not installed; methods
 * that need Core fail with a controlled RuntimeException instead of a fatal
 * class-not-found error.
 */
final class CoreIntegrationService
{
    private const COMPONENT = 'com_decaromembership';

    public function isAvailable(): bool
    {
        return class_exists(\Xdecaro\Core\Integration\EntityReference::class)
            && class_exists(\Xdecaro\Core\Integration\RelationReference::class);
    }

    /**
     * Create a Core entity reference owned by Membership.
     *
     * Entity names are supplied explicitly because they become public
     * integration contracts only when Membership deliberately publishes them.
     */
    public function createEntityReference(string $entity, int|string $id): object
    {
        $this->assertAvailable();

        return new \Xdecaro\Core\Integration\EntityReference(
            self::COMPONENT,
            $entity,
            $id
        );
    }

    /**
     * Create a typed relation from a Membership-owned entity to another
     * component's published entity reference.
     */
    public function createRelationReference(
        string $sourceEntity,
        int|string $sourceId,
        string $targetComponent,
        string $targetEntity,
        int|string $targetId,
        string $relationType
    ): object {
        $this->assertAvailable();

        $source = new \Xdecaro\Core\Integration\EntityReference(
            self::COMPONENT,
            $sourceEntity,
            $sourceId
        );

        $target = new \Xdecaro\Core\Integration\EntityReference(
            $targetComponent,
            $targetEntity,
            $targetId
        );

        return new \Xdecaro\Core\Integration\RelationReference(
            $source,
            $target,
            $relationType
        );
    }

    private function assertAvailable(): void
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException(
                'Xdecaro Core integration is unavailable. Install a compatible Xdecaro Core version before using cross-product references.'
            );
        }
    }
}
