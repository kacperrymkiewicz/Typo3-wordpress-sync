<?php 
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'sync:typo3')]
class SyncCommand extends Command
{
    public function __construct(ManagerRegistry $doctrine)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... put here the code to create the user

        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        $io = new SymfonyStyle($input, $output);
        $io->note('Data synced successfully.');
        $this->fetchTypo3Data();
        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');
        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }

    private function fetchTypo3Data()
    {
        $sql = 'SELECT * FROM tt_content';
        
        $connection = $this->doctrine->getConnection('typo3');
        $statement = $connection->query($sql);


        // Handle results, for example, display them
        while (($row = $statement->fetchAssociative()) !== false) {
            echo $row['bodytext'];
        }

    }
}