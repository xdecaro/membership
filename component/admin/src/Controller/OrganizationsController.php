<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use RuntimeException;
use Throwable;
use Xdecaro\Component\Decaromembership\Administrator\Service\OrganizationsIntegrationService;

final class OrganizationsController extends BaseController
{
    private function organizations(): OrganizationsIntegrationService
    {
        $component = Factory::getApplication()->bootComponent('com_decaromembership');
        if (!method_exists($component, 'getOrganizationsIntegrationService')) {
            throw new RuntimeException('Membership Organizations integration service is unavailable.');
        }

        return $component->getOrganizationsIntegrationService();
    }

    public function search(): void
    {
        $this->checkToken('get');
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.manage', 'com_decaromembership')) {
            throw new RuntimeException('Not authorised', 403);
        }

        try {
            $q = trim($this->input->getString('q', ''));
            if (mb_strlen($q) < 2) {
                echo new JsonResponse([]);
                $app->close();
                return;
            }

            $rows = $this->organizations()->searchOrganizations($q, 25);
            $result = [];
            foreach ($rows as $row) {
                $uuid = strtolower(trim((string) ($row['uuid'] ?? '')));
                if ($uuid === '') {
                    continue;
                }

                $result[] = [
                    'uuid' => $uuid,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'short_name' => trim((string) ($row['short_name'] ?? '')),
                    'type' => trim((string) ($row['type'] ?? '')),
                    'country' => trim((string) ($row['country_name'] ?? $row['country'] ?? '')),
                ];
            }

            echo new JsonResponse($result);
        } catch (Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }

        $app->close();
    }
}
