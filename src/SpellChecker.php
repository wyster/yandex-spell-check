<?php declare(strict_types=1);

namespace Twin\Support\Yandex;

use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;
use GuzzleHttp\Client;

class SpellChecker
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Corrects spelling errors in the given message.
     *
     * @param string $message
     * @return string
     */
    public function fix(string $message): string
    {
        $correctionData = $this->getCorrectionInfo($message);

        $offset = 0;
        $correctedMessage = $message;
        foreach ($correctionData as $item) {
            $message = $correctedMessage;
            $fragment = $item['s'][0] ?? '';

            $correctedMessage = mb_substr($message, 0, $item['pos'] + $offset);
            $correctedMessage .= $fragment;
            $correctedMessage .= mb_substr($message, $item['pos'] + $item['len'] + $offset);

            $offset += mb_strlen($fragment) - $item['len'];
        }

        return $correctedMessage;
    }

    private function getCorrectionInfo(string $message): array
    {
        try {
            $response = $this->client
                ->get(
                    "https://speller.yandex.net/services/spellservice.json/checkText?text={$message}",
                    [
                        'connect_timeout' => 5,
                        'timeout' => 10
                    ]
                );

            $result = json_decode((string)$response->getBody(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid json');
            }

            if (!$this->isValidSchema($result)) {
                throw new Exception('Invalid schema');
            }
        } catch (Throwable $e) {
            Log::error('YANDEX SPELLER SERVICE FAILED', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);

            return [];
        }

        return $result;
    }

    private function isValidSchema(array $result): bool
    {
        $schema = ['pos', 'len', 's'];
        foreach ($schema as $key) {
            foreach ($result as $item) {
                if (!array_key_exists($key, $item)) {
                    return false;
                }
            }
        }

        return true;
    }
}
