<?php 

namespace KimaiPlugin\EasyBackupBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpClient\HttpClient;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'EasyBackup:backup',
            description: 'Creates a new backup.',
            hidden: false,
            )]
class EasyBackupBackupCommand extends Command
{
    protected static $defaultName = 'EasyBackup:backup';
    // the command description shown when running "php bin/console list"
    protected static $defaultDescription = 'Creates a new backup.';

    private $router;

    public function __construct(RouterInterface $router, TokenStorageInterface $tokenStorage)
    {
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;

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
        $url = $this->router->generate('create_backup');

        $token = new UsernamePasswordToken('susan_super', 'kitten', 'main', ['ROLE_ADMIN']); // TODO: FIX ME
        //$this->tokenStorage->setToken($token);

        $client = HttpClient::create();
        $response = $client->request('GET', 'http://localhost/kimai2/public/index.php' . $url); // TODO: FIX ME

        $output->writeln($response->getContent());
        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        return 0;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }
}