<?php

/*
 * This file is part of the EasyBackupBundle.
 * All rights reserved by Maximilian Groß (www.maximiliangross.de).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\EasyBackupBundle\EventSubscriber;

use App\Event\SystemConfigurationEvent;
use App\Form\Model\Configuration;
use App\Form\Model\SystemConfiguration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;

class SystemConfigurationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigurationEvent::class => ['onSystemConfiguration', 100],
        ];
    }

    public function onSystemConfiguration(SystemConfigurationEvent $event): void
    {
        $event->addConfiguration(
            (new SystemConfiguration('easy_backup_config'))
            ->setConfiguration([
                (new Configuration('easy_backup.setting_mysqldump_command'))
                    ->setTranslationDomain('system-configuration')
                    ->setRequired(false)
                    ->setType(TextType::class),
                (new Configuration('easy_backup.setting_mysql_restore_command'))
                    ->setTranslationDomain('system-configuration')
                    ->setRequired(false)
                    ->setType(TextType::class),
                (new Configuration('easy_backup.setting_backup_dir'))
                    ->setTranslationDomain('system-configuration')
                    ->setRequired(false)
                    ->setType(TextType::class),
                (new Configuration('easy_backup.setting_paths_to_backup'))
                    ->setTranslationDomain('system-configuration')
                    ->setRequired(false)
                    ->setType(TextareaType::class),
                (new Configuration('easy_backup.setting_backup_amount_max'))
                    ->setTranslationDomain('system-configuration')
                    ->setRequired(false)
                    ->setConstraints([new NotNull(), new GreaterThanOrEqual(['value' => -1])])
                    ->setType(IntegerType::class)
                    ->setValue(-1)
                    ->setOptions(['help' => 'help.easy_backup.setting_backup_amount_max']),
            ])
        );
    }
}
