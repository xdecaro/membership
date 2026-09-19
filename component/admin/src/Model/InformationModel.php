<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Throwable;
use Xdecaro\Component\Decaromembership\Administrator\Helper\MembershipHelper;

final class InformationModel extends BaseDatabaseModel
{
    private const MINIMUM_CORE_VERSION = '2.0.1';
    private const MINIMUM_PEOPLE_VERSION = '1.2.15';

    public function getInformation(): array
    {
        $componentVersion = $this->getExtensionVersion('component', 'com_decaromembership');
        $packageVersion = $this->getExtensionVersion('package', 'pkg_decaromembership');
        $coreVersion = $this->getExtensionVersion('package', 'pkg_xdecarocore');
        $peopleVersion = $this->getExtensionVersion('package', 'pkg_xdecaropeople');

        $coreCompatible = $coreVersion !== '' && version_compare($coreVersion, self::MINIMUM_CORE_VERSION, '>=');
        $peopleCompatible = $peopleVersion !== '' && version_compare($peopleVersion, self::MINIMUM_PEOPLE_VERSION, '>=');

        $peopleApiAvailable = false;
        if ($peopleCompatible) {
            try {
                $people = Factory::getApplication()->bootComponent('com_xdecaropeople');
                $peopleApiAvailable = method_exists($people, 'getPersonProviderService');
            } catch (Throwable) {
                $peopleApiAvailable = false;
            }
        }

        return [
            'environment' => MembershipHelper::environment(),
            'integrations' => MembershipHelper::integrations(),
            'diagnostics' => MembershipHelper::diagnostics(),
            'extensions' => [
                ['name' => 'Membership by xdecaro', 'element' => 'com_decaromembership', 'version' => $componentVersion ?: '1.9.0'],
                ['name' => 'Membership Package', 'element' => 'pkg_decaromembership', 'version' => $packageVersion],
            ],
            'core' => [
                'name' => 'Core by xdecaro',
                'element' => 'pkg_xdecarocore',
                'installed' => $coreVersion !== '',
                'version' => $coreVersion,
                'minimum_version' => self::MINIMUM_CORE_VERSION,
                'compatible' => $coreCompatible,
                'available' => $coreCompatible,
                'required' => true,
            ],
            'people' => [
                'name' => 'People by xdecaro',
                'element' => 'pkg_xdecaropeople',
                'installed' => $peopleVersion !== '',
                'version' => $peopleVersion,
                'minimum_version' => self::MINIMUM_PEOPLE_VERSION,
                'compatible' => $peopleCompatible,
                'available' => $peopleCompatible && $peopleApiAvailable,
                'required' => true,
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
