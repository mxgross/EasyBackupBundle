<?php 

namespace KimaiPlugin\EasyBackupBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'EasyBackup:backup',
            description: 'Creates a new backup.',
            hidden: false,
            )]
class EasyBackupBackupCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'EasyBackup:backup';
    // the command description shown when running "php bin/console list"
    protected static $defaultDescription = 'Creates a new backup.';

    private $router;

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp('This command allows you to create a new backup e.g. via cronjob.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $container = $this->getContainer();
        $controller = $container->get('KimaiPlugin\EasyBackupBundle\Controller\EasyBackupController');
        $result = $controller->createBackupAction();

        //$output->writeln($result);
        $output->writeln($result->getStatusCode());


        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }
}