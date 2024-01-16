<?php

namespace App\Http\Controllers;

use Exception;
use GuzzleHttp\Client;
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


class FacebookWebhookController extends Controller
{
    public function __construct()
    {
        $filePath = public_path(env('DIALOGFLOW_CREDENTIALS_JSON'));
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $filePath);
    }

     public function webhook(Request $request)
    {
        // $envHubVerifyToken = env('HUB_VERIFY_TOKEN');
        // $hubChallenge = $request->input('hub_challenge');
        // $hubVerifyToken = $request->input('hub_verify_token');

        // if ($envHubVerifyToken === $hubVerifyToken) {
        //     return response($hubChallenge); 
        // } else {
        //     return response()->json(['error' => 'HUB_VERIFY_TOKEN ไม่ตรงกัน']);
        // }

        $input = $request->all();
            $messages = [
                'I_want_to_buy' => 'We will contact you as soon as possible once we see it sent. If you have other questions, you can ask me!',
                'I_dont_want_to_buy' => 'Thank you for contacting us. If you have other questions, you can ask me!',
            ];

            if (isset($input['object']) && $input['object'] === 'page') {
                foreach ($input['entry'] as $entry) {
                    foreach ($entry['messaging'] as $event) {
                        $senderId = $event['sender']['id'];
              
                        if (isset($event['message']['attachments'][0]['type']) && $event['message']['attachments'][0]['type'] === 'image') {
                            // Handle image attachment
                            // You can access the image URL like this:
                            $imageUrl = $event['message']['attachments'][0]['payload']['url'];

                            // Respond to the image message, e.g., acknowledge receipt
                            $response_text = 'Received an image: ' . $imageUrl;
                        } else {
                            if (isset($event['message']['quick_reply']['payload'])) {
                                $payload = $event['message']['quick_reply']['payload'];
                                $response_text = $messages[$payload];
                            } else {
                                $messageText = $event['message']['text'];
                                $response_text = 'You said: ' . $messageText;
                            }
                        }

                        $projectId = env('DIALOGFLOW_PROJECT_ID');
                        $languageCode = env('DIALOGFLOW_LANGUAGE_CODE');

                        $sessionId = 'session-123';
                        $sessionsClient = new SessionsClient();
                        $session = $sessionsClient->sessionName($projectId, $sessionId ?: uniqid());

                        $textInput = new TextInput();
                        $textInput->setText($messageText);
                        $textInput->setLanguageCode($languageCode);

                        // create query input
                        $queryInput = new QueryInput();
                        $queryInput->setText($textInput);
                    
                        $response = $sessionsClient->detectIntent($session, $queryInput);        
                        $queryResult = $response->getQueryResult();
                        $queryText = $queryResult->getQueryText();
                        $intent = $queryResult->getIntent();
                        $displayName = $intent->getDisplayName();
                        $confidence = $queryResult->getIntentDetectionConfidence();
                        $fulfilmentText = $queryResult->getFulfillmentText();

                        $sessionsClient->close($session);

                        // $messages = [
                        //     [
                        //         'type' => 'text',
                        //         'text' => "สวัสดีครับ\nคุณส่งข้อความว่า '" . $queryText . "'\n\nIntent ที่ตอบคือ '" . $displayName. "'\n\nข้อความคือ '" . $fulfilmentText ."'",
                        //     ],
                        // ];

                        // Send a response_text
                        $gg = $this->sendTextMessage($senderId, $fulfilmentText);
                        if (!$gg) {
                            return response('EVENT_RECEIVED', 200);
                        } else {
                            // Log::error($gg);
                            return $gg . '<br>' . $senderId . '<br>' . $messageText;
                        }
                    }
                }
            }

            return 'ummm';
    }

    private function sendTextMessage($recipientId, $messageText)
    {
        $pageAccessToken = env('PAGE_ACCESS_TOKEN');
        $client = new Client();

        $data = [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $messageText],
        ];

        try {
            $response = $client->post('https://graph.facebook.com/v18.0/me/messages', [
                'query' => ['access_token' => $pageAccessToken],
                'json' => $data,
            ]);

            // Check the response and handle any errors if necessary
            $responseBody = $response->getBody()->getContents();
            return false;
            // You can log or process the response as needed
        } catch (\Exception $e) {
            // Handle any exceptions, e.g., log the error
            return $e->getMessage();
        }
    }

    public function create(Request $request)
    {
        $displayName = 'owlbacusChatBot';
        $trainingPhraseParts = [
                'สนใจแฟรนไชส์',
                'แฟรนไชส์ราคาเท่าไหร่',
            ];
        $messageTexts = [
                'ต้องการแพลนไหนดีค่ะ',
                'ขอทราบข้อมูลลูกค้าเพิ่มเติมค่ะ',
                'ไม่ทราบว่าลูกค้าอยู่จังหวัดไหนค่ะ',
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
}
