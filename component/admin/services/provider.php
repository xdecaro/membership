<?php
/** @package com_decaromembership */
defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Xdecaro\Component\Decaromembership\Administrator\Service\CoreIntegrationService;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Xdecaro\\Component\\Decaromembership'));
        $container->registerServiceProvider(new MVCFactory('\\Xdecaro\\Component\\Decaromembership'));

        $container->share(
            CoreIntegrationService::class,
            static fn (Container $container): CoreIntegrationService => new CoreIntegrationService()
        );

        $container->set(ComponentInterface::class, static function (Container $container): ComponentInterface {
            $component = new MVCComponent($container->get(ComponentDispatcherFactoryInterface::class));
            $component->setMVCFactory($container->get(MVCFactoryInterface::class));

            return $component;
        });
    }
};
