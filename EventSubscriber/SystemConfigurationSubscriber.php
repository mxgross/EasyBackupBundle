<?php

/*
 * This file is part of the EasyBackupBundle for Kimai 2.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\EasyBackupBundle\EventSubscriber;

use App\Event\SystemConfigurationEvent;
use App\Form\Model\Configuration;
use App\Form\Model\SystemConfiguration as SystemConfigurationModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class SystemConfigurationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            SystemConfigurationEvent::class => ['onSystemConfiguration', 100],
        ];
    }

    public function onSystemConfiguration(SystemConfigurationEvent $event): void
    {
        $event->addConfiguration(
            (new SystemConfigurationModel())
                ->setSection('easy_backup_config')
                ->setConfiguration([
                    (new Configuration())
                        ->setName('easy_backup.setting_mysqldump_command')
                        ->setLabel('easy_backup.setting_mysqldump_command')
                        ->setTranslationDomain('system-configuration')
                        ->setRequired(false)
                        ->setType(TextType::class),
                    (new Configuration())
                        ->setName('easy_backup.setting_mysql_restore_command')
                        ->setLabel('easy_backup.setting_mysql_restore_command')
                        ->setTranslationDomain('system-configuration')
                        ->setRequired(false)
                        ->setType(TextType::class),
                    (new Configuration())
                        ->setName('easy_backup.setting_backup_dir')
                        ->setLabel('easy_backup.setting_backup_dir')
                        ->setTranslationDomain('system-configuration')
                        ->setRequired(false)
                        ->setType(TextType::class),
                    (new Configuration())
                        ->setName('easy_backup.setting_paths_to_backup')
                        ->setLabel('easy_backup.setting_paths_to_backup')
                        ->setTranslationDomain('system-configuration')
                        ->setRequired(false)
                        ->setType(TextareaType::class),
                ])
        );
    }
}
