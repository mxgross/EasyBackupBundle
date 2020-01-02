<?php

namespace KimaiPlugin\EasyBackupBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use App\Plugin\AbstractPluginExtension;

class EasyBackupExtension extends AbstractPluginExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->registerBundleConfiguration($container, $config);
        $container->setParameter('easy_settings', $config);

        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container)
    {
        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'roles' => [
                    'ROLE_SUPER_ADMIN' => [
                        'easy_backup',
                    ],
                ],
            ],
        ]);
    }
}
