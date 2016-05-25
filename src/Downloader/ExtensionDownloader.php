<?php declare(strict_types=1);

namespace Schnitzler\Downloader;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use VIPSoft\Unzip\Unzip;

/**
 * Class ExtensionDownloader
 * @package Schnitzler\Downloader
 */
class ExtensionDownloader {

    const BASEURL = 'https://typo3.org/extensions/repository/download/%s/%s/zip/';

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var string
     */
    protected $version;

    /**
     * @param string $extension
     * @param string $version
     */
    public function __construct(string $extension, string $version)
    {
        $this->extension = $extension;
        $this->version = $version;
    }

    /**
     * @param string $destination
     * @return \Generator
     * @throws \Exception
     */
    public function download(string $destination)
    {
        if (!is_dir($destination)) {
            throw new \InvalidArgumentException('Destination folder "' . $destination . '" does not exist.');
        }

        $tmpFile = APP_TMP . $this->extension . '_' . $this->version . '.zip';

        try {
            $client = new Client();
            $stream = \GuzzleHttp\Psr7\stream_for(fopen($tmpFile, 'w'));
            $response = $client->request(
                'GET',
                sprintf(static::BASEURL, $this->extension, $this->version),
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
                $percentage = floor($bytesRead / $contentLengthHeader * 100);

                yield ($percentage <= 100 ? $percentage : 100);
            }

            $unzipper = new Unzip();
            $unzipper->extract($tmpFile, $destination);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        } finally {
            yield 100;
            unlink($tmpFile);
        }
    }
}
