<?php 
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\EntryMapper;
use App\Repository\EntryMapperRepository;


// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'sync:typo3')]
class SyncCommand extends Command
{
    public function __construct(ManagerRegistry $doctrine, EntityManagerInterface $entityManager, HttpClientInterface $client, ParameterBagInterface $params)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
        $this->client = $client;
        $this->params = $params;
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... put here the code to create the user

        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        $io = new SymfonyStyle($input, $output);
        $io->note('Data sync in progress.');
        $this->syncData($io);
        $io->success('Data synced successfully.');
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
        return $connection->query($sql);
    }

    private function syncData(SymfonyStyle $io)
    {
        $typoData = $this->fetchTypo3Data();
        $entryMapper = $this->entityManager->getRepository(EntryMapper::class);

        //$entries = $entryMapper->findAll();

        while (($row = $typoData->fetchAssociative()) !== false) {
            $entry = $entryMapper->findOneBy(['typo_id' => $row['uid']]);
            if($entry) {
                if($entry->getSyncTime() != $row['tstamp']) {
                    $this->updateWordpressEntry($entry->getWordpressId(), $row['header'], 'publish', $row['bodytext']);
                    $entry->setSyncTime($row['tstamp']);
                    $this->entityManager->persist($entry);
                    $io->text('Synchronizacja zmian we wpisie ID: '. strval($entry->getWordpressId()));
                }
 
                return;            
            }

            $wordpressEntry = $this->addWordpressEntry(empty($row['header']) ? "Brak tytułu" : $row['header'], 'publish', $row['bodytext']);
            $newEntry = new EntryMapper();
            $newEntry->setTypoId($row['uid']);
            $newEntry->setWordpressId($wordpressEntry['id']);
            $newEntry->setSyncTime($row['tstamp']);
            $this->entityManager->persist($newEntry);
            $io->text("Synchronizacja nowego wpisu ID: ". strval($wordpressEntry['id']));
        }

        $this->entityManager->flush();
    }

    private function addWordpressEntry($title, $status, $content)
    {
        $data = [
            'title' => $title,
            'status' => $status,
            'content' => $content
        ];

        $response = $this->client->request('POST', "http://192.168.0.7/wordpress/wp-json/wp/v2/posts", [
            'json' => $data,
            'auth_basic' => [$this->params->get('wordpress.login'), $this->params->get('wordpress.password')]
        ]);

        return $response->toArray();
    }

    private function updateWordpressEntry($id, $title, $status, $content)
    {
        $data = [
            'title' => $title,
            'status' => $status,
            'content' => $content
        ];

        $response = $this->client->request('PATCH', "http://192.168.0.7/wordpress/wp-json/wp/v2/posts/{$id}", [
            'json' => $data,
            'auth_basic' => [$this->params->get('wordpress.login'), $this->params->get('wordpress.password')]
        ]);

        return $response->toArray();
    }
}