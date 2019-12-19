<?php

/*
 * This file is part of the Kimai EasyBackupBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\EasyBackupBundle\EventSubscriber;

use App\Event\SystemConfigurationEvent;
use App\Form\Model\Configuration;
use App\Form\Model\SystemConfiguration as SystemConfigurationModel;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SystemConfigurationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            SystemConfigurationEvent::class => ['onSystemConfiguration', 100],
        ];
    }

    public function onSystemConfiguration(SystemConfigurationEvent $event)
    {
        $event->addConfiguration((new SystemConfigurationModel())
                ->setSection('easy_backup_config')
                ->setConfiguration([
                    (new Configuration())
                        ->setName('easy_backup.setting_mysqldump_path')
                        ->setLabel('easy_backup.setting_mysqldump_path')
                        ->setTranslationDomain('system-configuration')
                        ->setRequired(false)
                        ->setType(TextType::class),
                    (new Configuration())
                        ->setName('easy_backup.setting_backup_dir')
                        ->setLabel('easy_backup.setting_backup_dir')
                        ->setTranslationDomain('system-configuration')
                        ->setRequired(false)
                        ->setType(TextType::class),
                ])
        );
    }
}
