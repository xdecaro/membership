<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Xdecaro\Component\Decaromembership\Administrator\Helper\MembershipHelper;

final class InformationModel extends BaseDatabaseModel
{
    public function getInformation(): array
    {
        return [
            'environment' => MembershipHelper::environment(),
            'integrations' => MembershipHelper::integrations(),
            'diagnostics' => MembershipHelper::diagnostics(),
            'extensions' => [
                ['name' => 'Membership', 'element' => 'com_decaromembership', 'version' => '1.0.0'],
                ['name' => 'Membership Package', 'element' => 'pkg_decaromembership', 'version' => '1.0.0'],
            ],
            'updates' => [
                'component' => 'https://raw.githubusercontent.com/xdecaro/membership/main/updates/com_decaromembership.xml',
                'package' => 'https://raw.githubusercontent.com/xdecaro/membership/main/updates/pkg_decaromembership.xml',
            ],
        ];
    }
}
