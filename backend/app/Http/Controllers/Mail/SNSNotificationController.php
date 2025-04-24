<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Services\Mail\NotificationProcessor;
use App\Services\Mail\SNSMessageValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SNSNotificationController extends Controller
{
    protected SNSMessageValidator $validator;
    protected NotificationProcessor $processor;

    public function __construct(SNSMessageValidator $validator, NotificationProcessor $processor)
    {
        $this->validator = $validator;
        $this->processor = $processor;
    }

    public function handle(Request $request): Response
    {
        // Log::log('info', 'Received SNS message: ' . $request->getContent());
        $payload = $request->getContent();

        try {
            $message = $this->validator->validate($payload);
           // Log::info('SNS message validated: ' . json_encode($message));

            $messageType = $message['Type'];

            if ($messageType === 'SubscriptionConfirmation') {
                $this->confirmSubscription($message);
            } elseif ($messageType === 'Notification') {
                $this->processNotification($message);
            }

            return response()->json(['message' => 'Processed']);

        } catch (\Exception $e) {
            Log::error('Error processing SNS message: ' . $e->getMessage());
            return response()->json(['message' => 'Error processing message'], 400);
        }
    }

    protected function confirmSubscription($message): void
    {
        $subscribeUrl = $message['SubscribeURL'];
        file_get_contents($subscribeUrl);
        // Log::info('SNS Subscription confirmed.');
    }

    protected function processNotification($message): void
    {
        // Log::info('Received SNS message: ' . $message['Message']);
        $notification = json_decode($message['Message'], true, 512, JSON_THROW_ON_ERROR);
        $eventType = $notification['eventType'];

        $this->processor->process($eventType, $notification);
    }
}
