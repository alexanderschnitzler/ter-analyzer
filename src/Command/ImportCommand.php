<?php declare(strict_types = 1);

namespace Schnitzler\Command;

use Schnitzler\Bootstrap;
use Schnitzler\Downloader\ExtensionDownloader;
use Schnitzler\Repository\ExtensionRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ImportCommand
 * @package Schnitzler\Command
 */
class ImportCommand extends Command
{

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('import')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force redownload of extensions.xml')
            ->setDescription('Import all extensions from TER')
        ;
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = new ExtensionRepository();

        $progress = new ProgressBar($output, 100);
        foreach ($repository->downloadExtensionFile($input->getOption('force')) as $value)
        {
            $progress->setProgress($value);
        }
        $progress->finish();

        $rows = Bootstrap::$db->createQueryBuilder()
            ->select('v.uid, e.name as extension, v.name as version')
            ->from('versions', 'v')
            ->join('v', 'extensions', 'e', 'v.extension = e.uid')
            ->where('v.downloaded = 0')
            ->orderBy('extension, version')
            ->execute()
            ->fetchAll()
        ;

        $statement = Bootstrap::$db->prepare(
            Bootstrap::$db->createQueryBuilder()
                ->update('versions', 'v')
                ->set('downloaded', 1)
                ->where('uid = :uid')->getSQL()
        );

        foreach ($rows as $row) {
            $uid = $row['uid'];
            $extensions = $row['extension'];
            $version = $row['version'];

            $extensionDirectory = APP_TMP . $extensions . DIRECTORY_SEPARATOR;
            $versionDirectory = $extensionDirectory . $version . DIRECTORY_SEPARATOR;

            if (!file_exists($extensionDirectory)) {
                mkdir($extensionDirectory);
            }

            if (!file_exists($versionDirectory)) {
                mkdir($versionDirectory);
            }

            if (count(scandir($versionDirectory)) > 2) {
                continue;
            }

            $progressBar = new ProgressBar($output, 100);
            try {
                $output->writeln('Downloading ' . $row['extension'] . ' ' . $row['version']);

                $extensionDownloader = new ExtensionDownloader($row['extension'], $row['version']);
                foreach ($extensionDownloader->download($versionDirectory) as $progress) {
                    $progressBar->setProgress($progress);
                }

                $statement->bindValue('uid', $uid);
                $statement->execute();
            } catch (\RuntimeException $e) {
                $output->writeln('');
                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                Bootstrap::$db->executeQuery(
                    Bootstrap::$db->createQueryBuilder()
                    ->update('versions', 'v')
                    ->set('error', Bootstrap::$db->quote($e->getMessage()))
                    ->where('uid = :uid')
                    ->getSQL(),
                    [
                        'uid' => $uid
                    ]
                );
            } finally {
                $output->writeln('');
            }
        }
    }

}
