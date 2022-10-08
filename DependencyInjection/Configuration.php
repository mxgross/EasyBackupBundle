<?php

/*
 * This file is part of the EasyBackupBundle.
 * All rights reserved by Maximilian Groß (www.maximiliangross.de).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\EasyBackupBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('easy_backup');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $arrayOfPathsToBackup = [
            '.env',
            'config/packages/local.yaml',
            'var/data/',
            'var/plugins/',
            'var/invoices/',
            'templates/invoice/',
            'var/export/',
            'templates/export/',
        ];

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('setting_mysqldump_command')
                    ->defaultValue('/usr/bin/mysqldump --user={user} --password={password} --host={host} --port={port} --single-transaction --force {database} --no-tablespaces')
                ->end()
                ->scalarNode('setting_mysql_restore_command')
                    ->defaultValue('/usr/bin/mysql --user={user} --password={password} --host={host} --port={port} {database} < {sql_file}')
                ->end()
                ->scalarNode('setting_backup_dir')
                    ->defaultValue('var/easy_backup/')
                ->end()
                ->scalarNode('setting_paths_to_backup')
                    ->defaultValue(implode(PHP_EOL, $arrayOfPathsToBackup))
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
