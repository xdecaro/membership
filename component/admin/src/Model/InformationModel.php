<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Throwable;
use Xdecaro\Component\Decaromembership\Administrator\Helper\MembershipHelper;

final class InformationModel extends BaseDatabaseModel
{
    public function getInformation(): array
    {
        $componentVersion = $this->getExtensionVersion('component', 'com_decaromembership');
        $packageVersion = $this->getExtensionVersion('package', 'pkg_decaromembership');
        $coreVersion = $this->getExtensionVersion('package', 'pkg_xdecarocore');
        $coreApiAvailable = class_exists(\Xdecaro\Core\Integration\EntityReference::class)
            && class_exists(\Xdecaro\Core\Integration\RelationReference::class);
        $coreUiAvailable = class_exists(\Xdecaro\Core\Asset\AssetService::class);

        return [
            'environment' => MembershipHelper::environment(),
            'integrations' => MembershipHelper::integrations(),
            'diagnostics' => MembershipHelper::diagnostics(),
            'extensions' => [
                ['name' => 'Membership by xdecaro', 'element' => 'com_decaromembership', 'version' => $componentVersion ?: '1.1.0'],
                ['name' => 'Membership Package', 'element' => 'pkg_decaromembership', 'version' => $packageVersion],
            ],
            'core' => [
                'name' => 'Core by xdecaro',
                'element' => 'pkg_xdecarocore',
                'installed' => $coreVersion !== '' || $coreApiAvailable || $coreUiAvailable,
                'version' => $coreVersion,
                'api_available' => $coreApiAvailable,
                'ui_available' => $coreUiAvailable,
                'minimum_ui_version' => '1.1.0',
                'required' => false,
            ],
            'updates' => [
                'package' => 'https://raw.githubusercontent.com/xdecaro/membership/main/updates/pkg_decaromembership.xml',
            ],
        ];
    }

    private function getExtensionVersion(string $type, string $element): string
    {
        try {
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('manifest_cache'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = :type')
                ->where($db->quoteName('element') . ' = :element')
                ->bind(':type', $type, ParameterType::STRING)
                ->bind(':element', $element, ParameterType::STRING);
            $manifest = json_decode((string) $db->setQuery($query, 0, 1)->loadResult(), true);

            return is_array($manifest) ? trim((string) ($manifest['version'] ?? '')) : '';
        } catch (Throwable) {
            return '';
        }
    }
}
