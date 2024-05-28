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
        $io = new SymfonyStyle($input, $output);
        $io->note('Data sync in progress.');
        $this->syncData($io);
        $io->success('Data synced successfully.');
        return Command::SUCCESS;
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

        while (($typoEntry = $typoData->fetchAssociative()) !== false) {
            $entry = $entryMapper->findOneBy(['typo_id' => $typoEntry['uid']]);
            if($entry) {
                if($entry->getSyncTime() != $typoEntry['tstamp']) {
                    if($typoEntry['deleted'] == 1) {
                        $this->deleteWordpressEntry($entry->getWordpressId());
                        $this->entityManager->remove($entry);
                        $io->text('Usunieto wpis ID: '. strval($entry->getWordpressId()).", ".$typoEntry['tstamp']);
                        continue;
                    }
                    $this->updateWordpressEntry($entry->getWordpressId(), $typoEntry['header'], $typoEntry['hidden'] ? 'private' : 'publish', $typoEntry['bodytext']);
                    $entry->setSyncTime($typoEntry['tstamp']);
                    $this->entityManager->persist($entry);
                    $io->text('Synchronizacja zmian we wpisie ID: '. strval($entry->getWordpressId()).", ".$typoEntry['tstamp']);
                }
                continue;
            }

            if($typoEntry['deleted'] == 1) {
                continue;
            }

            $wordpressEntry = $this->addWordpressEntry(empty($typoEntry['header']) ? "Brak tytułu" : $typoEntry['header'], $typoEntry['hidden'] ? 'private' : 'publish', $typoEntry['bodytext']);
            $newEntry = new EntryMapper();
            $newEntry->setTypoId($typoEntry['uid']);
            $newEntry->setWordpressId($wordpressEntry['id']);
            $newEntry->setSyncTime($typoEntry['tstamp']);
            $this->entityManager->persist($newEntry);
            $io->text("Synchronizacja nowego wpisu ID: ". strval($wordpressEntry['id']).", ".$typoEntry['tstamp']);
        }

        $this->entityManager->flush();
    }

    private function addWordpressEntry($title, $status, $content)
    {
        $data = [
            'title' => $this->translateEntry($title, "DE"),
            'status' => $status,
            'content' => $this->translateEntry($content, "DE");
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
            'title' => $this->translateEntry($title, "DE"),
            'status' => $status,
            'content' => $this->translateEntry($content, "DE"),
        ];

        $response = $this->client->request('PATCH', "http://192.168.0.7/wordpress/wp-json/wp/v2/posts/{$id}", [
            'json' => $data,
            'auth_basic' => [$this->params->get('wordpress.login'), $this->params->get('wordpress.password')]
        ]);

        return $response->toArray();
    }

    
    private function deleteWordpressEntry($id)
    {
        $response = $this->client->request('DELETE', "http://192.168.0.7/wordpress/wp-json/wp/v2/posts/{$id}", [
            'auth_basic' => [$this->params->get('wordpress.login'), $this->params->get('wordpress.password')]
        ]);
    }

    private function translateEntry($content, $targetLang) {
        $url = "https://api-free.deepl.com/v2/translate";
         
        try {
            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'DeepL-Auth-Key ' . $this->params->get('deepl.authkey'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'text' => [$content],
                    'target_lang' => $targetLang,
                ],
            ]);

            return $response->toArray()['translations'][0]['text'];
        }
        catch (GuzzleException $e) {
            return $content;
        }
    }
}