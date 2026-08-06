<?php

namespace App\Services\Ai;

use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ChatbotService
{
    /**
     * Process a message from a customer and return a response.
     * Escalates to a ticket if necessary.
     */
    public function handleMessage(Customer $customer, string $message): array
    {
        try {
            // Get billing/account context
            $activeSubscription = $customer->subscriptions()->where('status', 'active')->first();
            $package = $activeSubscription ? $activeSubscription->package->name : 'None';
            $unpaidInvoices = $customer->invoices()->whereIn('status', ['draft', 'sent', 'overdue', 'partial'])->sum('total');
            $balance = $unpaidInvoices / 100; // Assuming poysha

            $systemPrompt = "You are an AI support assistant for Fiberloop, an ISP. 
Your goal is to answer common billing and account questions.
Customer context:
Name: {$customer->first_name} {$customer->last_name}
Status: {$customer->status}
Current Package: {$package}
Unpaid Balance: {$balance} BDT

Rules:
1. Answer politely and accurately based on the context provided.
2. You cannot change their package, suspend their account, or do anything that modifies their account.
3. If the user asks for something you cannot do, or complains about an outage/technical issue, or asks to talk to a human, you MUST escalate by including the exact string 'ESCALATE_TICKET' in your response along with a summary of their issue.
";

            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message],
                ],
            ]);

            $reply = $response->choices[0]->message->content;

            if (str_contains($reply, 'ESCALATE_TICKET')) {
                // Strip the escalation string from the user-facing reply
                $userReply = trim(str_replace('ESCALATE_TICKET', '', $reply));

                // Create a ticket
                $ticket = Ticket::create([
                    'customer_id' => $customer->id,
                    'category' => 'complaint', // General category for escalations
                    'priority' => 'normal',
                    'status' => 'open',
                    'title' => 'AI Escalation: ' . substr($message, 0, 50),
                    'description' => "Customer message: {$message}\n\nAI Summary: {$userReply}",
                ]);

                return [
                    'reply' => "I have escalated this issue to our human support team. Ticket #" . $ticket->id . " has been created for you.",
                    'escalated' => true,
                    'ticket_id' => $ticket->id,
                ];
            }

            return [
                'reply' => $reply,
                'escalated' => false,
            ];

        } catch (\Exception $e) {
            Log::error('Chatbot error: ' . $e->getMessage());
            return [
                'reply' => "I'm sorry, I'm having trouble connecting right now. Please try again later or call support.",
                'escalated' => false,
            ];
        }
    }
}
