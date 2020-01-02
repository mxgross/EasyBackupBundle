<?php

/*
 * This file is part of the Kimai EasyBackupBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\EasyBackupBundle\DependencyInjection;

use App\Plugin\AbstractPluginExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

class EasyBackupExtension extends AbstractPluginExtension implements PrependExtensionInterface
{
    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @throws \Exception
     */
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


        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
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
