<?php
defined('_JEXEC') or die;
use Joomla\CMS\Extension\PluginInterface; use Joomla\CMS\Plugin\PluginHelper; use Joomla\DI\Container; use Joomla\DI\ServiceProviderInterface; use Xdecaro\Plugin\Xdecaroanalytics\Decaromembership\Extension\Decaromembership;
return new class implements ServiceProviderInterface { public function register(Container $container): void { $container->set(PluginInterface::class, $container->lazy(Decaromembership::class, static fn (): Decaromembership => new Decaromembership((array) PluginHelper::getPlugin('xdecaroanalytics','decaromembership')))); } };
