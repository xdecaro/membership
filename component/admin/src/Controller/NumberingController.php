<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use RuntimeException;
use Throwable;
use Xdecaro\Component\Decaromembership\Administrator\Service\CardNumberingPolicyService;

final class NumberingController extends BaseController
{
    public function policy(): void
    {
        $this->checkToken('get');
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.manage', 'com_decaromembership')) {
            throw new RuntimeException('Not authorised', 403);
        }

        try {
            $issuerUuid = strtolower(trim($this->input->getString('issuer_uuid', '')));
            $scope = $this->input->getCmd('scope', 'association');
            $policy = (new CardNumberingPolicyService(Factory::getContainer()->get(DatabaseInterface::class)))->resolve($issuerUuid, $scope);

            echo new JsonResponse([
                'numbering_mode' => (string) ($policy['numbering_mode'] ?? 'manual'),
                'source' => (string) ($policy['source'] ?? ''),
                'manual_edit' => !empty($policy['manual_edit']) ? 1 : 0,
                'sequence_padding' => (int) ($policy['sequence_padding'] ?? 7),
                'is_default' => !empty($policy['is_default']),
            ]);
        } catch (Throwable $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }

        $app->close();
    }
}
