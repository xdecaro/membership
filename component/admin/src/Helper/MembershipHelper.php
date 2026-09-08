<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Throwable;

final class MembershipHelper
{
    public static function integrations(): array
    {
        $components = [
            'com_decaroforms' => 'Forms by xdecaro',
            'com_decarodocuments' => 'Documents by xdecaro',
            'com_decarocourses' => 'Courses by xdecaro',
            'com_decarodcl' => 'Competitions by xdecaro',
            'com_decaropayments' => 'Payments by xdecaro',
            'com_decarocertificates' => 'Certificates by xdecaro',
        ];
        $result = [];

        foreach ($components as $option => $name) {
            $result[] = ['option' => $option, 'name' => $name, 'enabled' => ComponentHelper::isEnabled($option)];
        }

        return $result;
    }

    public static function diagnostics(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tables = array_flip($db->getTableList());
        $result = [];

        foreach (EntityRegistry::expectedTables() as $table) {
            $real = $db->replacePrefix($table);
            $result[] = ['name' => $table, 'ok' => isset($tables[$real])];
        }

        return $result;
    }

    public static function environment(): array
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $database = $db->getServerType() . ' ' . $db->getVersion();
        } catch (Throwable) {
            $database = '-';
        }

        return ['joomla' => JVERSION, 'php' => PHP_VERSION, 'database' => $database];
    }
}
