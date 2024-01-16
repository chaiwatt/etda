<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\ApiCore\ApiException;
use Google\Auth\CredentialsLoader;
use Google\Cloud\Dialogflow\V2\Intent;
use Google\Cloud\Dialogflow\V2\TextInput;
use Google\Cloud\Dialogflow\V2\QueryInput;
use Google\Cloud\Dialogflow\V2\AudioEncoding;
use Google\Cloud\Dialogflow\V2\IntentsClient;
use Google\Cloud\Dialogflow\V2\Intent\Message;
use Google\Cloud\Dialogflow\V2\SessionsClient;
use Google\Cloud\Dialogflow\V2\InputAudioConfig;
use Google\Cloud\Dialogflow\V2\Intent\Message\Text;
use Google\Cloud\Dialogflow\V2\Intent\TrainingPhrase;


use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\Dialogflow\V2\Intent\TrainingPhrase\Part;
use Google\Cloud\Dialogflow\V2\StreamingDetectIntentRequest;

class DialogflowController extends Controller
{
    public function __construct()
    {
        $filePath = public_path(env('DIALOGFLOW_CREDENTIALS_JSON'));
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $filePath);
    }
    
    public function createIntent()
    {
        $projectId = env('DIALOGFLOW_PROJECT_ID');
        $languageCode = env('DIALOGFLOW_LANGUAGE_CODE');

        $displayName = 'mybot';
        $trainingPhraseParts = [
                'วันนี้อากาศเป็นอย่างไร?',
                'วันนี้อุณหภูมิเท่าไหร่?',
                'วันนี้ฝนจะตกไหม?',
            ];
        $messageTexts = [
                'อากาศวันนี้แตชจ่มใส อุณหภูมิ 25 องศาเซลเซียส',
                'อุณหภูมิ 25 องศาเซลเซียส.',
                'วันนี้ไม่มีฝนตก',
            ];

        $intentsClient = new IntentsClient();
        $parent = $intentsClient->agentName($projectId,$languageCode);

        // prepare training phrases for intent
        $trainingPhrases = [];
        foreach ($trainingPhraseParts as $trainingPhrasePart) {
            $part = (new Part())->setText($trainingPhrasePart);
            $trainingPhrase = (new TrainingPhrase())->setParts([$part]);
            $trainingPhrases[] = $trainingPhrase;
        }

        // prepare messages for intent
        $text = (new Text())->setText($messageTexts);
        $message = (new Message())->setText($text);

        // prepare intent
        $weatherIntent = (new Intent())
            ->setDisplayName($displayName)
            ->setTrainingPhrases($trainingPhrases)
            ->setMessages([$message]);
               
        // create intent
        $response = $intentsClient->createIntent($parent, $weatherIntent);
        printf('Intent created: %s' . PHP_EOL, $response->getName());

        $intentsClient->close();
    }




    public function detectIntentTexts(Request $request)
    {
        $projectId = env('DIALOGFLOW_PROJECT_ID');
        $languageCode = env('DIALOGFLOW_LANGUAGE_CODE');
        $credentialsJson = env('DIALOGFLOW_CREDENTIALS_JSON');

        $texts = ['หาหนังสือ'];
        $sessionId = '';
        // new session
        $sessionsClient = new SessionsClient();
        $session = $sessionsClient->sessionName($projectId, $sessionId ?: uniqid());
        printf('Session path: %s', $session);

        // query for each string in array
        foreach ($texts as $text) {
            // create text input
            $textInput = new TextInput();
            $textInput->setText($text);
            $textInput->setLanguageCode($languageCode);

            // create query input
            $queryInput = new QueryInput();
            $queryInput->setText($textInput);

            // get response and relevant info
            $response = $sessionsClient->detectIntent($session, $queryInput);
            // dd($response);
            $queryResult = $response->getQueryResult();
            $queryText = $queryResult->getQueryText();
            $intent = $queryResult->getIntent();
            $displayName = $intent->getDisplayName();
            $confidence = $queryResult->getIntentDetectionConfidence();
            $fulfilmentText = $queryResult->getFulfillmentText();
            $queryParameters = $queryResult->getParameters();

            $parameters = [];
            if (null !== $queryParameters) {
                foreach ($queryParameters->getFields() as $name => $field) {
                    $parameters[$name] = $field->getStringValue();
                }
            }

            // output relevant info
            print(str_repeat("=", 20) . PHP_EOL);
            printf('Query text: %s' . PHP_EOL, $queryText);
            printf('Detected intent: %s (confidence: %f)' . PHP_EOL, $displayName,$confidence);
            print(PHP_EOL);
            printf('Fulfilment text: %s' . PHP_EOL, $fulfilmentText);
        }
        
        $sessionsClient->close();
    }

    function detectIntentAudio()
    {
        $projectId = '';
        $path ='';
        $sessionId ='';
        $languageCode = 'th-TH';
        // new session
        $sessionsClient = new SessionsClient();
        $session = $sessionsClient->sessionName($projectId, $sessionId ?: uniqid());
        printf('Session path: %s' . PHP_EOL, $session);

        // load audio file
        $inputAudio = file_get_contents($path);

        // hard coding audio_encoding and sample_rate_hertz for simplicity
        $audioConfig = new InputAudioConfig();
        $audioConfig->setAudioEncoding(AudioEncoding::AUDIO_ENCODING_LINEAR_16);
        $audioConfig->setLanguageCode($languageCode);
        $audioConfig->setSampleRateHertz(16000);

        // create query input
        $queryInput = new QueryInput();
        $queryInput->setAudioConfig($audioConfig);

        // get response and relevant info
        $response = $sessionsClient->detectIntent($session, $queryInput, ['inputAudio' => $inputAudio]);
        $queryResult = $response->getQueryResult();
        $queryText = $queryResult->getQueryText();
        $intent = $queryResult->getIntent();
        $displayName = $intent->getDisplayName();
        $confidence = $queryResult->getIntentDetectionConfidence();
        $fulfilmentText = $queryResult->getFulfillmentText();

        // output relevant info
        print(str_repeat("=", 20) . PHP_EOL);
        printf('Query text: %s' . PHP_EOL, $queryText);
        printf('Detected intent: %s (confidence: %f)' . PHP_EOL, $displayName,
            $confidence);
        print(PHP_EOL);
        printf('Fulfilment text: %s' . PHP_EOL, $fulfilmentText);

        $sessionsClient->close();
    }

    function detectIntentStreaming()
    {
        $projectId = '';
        $path ='';
        $sessionId ='';
        $languageCode = 'th-TH';
        // need to use gRPC
        if (!defined('Grpc\STATUS_OK')) {
            throw new \Exception('Install the grpc extension ' .
                '(pecl install grpc)');
        }

        // new session
        $sessionsClient = new SessionsClient();
        $session = $sessionsClient->sessionName($projectId, $sessionId ?: uniqid());
        printf('Session path: %s' . PHP_EOL, $session);

        // hard coding audio_encoding and sample_rate_hertz for simplicity
        $audioConfig = new InputAudioConfig();
        $audioConfig->setAudioEncoding(AudioEncoding::AUDIO_ENCODING_LINEAR_16);
        $audioConfig->setLanguageCode($languageCode);
        $audioConfig->setSampleRateHertz(16000);

        // create query input
        $queryInput = new QueryInput();
        $queryInput->setAudioConfig($audioConfig);

        // first request contains the configuration
        $request = new StreamingDetectIntentRequest();
        $request->setSession($session);
        $request->setQueryInput($queryInput);
        $requests = [$request];

        // we are going to read small chunks of audio data from
        // a local audio file. in practice, these chunks should
        // come from an audio input device.
        $audioStream = fopen($path, 'rb');
        while (true) {
            $chunk = stream_get_contents($audioStream, 4096);
            if (!$chunk) {
                break;
            }
            $request = new StreamingDetectIntentRequest();
            $request->setInputAudio($chunk);
            $requests[] = $request;
        }
        
        // intermediate transcript info
        print(PHP_EOL . str_repeat("=", 20) . PHP_EOL);
        $stream = $sessionsClient->streamingDetectIntent();
        foreach ($requests as $request) {
            $stream->write($request);
        }
        foreach ($stream->closeWriteAndReadAll() as $response) {
            $recognitionResult = $response->getRecognitionResult();
            if ($recognitionResult) {
                $transcript = $recognitionResult->getTranscript();
                printf('Intermediate transcript: %s' . PHP_EOL, $transcript);
            }
        }
        print(str_repeat("=", 20) . PHP_EOL);

        // get final response and relevant info
        $queryResult = $response->getQueryResult();
        $queryText = $queryResult->getQueryText();
        $intent = $queryResult->getIntent();
        $displayName = $intent->getDisplayName();
        $confidence = $queryResult->getIntentDetectionConfidence();
        $fulfilmentText = $queryResult->getFulfillmentText();

        // output relevant info
        printf('Query text: %s' . PHP_EOL, $queryText);
        printf('Detected intent: %s (confidence: %f)' . PHP_EOL, $displayName,
            $confidence);
        print(PHP_EOL);
        printf('Fulfilment text: %s' . PHP_EOL, $fulfilmentText);

        $sessionsClient->close();
    }

    public function intentDelete()
    {
        $projectId = env('DIALOGFLOW_PROJECT_ID');
        $languageCode = env('DIALOGFLOW_LANGUAGE_CODE');
        $credentialsJson = env('DIALOGFLOW_CREDENTIALS_JSON');
        $projectId ='';
        $intentId = '';
        $intentsClient = new IntentsClient();
        $intentName = $intentsClient->intentName($projectId, $intentId);

        $intentsClient->deleteIntent($intentName);
        printf('Intent deleted: %s' . PHP_EOL, $intentName);
        
        $intentsClient->close();
    }

    public function intentList()
    {

        $projectId = env('DIALOGFLOW_PROJECT_ID');
        $languageCode = env('DIALOGFLOW_LANGUAGE_CODE');
        $credentialsJson = env('DIALOGFLOW_CREDENTIALS_JSON');

        // Create a client.
        $intentsClient = new IntentsClient();
        // get intents
        $parent = $intentsClient->agentName($projectId);
        $intents = $intentsClient->listIntents($parent);

        foreach ($intents->iterateAllElements() as $intent) {
            // print relevant info
            print(str_repeat("=", 20) . PHP_EOL);
            printf('Intent name: %s' . PHP_EOL, $intent->getName());
            printf('Intent display name: %s' . PHP_EOL, $intent->getDisplayName());
            printf('Action: %s' . PHP_EOL, $intent->getAction());
            printf('Root followup intent: %s' . PHP_EOL,
                $intent->getRootFollowupIntentName());
            printf('Parent followup intent: %s' . PHP_EOL,
                $intent->getParentFollowupIntentName());
            print(PHP_EOL);

            print('Input contexts: ' . PHP_EOL);
            foreach ($intent->getInputContextNames() as $inputContextName) {
                printf("\t Name: %s" . PHP_EOL, $inputContextName);
            }

            print('Output contexts: ' . PHP_EOL);
            foreach ($intent->getOutputContexts() as $outputContext) {
                printf("\t Name: %s" . PHP_EOL, $outputContext->getName());
            }
        }
        $intentsClient->close();
    }

    public function updateIntent($faq){
        try{

            $trainingPhrasesInputs = [];
            $messageTexts = [];
            $intentName = '';
        
            $projectId = env('DIALOGFLOW_PROJECT_ID');
            $languageCode = env('DIALOGFLOW_LANGUAGE_CODE');
            $credentialsJson = env('DIALOGFLOW_CREDENTIALS_JSON');

            // Create a client.
            $intentsClient = new IntentsClient();
            $formattedName = IntentsClient::intentName($projectId, $intentName);
            $intent = $intentsClient->getIntent($formattedName);

            $trainingPhrases = [];

            $trainingPhrases = [];
            foreach ($trainingPhrasesInputs as $phrase) {
                $part = new Part();
                $part->setText($phrase);
                $trainingPhrase = new TrainingPhrase();
                $trainingPhrase->setParts([$part]);
                $trainingPhrases[] = $trainingPhrase;
            }

            $responseMessages = [];
            foreach ($messageTexts as $response) {
                $text = new Text();
                $text->setText($response);
                $message = new Message();
                $message->setText($text);
                $responseMessages[] = $message;
            }

            $intent->setMessages($responseMessages);

            $response = $intentsClient->updateIntent($intent);
        } catch (ApiException $e) {
            return false;
            exit();
        } 
    }
    
}

