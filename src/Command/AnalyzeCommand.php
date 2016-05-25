<?php declare(strict_types = 1);

namespace Schnitzler\Command;

use Schnitzler\Bootstrap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ImportCommand
 * @package Schnitzler\Command
 */
class AnalyzeCommand extends Command
{

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('analyze')
            ->addArgument('runner', InputArgument::REQUIRED, 'Path to the sonarqube runner bin')
            ->setDescription('Analyzes all extensions')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $cmd = $input->getArgument('runner');

        $rows = Bootstrap::$db->createQueryBuilder()
            ->select('v.uid, e.name as extension, v.name as version')
            ->from('versions', 'v')
            ->join('v', 'extensions', 'e', 'v.extension = e.uid')
            ->where('v.analyzed = 0')
            ->orderBy('extension, version')
            ->execute()
            ->fetchAll()
        ;

        $groupedRows = [];
        foreach ($rows as $row) {
            $groupedRows[$row['extension']][$row['uid']] = $row['version'];
        }

        $statement = Bootstrap::$db->prepare(
            Bootstrap::$db->createQueryBuilder()
                ->update('versions', 'v')
                ->set('analyzed', 1)
                ->where('uid = :uid')
                ->getSQL()
        );

        foreach ($groupedRows as $extension => $versions) {
            asort($versions, SORT_NATURAL);

            foreach ($versions as $uid => $version) {
                $sonarProjectPropertiesFiles = APP . 'sonar-project.properties';
                if (file_exists($sonarProjectPropertiesFiles)) {
                    unlink($sonarProjectPropertiesFiles);
                }

                file_put_contents(
                    $sonarProjectPropertiesFiles,
                    implode(
                        PHP_EOL,
                        [
                            'sonar.projectKey=' . $extension,
                            'sonar.projectName=' . $extension,
                            'sonar.projectVersion=' . $version,
                            'sonar.projectBaseDir=' . APP_TMP . $extension . DIRECTORY_SEPARATOR . $version,
                            'sonar.sources=.',
                        ]
                    )
                );

                $stdout = [];

                $output->writeln('Analyzing ' . $extension . ' ' . $version);
                exec(escapeshellcmd($cmd), $stdout, $code);

                if ((int)$code === 0) {
                    $statement->bindValue('uid', $uid);
                    $statement->execute();
                }
            }
        }
    }

}
