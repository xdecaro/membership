<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class pkg_decaromembershipInstallerScript
{
    public function postflight($type, $parent): void
    {
        if (!in_array((string) $type, ['install', 'discover_install'], true)) { return; }
        try {
            /** @var DatabaseInterface $db */
            $db=Factory::getContainer()->get(DatabaseInterface::class);
            foreach ([['xdecaroanalytics','decaromembership'],['task','decaromembership']] as [$folder,$element]) {
                $enabled=1; $pluginType='plugin';
                $query=$db->getQuery(true)->update($db->quoteName('#__extensions'))->set($db->quoteName('enabled').' = :enabled')->where($db->quoteName('type').' = :type')->where($db->quoteName('folder').' = :folder')->where($db->quoteName('element').' = :element')->bind(':enabled',$enabled,ParameterType::INTEGER)->bind(':type',$pluginType)->bind(':folder',$folder)->bind(':element',$element);
                $db->setQuery($query)->execute();
            }
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage('Membership installed, but optional integration plugins could not be enabled automatically.','warning');
        }
    }
}
