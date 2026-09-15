<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use RuntimeException;
use Throwable;
use Xdecaro\Component\Decaromembership\Administrator\Service\AuditService;
use Xdecaro\Component\Decaromembership\Administrator\Service\MemberPeopleLinkService;
use Xdecaro\Component\Decaromembership\Administrator\Service\PeopleIntegrationService;
use Xdecaro\Component\Decaromembership\Administrator\Service\RecordRepository;

final class PeopleController extends BaseController
{
    private function people(): PeopleIntegrationService
    {
        $component = Factory::getApplication()->bootComponent('com_decaromembership');
        if (!method_exists($component, 'getPeopleIntegrationService')) {
            throw new RuntimeException('Membership People integration service is unavailable.');
        }

        return $component->getPeopleIntegrationService();
    }

    private function linker(): MemberPeopleLinkService
    {
        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return new MemberPeopleLinkService(
            new RecordRepository($db),
            $this->people(),
            new AuditService($db)
        );
    }

    public function search(): void
    {
        $this->checkToken('get');
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.manage', 'com_decaromembership')) {
            throw new RuntimeException('Not authorised', 403);
        }

        try {
            $q = trim($this->input->getString('q', ''));
            $rows = $this->people()->searchPeople($q, 20);
            $result = [];
            foreach ($rows as $row) {
                $result[] = [
                    'uuid' => strtolower(trim((string) ($row['uuid'] ?? ''))),
                    'display_name' => (string) ($row['display_name'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                    'phone' => (string) ($row['phone'] ?? ''),
                ];
            }
            echo new JsonResponse($result);
        } catch (Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }

        $app->close();
    }

    public function relink(): void
    {
        $this->checkToken();
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('membership.relink_person', 'com_decaromembership')) {
            throw new RuntimeException('Not authorised', 403);
        }

        try {
            $memberId = $this->input->getInt('member_id');
            $uuid = trim($this->input->getString('person_uuid', ''));
            if ($memberId < 1 || $uuid === '') {
                throw new RuntimeException('Member and People person are required.');
            }

            $this->linker()->relinkMember(
                $memberId,
                $uuid,
                (int) $user->id,
                Factory::getDate()->toSql()
            );
            echo new JsonResponse(['member_id' => $memberId, 'person_uuid' => strtolower($uuid)]);
        } catch (Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }

        $app->close();
    }
}
