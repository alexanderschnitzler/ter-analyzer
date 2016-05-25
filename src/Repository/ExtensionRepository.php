<?php declare(strict_types = 1);

namespace Schnitzler\Repository;

use GuzzleHttp\Client;
use Schnitzler\Bootstrap;

/**
 * Class ExtensionRepository
 * @package Schnitzler\Repository
 */
class ExtensionRepository
{

    const URL = 'https://typo3.org/fileadmin/ter/extensions.xml.gz';

    /**
     * @param bool $forceDownload
     * @return \Generator
     */
    public function downloadExtensionFile(bool $forceDownload = false)
    {
        $gzFileName = APP_TMP . 'extensions.xml.gz';
        $xmlFileName = substr($gzFileName, 0, -3);

        if ($forceDownload || !file_exists($gzFileName)) {
            $client = new Client();
            $stream = \GuzzleHttp\Psr7\stream_for(fopen($gzFileName, 'w'));
            $response = $client->request(
                'GET',
                static::URL,
                [
                    'stream' => true,
                    'verify' => false,
                    'sink' => $stream
                ]
            );

            $contentLengthHeader = $response->getHeader('Content-Length');
            if (is_array($contentLengthHeader)) {
                $contentLengthHeader = (int)reset($contentLengthHeader);
            } else {
                $contentLengthHeader = 1024;
            }

            $bytesToRead = 1024;
            $bytesRead = 0;
            while (!$response->getBody()->eof()) {
                $bytesRead += $bytesToRead;
                $stream->write($response->getBody()->read($bytesToRead));
                yield $bytesRead / $contentLengthHeader * 100;
            }
        }

        /*
         * Unzip extensions.xml.gz if not exists
         */
        if (!file_exists($xmlFileName)) {
            $inputStream = \GuzzleHttp\Psr7\stream_for(gzopen($gzFileName, 'rb'));
            $outputStream = \GuzzleHttp\Psr7\stream_for(fopen($xmlFileName, 'wb'));

            while (!$inputStream->eof()) {
                $outputStream->write($inputStream->read(4096));
            }

            unset($inputStream, $outputStream);
        }

        /*
         *
         */
        $extensions = [];
        $inputStream = \GuzzleHttp\Psr7\stream_for(fopen($xmlFileName, 'r'));

        foreach (new \SimpleXMLElement($inputStream->getContents()) as $extension) {
            /** @var $extension \SimpleXMLElement */
            $extensionVersions = [];

            foreach ($extension->version as $version) {
                $versionString = (string)$version['version'];
                if (!preg_match('/^[\d]+\.[\d]+\.[\d]+$/', $versionString)) {
                    continue;
                }

                $extensionVersions[] = $versionString;
            }

            sort($extensionVersions, SORT_NATURAL);
            $extensions[(string)$extension['extensionkey']] = $extensionVersions;
        }
        unset($extension);

        $qb = Bootstrap::$db->createQueryBuilder();
        $sql = 'SELECT * FROM extensions WHERE name = :name';
        $statement = Bootstrap::$db->prepare($sql);

        foreach ($extensions as $key => $versions) {
            $statement->bindValue('name', $key);
            $statement->execute();

            if ($statement->rowCount() === 0) {
                $guid = $this->getGuid();
                Bootstrap::$db->insert(
                    'extensions',
                    [
                        'uid' => $guid,
                        'name' => $key
                    ]
                );

                foreach ($versions as $version) {
                    Bootstrap::$db->insert(
                        'versions',
                        [
                            'uid' => $this->getGuid(),
                            'name' => $version,
                            'extension' => $guid
                        ]
                    );
                }

                $count = $qb->select('*')
                    ->from('versions')
                    ->where('extension = ' . Bootstrap::$db->quote($guid))
                    ->execute()
                    ->rowCount();

                Bootstrap::$db->update(
                    'extensions',
                    [
                        'versions' => $count,
                    ],
                    [
                        'uid' => $guid
                    ]
                );
            }
        }
    }

    /**
     * @return bool|string
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getGuid()
    {
        return Bootstrap::$db->executeQuery('SELECT ' . Bootstrap::$db->getDriver()->getDatabasePlatform()->getGuidExpression())->fetchColumn();
    }

}
