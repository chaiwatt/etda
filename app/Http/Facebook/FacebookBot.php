<?php

namespace App\Line;

use Exception;

use App\Models\Intent;
// use GuzzleHttp\Client;
use GuzzleHttp\Client as Guzzle_Client;
use FFMpeg\Format\Audio\Wav;
use Google\ApiCore\ApiException;
use App\Models\WebhookEventHistory;
use App\Services\CredentialsHandler;
use Google\Cloud\Dialogflow\V2\TextInput;
use Google\Cloud\Dialogflow\V2\QueryInput;
use GuzzleHttp\Exception\RequestException;
use Google\Cloud\Dialogflow\V2\AudioEncoding;
use Google\Cloud\Dialogflow\V2\SessionsClient;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Google\Cloud\Dialogflow\V2\InputAudioConfig;

class LineBot
{
    private $credentialsHandler;
    private $lineChannelSecret;
    private $lineAccessToken;
    private $headers;
    private $lineApiUrl;

    public function __construct(CredentialsHandler $credentialsHandler)
    {
        $filePath = public_path(env('DIALOGFLOW_CREDENTIALS_JSON'));
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $filePath);

        $this->credentialsHandler = $credentialsHandler;
        $this->lineChannelSecret = $this->credentialsHandler->getLineChannelSecret();
        $this->lineAccessToken = $this->credentialsHandler->getLineChannelAccessToken();
        $this->lineApiUrl = $this->credentialsHandler->getLineApiUrl();
        $this->headers = [
            'Authorization' => 'Bearer ' . $this->lineAccessToken,
            'Content-Type' => 'application/json',
        ];
    }

    public function sendReply($request,$messages)
    {
        
    }



  



}
