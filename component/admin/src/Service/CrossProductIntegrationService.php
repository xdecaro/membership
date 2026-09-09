<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Throwable;

final class CrossProductIntegrationService
{
    public const COMPONENT = 'com_decaromembership';

    public function publishNotification(array $data): ?int
    {
        if (!ComponentHelper::isEnabled('com_xdecaronotifications')) { return null; }
        $data['source_component'] = self::COMPONENT;
        try {
            $component = Factory::getApplication()->bootComponent('com_xdecaronotifications');
            if (!is_object($component) || !method_exists($component, 'getNotificationService')) { return null; }
            $service = $component->getNotificationService();
            return is_object($service) && method_exists($service, 'create') ? (int) $service->create($data) : null;
        } catch (Throwable $e) {
            Log::add('Membership notification bridge: ' . $e->getMessage(), Log::WARNING, 'com_decaromembership.integration');
            return null;
        }
    }

    public function createTask(array $data, ?array $assignee = null, int $actorUserId = 0): ?int
    {
        if (!ComponentHelper::isEnabled('com_xdecarotasks')) { return null; }
        $data['source_component'] = self::COMPONENT;
        try {
            $component = Factory::getApplication()->bootComponent('com_xdecarotasks');
            if (!is_object($component) || !method_exists($component, 'getTaskService')) { return null; }
            $service = $component->getTaskService();
            if (!is_object($service) || !method_exists($service, 'create')) { return null; }
            $id = (int) $service->create($data, max(0, $actorUserId));
            if ($id > 0 && is_array($assignee) && method_exists($service, 'assign')) {
                $type = trim((string) ($assignee['type'] ?? ''));
                $recipient = trim((string) ($assignee['id'] ?? ''));
                if ($type !== '' && $recipient !== '') {
                    $service->assign($id, $type, $recipient, max(0, $actorUserId), true);
                }
            }
            return $id > 0 ? $id : null;
        } catch (Throwable $e) {
            Log::add('Membership task bridge: ' . $e->getMessage(), Log::WARNING, 'com_decaromembership.integration');
            return null;
        }
    }
}
