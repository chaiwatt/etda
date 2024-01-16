<?php

namespace App\Http\Controllers;

use Exception;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Google\ApiCore\ApiException;
use Google\Cloud\Dialogflow\V2\Intent;
use Google\Cloud\Dialogflow\V2\TextInput;
use Google\Cloud\Dialogflow\V2\EntityType;
use Google\Cloud\Dialogflow\V2\QueryInput;
use Google\Cloud\Dialogflow\V2\QueryResult;
use Google\Cloud\Dialogflow\V2\AudioEncoding;
use Google\Cloud\Dialogflow\V2\IntentsClient;
use Google\Cloud\Dialogflow\V2\ContextsClient;
use Google\Cloud\Dialogflow\V2\Intent\Message;
use Google\Cloud\Dialogflow\V2\SessionsClient;
use Google\Cloud\Dialogflow\V2\EntityType\Kind;
use Google\Cloud\Dialogflow\V2\InputAudioConfig;
use Google\Cloud\Dialogflow\V2\Intent\Parameter;
use Google\Cloud\Dialogflow\V2\EntityType\Entity;
use Google\Cloud\Dialogflow\V2\EntityTypesClient;
use Google\Cloud\Dialogflow\V2\Intent\Message\Text;
use Google\Cloud\Dialogflow\V2\Intent\TrainingPhrase;
use Google\Cloud\Dialogflow\V2\SessionEntityTypesClient;
use Google\Cloud\Dialogflow\V2\Intent\FollowupIntentInfo;
use Google\Cloud\Dialogflow\V2\Intent\TrainingPhrase\Part;

class DialogflowIntentController extends Controller
{
    public function __construct()
    {
        $filePath = public_path(env('DIALOGFLOW_CREDENTIALS_JSON'));
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $filePath);
    }

    public function create(Request $request)
    {
        $displayName = 'mycoolbot';
        $trainingPhraseParts = [
                'วันนี้อากาศเป็นอย่างไร?',
                'วันนี้อุณหภูมิเท่าไหร่?',
                'วันนี้ฝนจะตกไหม?',
            ];
        $messageTexts = [
                'อากาศวันนี้แจ่มใส อุณหภูมิ 25 องศาเซลเซียส',
                'อุณหภูมิ 25 องศาเซลเซียส.',
                'วันนี้ไม่มีฝนตก',
            ];
        $response = $this->newIntent($displayName,$trainingPhraseParts,$messageTexts);
        return $response;
    }

    public function detectIntentText(Request $request)
    {
        $projectId = env('DIALOGFLOW_PROJECT_ID');
        $languageCode = env('DIALOGFLOW_LANGUAGE_CODE');
        
        $text = $request->text;// 'วันนี้ฝนจะร้อนไหม';
        $sessionId = 'session-124';
        // new session
        $sessionsClient = new SessionsClient();
        $session = $sessionsClient->sessionName($projectId, $sessionId ?: uniqid());

        $textInput = new TextInput();
        $textInput->setText($text);
        $textInput->setLanguageCode($languageCode);

        // create query input
        $queryInput = new QueryInput();
        $queryInput->setText($textInput);

        try {
            $response = $sessionsClient->detectIntent($session, $queryInput); 

            $queryResult = $response->getQueryResult();
            $queryText = $queryResult->getQueryText();
            $intent = $queryResult->getIntent();
            $displayName = $intent->getDisplayName();
            $confidence = $queryResult->getIntentDetectionConfidence();
            $fulfilmentText = $queryResult->getFulfillmentText();
            $lgCode = $queryResult->getLanguageCode();

            $queryParameters = $queryResult->getParameters();
            $outputContext = $queryResult->getOutputContexts();

            // dd($queryResult->getOutputContexts());


            $parameters = [];
            if (null !== $queryParameters) {
                foreach ($queryParameters->getFields() as $name => $field) {
                    $parameters[$name] = $field->getStringValue();
                }
            }

            $result = [
                "queryText" => $queryText,
                "intentBot" => $displayName,
                "confidence" => $confidence,
                "fulfilmentText" => $fulfilmentText,
                "parameters" => $parameters,
                "languageCode" => $lgCode,
                "outputContexts" => $this->getContexts($queryResult)
            ];
            
            $sessionsClient->close($session);
            return response()->json([
                        'message' => $result,
                    ], 200);
        } catch (ApiException $e) {
            $errorDetails = json_decode($e->getMessage(), true);

            if (isset($errorDetails['message'])) {
                return response()->json([
                    'error' => $errorDetails['message'],
                ], 400); // You can adjust the status code accordingly
            } else {
                return response()->json([
                    'error' => 'An unexpected error occurred: ' . $e->getMessage(),
                ], 500);
            }
        } catch (Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
   
    }


    private function getContexts(QueryResult $queryResult): array
    {
        $contexts = [];
        foreach ($queryResult->getOutputContexts() as $context) {
            // $contextParams = [];
            // $parameters = $context->getParameters();
            // if (null !== $parameters) {
            //     foreach ($parameters->getFields() as $name => $field) {
            //         $contextParams[$name] = $field->getStringValue();
            //     }
            // }

            $contexts[] = [
                'name' => substr(strrchr($context->getName(), '/'), 1),
                'parameters' => $this->getParameters($queryResult),
                'lifespan' => $context->getLifespanCount(),
            ];
        }
        return $contexts;
    }

    // private function getParameters(QueryResult $queryResult): array
    // {
    //     $parameters = [];
    //     $queryParameters = $queryResult->getParameters();
    //     if (null !== $queryParameters) {
    //         foreach ($queryParameters->getFields() as $name => $field) {
    //             if (null !== $field->getListValue()) {
    //                 $sub_problem = array();
    //                 foreach ($field->getListValue()->getValues() as $list_name => $list_field) {
    //                     array_push($sub_problem, $list_field->getStringValue());
    //                 }
    //                 $parameters[$name] = $sub_problem;
    //             } else {
    //                 $parameters[$name] = $field->getStringValue();
    //             }
    //         }
    //     }

    //     return $parameters;
    // }

    private function getParameters(QueryResult $queryResult): array
    {
        $parameters = [];
        $queryParameters = $queryResult->getParameters();
        if (null !== $queryParameters) {
            foreach ($queryParameters->getFields() as $name => $field) {
                if (null !== $field->getListValue()) {
                    $sub_problem = [];
                    foreach ($field->getListValue()->getValues() as $list_name => $list_field) {
                        // ตรวจสอบว่าเป็นตัวเลขหรือไม่
                        if ($list_field->hasNumberValue()) {
                            array_push($sub_problem, $list_field->getNumberValue());
                        } else {
                            array_push($sub_problem, $list_field->getStringValue());
                        }
                    }
                    $parameters[$name] = $sub_problem;
                } else {
                    // ตรวจสอบว่าเป็นตัวเลขหรือไม่
                    if ($field->hasNumberValue()) {
                        $parameters[$name] = $field->getNumberValue();
                    } else {
                        $parameters[$name] = $field->getStringValue();
                    }
                }
            }
        }

        return $parameters;
    }

    function session_entity_type_list($projectId, $sessionId)
    {
        $sessionEntityTypesClient = new SessionEntityTypesClient();
        $parent = $sessionEntityTypesClient->sessionName($projectId, $sessionId);
        $sessionEntityTypes = $sessionEntityTypesClient->listSessionEntityTypes($parent);
        print('Session entity types:' . PHP_EOL);
        foreach ($sessionEntityTypes->iterateAllElements() as $sessionEntityType) {
            printf('Session entity type name: %s' . PHP_EOL, $sessionEntityType->getName());
            printf('Number of entities: %d' . PHP_EOL, count($sessionEntityType->getEntities()));
        }
        $sessionEntityTypesClient->close();
    }
        
    function context_list($session)
    {
        // get contexts
        $contextsClient = new ContextsClient();
        // $session = $contextsClient->sessionName($projectId, $sessionId);
        // dd($parent);
        $contexts = $contextsClient->listContexts($session);
        

        printf('Contexts for session %s' . PHP_EOL, $session);
        foreach ($contexts->iterateAllElements() as $context) {
             $contextParams = [];
            // print relevant info
            printf('Context name: %s' . PHP_EOL, $context->getName());
            printf('Lifespan count: %d' . PHP_EOL, $context->getLifespanCount());

            $parameters = $context->getParameters();
            if (null !== $parameters) {
                dd($parameters);
                foreach ($parameters->getFields() as $name => $field) {
                    $contextParams[$name] = $field->getStringValue();
                    // dd($field->getListValue());
                }
            }
           
          printf('Lifespan count: %d' . '<br>', $contextParams);


            
        }

        $contextsClient->close();

        
    }
    public function newFollowerIntentWithEntity($displayName, $trainingPhraseParts, $messageTexts) {
        // Set your Dialogflow project ID and language code
        $projectId = env('DIALOGFLOW_PROJECT_ID');
        $languageCode = env('DIALOGFLOW_LANGUAGE_CODE');

        // Set the display name for the follow-up intent
        $followupIntentDisplayName = 'FollowupIntent';

        // Set the training phrases for the follow-up intent
        $followupTrainingPhraseParts = ['This is a follow-up', 'What about this', 'Anything else'];

        // Set the parent intent display name for the follow-up intent
        $parentIntentDisplayName = 'MyFavoriteFruit';

        // Create the entity type
        $entityType = (new EntityType())
            ->setDisplayName('Email')
            ->setKind(Kind::KIND_MAP);

        // Create array to store Entities
        $emailEntities = [];
        $emailEntity = (new Entity())->setValue('email')->setSynonyms(['email']);
        $emailEntities[] = $emailEntity;

        // Set the array of Entities in the EntityType
        $entityType->setEntities($emailEntities);

        // Create EntityTypesClient
        $entityTypesClient = new EntityTypesClient();

        // Set the parent for EntityType
        $entityTypeParent = $entityTypesClient->agentName($projectId, $languageCode);

        // Create the entity type
        $entityTypeResponse = $entityTypesClient->createEntityType($entityTypeParent, $entityType);

        // Close the EntityTypesClient
        $entityTypesClient->close();

        // Create the follow-up Intent
        $followupIntent = (new Intent())
            ->setDisplayName($followupIntentDisplayName)
            ->setTrainingPhrases([])
            ->setMessages([
                (new Message())
                    ->setText((new Text())->setText(['Yes, I have more to say.'])) // Set the response message
            ]);

        // Create array to store TrainingPhrases
        $followupTrainingPhrases = [];

        // Use foreach loop to add TrainingPhrasePart from $followupTrainingPhraseParts
        foreach ($followupTrainingPhraseParts as $followupTrainingPhrasePart) {
            $part = (new Part())->setText($followupTrainingPhrasePart . ' {' . 'Email' . '}');
            $part->setEntityType('Email');  // Set entity type ID in the loop
            $followupTrainingPhrases[] = (new TrainingPhrase())->setParts([$part]);
        }

        // Set the array of TrainingPhrases in the follow-up Intent
        $followupIntent->setTrainingPhrases($followupTrainingPhrases);

        // Set the parent intent display name for the follow-up intent
        $followupIntentInfo = (new FollowupIntentInfo())
            ->setFollowupIntentName($parentIntentDisplayName);

        // Set the follow-up Intent info in the follow-up Intent
        $followupIntent->setFollowupIntentInfo($followupIntentInfo);

        // Create IntentsClient
        $intentsClient = new IntentsClient();

        // Set the parent for the Intent
        $parentIntentParent = $intentsClient->agentName($projectId, $languageCode);

        // Create the follow-up intent
        $response = $intentsClient->createIntent($parentIntentParent, $followupIntent);

        // Close the IntentsClient
        $intentsClient->close();
    }

    public function newIntentWithEntity() 
    {
        $projectId = env('DIALOGFLOW_PROJECT_ID');
        $languageCode = env('DIALOGFLOW_LANGUAGE_CODE');
        $displayName = "Fruit Intent";
        $fruits = ['mango', 'grape', 'watermelon'];
        $entityValueName = 'fruit';

        $trainingPhraseParts = [
            'This is my favorite ',
            'I really enjoy eating ',
            'My top pick is '
        ];

        $messageTexts = [
            'Yes, I know you love $' . $entityValueName,
            'Tell me more about your favorite $' . $entityValueName,
            'What makes $' . $entityValueName . ' your favorite?'
        ];

        $newIntent = (new Intent())
            ->setDisplayName($displayName)
            ->setTrainingPhrases([])
            ->setParameters([
                (new Parameter())
                    ->setDisplayName($entityValueName)
                    ->setEntityTypeDisplayName('@sys.any')
                    ->setValue('$' . $entityValueName)
            ])
            ->setMessages([
                (new Message())
                    ->setText((new Text())->setText($messageTexts)) // Set the response message
            ]);

        $trainingPhrases = [];

        foreach ($trainingPhraseParts as $trainingPhrasePart) {
            $fruit = $fruits[array_rand($fruits)];
            $part = (new Part())
                ->setText($trainingPhrasePart . $fruit)
                ->setEntityType('@sys.any')
                ->setUserDefined(true);

            $trainingPhrase = (new TrainingPhrase())->setParts([$part]);

            $trainingPhrases[] = $trainingPhrase;
        }

        $intentsClient = new IntentsClient();
        $intentParent = $intentsClient->agentName($projectId, $languageCode);
        $newIntent->setTrainingPhrases($trainingPhrases);
        $response = $intentsClient->createIntent($intentParent, $newIntent);
        $intentsClient->close();
    }


    public function newIntentWithEntityMultipleEntites($displayName, $trainingPhraseParts, $messageTexts){
        // Set your Dialogflow project ID and language code
        $projectId = 'your-project-id';
        $languageCode = 'en';

        // Set the display name and value for the entity
        $entityDisplayName = 'fruit';
        $entityValue = 'apple';

        // Set the display name for the intent
        $displayName = 'MyFavoriteFruit';

        // Set the training phrase with the entity
        $trainingPhraseParts = ['This is my favorite', 'I really enjoy eating', 'My top pick is'];

        $numberEntity = (new Entity())->setValue('100')->setSynonyms(['100']);
        $emailEntity = (new Entity())->setValue('mymail@mail.com')->setSynonyms(['mymail@mail.com']);

        // Create the entity type
        $entityType = (new EntityType())
            ->setDisplayName($entityDisplayName)
            ->setKind(Kind::KIND_MAP);

        // Create array to store Entities
        // $entities = [];
        // $entity = (new Entity())->setValue($entityValue);
        // $entities[] = $entity;

        // Create the Intent
        $weatherIntent = (new Intent())
            ->setDisplayName($displayName)
            ->setTrainingPhrases([])
            ->setMessages([
                (new Message())
                    ->setText((new Text())->setText(['Yes I know you love it'])) // Set the response message
            ]);

        // Create array to store TrainingPhrases
        $trainingPhrases = [];

        // Use foreach loop to add TrainingPhrasePart from $trainingPhraseParts
        foreach ($trainingPhraseParts as $trainingPhrasePart) {
            $part = (new Part())->setText($trainingPhrasePart . ' {' . $entityDisplayName . '}');
            $trainingPhrases[] = (new TrainingPhrase())->setParts([$part]);
        }

        // Set the array of TrainingPhrases in the Intent
        $weatherIntent->setTrainingPhrases($trainingPhrases);

        // Set the array of Entities in the EntityType

        $entityType->setEntities([$numberEntity, $emailEntity]);

        // Create EntityTypesClient and IntentsClient
        $entityTypesClient = new EntityTypesClient();
        $intentsClient = new IntentsClient();

        // Set the parent for both EntityType and Intent
        $entityTypeParent = $entityTypesClient->agentName($projectId, $languageCode);
        $intentParent = $intentsClient->agentName($projectId, $languageCode);

        // Create the entity type
        $entityTypeResponse = $entityTypesClient->createEntityType($entityTypeParent, $entityType);

        // Get the entity type ID
        $entityTypeId = $entityTypeResponse->getName();

        // Set the entity type ID in the training phrase
        $part->setEntityType($entityTypeId);

        // Set the array of TrainingPhrases in the Intent
        $weatherIntent->setTrainingPhrases($trainingPhrases);

        // Create the intent
        $response = $intentsClient->createIntent($intentParent, $weatherIntent);

        // Close the EntityTypesClient and IntentsClient
        $entityTypesClient->close();
        $intentsClient->close();
    }

    public function newIntent($displayName, $trainingPhraseParts, $messageTexts)
    {
        try {
            $projectId = env('DIALOGFLOW_PROJECT_ID');
            $languageCode = env('DIALOGFLOW_LANGUAGE_CODE');

            $intentsClient = new IntentsClient();
            $parent = $intentsClient->agentName($projectId, $languageCode);

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

            $intentsClient->close();



            return response()->json([
                    'successful' => $response->getName(),
                ], 200);
            } catch (ApiException $e) {
                $errorDetails = json_decode($e->getMessage(), true);

                if (isset($errorDetails['message'])) {
                    return response()->json([
                        'error' => $errorDetails['message'],
                    ], 400); // You can adjust the status code accordingly
                } else {
                    return response()->json([
                        'error' => 'An unexpected error occurred: ' . $e->getMessage(),
                    ], 500);
                }
            } catch (Exception $e) {
                return response()->json([
                    'error' => 'An unexpected error occurred: ' . $e->getMessage(),
                ], 500);
            }
    }

    public function list(){
        $projectId = env('DIALOGFLOW_PROJECT_ID');
        $intentsClient = new IntentsClient();
        $parent = $intentsClient->agentName($projectId);
        $intents = $intentsClient->listIntents($parent);
        $uuid = null;
        foreach ($intents->iterateAllElements() as $intent) {
            print(str_repeat('>>>>>>>>>>>>>>>>>>>>>>>', 20) . '<br>');
            print(str_repeat('=', 20) . '<br>');
            printf('Intent name: %s' . '<br>', $intent->getName());
            printf('Intent display name: %s' . '<br>', $intent->getDisplayName());
            printf('Action: %s' . '<br>', $intent->getAction());
            printf('Root followup intent: %s' . '<br>',
                $intent->getRootFollowupIntentName());
            printf('Parent followup intent: %s' . '<br>',
                $intent->getParentFollowupIntentName());
            print('<br>');

            print('Input contexts: ' . '<br>');
            foreach ($intent->getInputContextNames() as $inputContextName) {
                printf("\t Name: %s" . '<br>', $inputContextName);
            }

            print('Output contexts: ' . '<br>');
            foreach ($intent->getOutputContexts() as $outputContext) {
                printf("\t Name: %s" . '<br>', $outputContext->getName());
            }
            print(str_repeat('<<<<<<<<<<<<<<<<<<', 20) . '<br><br><br>');
        }
    }

    public function delete()
    {
        try {
            $projectId = env('DIALOGFLOW_PROJECT_ID');
            $intentDisplayName = 'weatherbot';
            $intentsClient = new IntentsClient();
            $parent = $intentsClient->agentName($projectId);
            $intents = $intentsClient->listIntents($parent);
            $uuid = null;
            foreach ($intents->iterateAllElements() as $intent) {
                 if($intent->getDisplayName() == $intentDisplayName){
                    $intentName = (string) $intent->getName();
                    $uuid = substr($intentName, strrpos($intentName, '/') + 1);
                    break;
                 }
            }

            $intentName = $intentsClient->intentName($projectId, $uuid);
            $intentsClient->deleteIntent($intentName);

            return response()->json([
                        'successful' => $intentName . 'deleted',
                    ], 200);
        } catch (ApiException $e) {
            $errorDetails = json_decode($e->getMessage(), true);

            if (isset($errorDetails['message'])) {
                return response()->json([
                    'error' => $errorDetails['message'],
                ], 400); // You can adjust the status code accordingly
            } else {
                return response()->json([
                    'error' => 'An unexpected error occurred: ' . $e->getMessage(),
                ], 500);
            }
        } catch (Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function audioDetect(){
        // ระบุที่อยู่ของไฟล์ audio ในโครงสร้างโฟลเดอร์ของ Laravel
        $path = public_path('assets/audio/audio.wav');

        // อ่านไฟล์ audio และเก็บข้อมูลไบนารีไว้ใน $inputAudio
        $inputAudio = file_get_contents($path);
        $projectId = env('DIALOGFLOW_PROJECT_ID');
        $languageCode = env('DIALOGFLOW_LANGUAGE_CODE');
    
        $audioConfig = new InputAudioConfig();
        $audioConfig->setAudioEncoding(AudioEncoding::AUDIO_ENCODING_LINEAR_16);
        $audioConfig->setLanguageCode($languageCode);
        $audioConfig->setSampleRateHertz(16000);

        $queryInput = new QueryInput();
        $queryInput->setAudioConfig($audioConfig);

        $sessionId = 'session-123';
        // new session
        $sessionsClient = new SessionsClient();
        $session = $sessionsClient->sessionName($projectId, $sessionId ?: uniqid());

        // ตรวจจับความต้องการจากไฟล์ audio
        $response = $sessionsClient->detectIntent($session, $queryInput, ['inputAudio' => $inputAudio]);
        $queryResult = $response->getQueryResult();
        $queryText = $queryResult->getQueryText();
        $intent = $queryResult->getIntent();
        $displayName = $intent->getDisplayName();
        $confidence = $queryResult->getIntentDetectionConfidence();
        $fulfilmentText = $queryResult->getFulfillmentText();
        dd($displayName,$queryText,$confidence,$fulfilmentText);
    }

   
}
