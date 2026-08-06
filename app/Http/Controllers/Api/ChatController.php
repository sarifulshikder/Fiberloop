<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ChatConversationResource;
use App\Http\Resources\Api\ChatMessageResource;
use App\Models\ChatConversation;
use App\Models\Customer;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chatService)
    {
    }

    /**
     * Get all conversations for the authenticated customer.
     */
    public function conversations(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        $conversations = $this->chatService->getCustomerConversations($customer);

        return response()->json([
            'success' => true,
            'data' => ChatConversationResource::collection($conversations),
            'unread_count' => $this->chatService->getUnreadCount($customer),
        ]);
    }

    /**
     * Get a specific conversation.
     */
    public function getConversation(Request $request, ChatConversation $conversation): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        // Authorize: customer can only view their own conversations
        if ($conversation->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this conversation.');
        }

        return response()->json([
            'success' => true,
            'data' => new ChatConversationResource($conversation),
        ]);
    }

    /**
     * Get messages for a conversation.
     */
    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        // Authorize: customer can only view their own conversation messages
        if ($conversation->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this conversation.');
        }

        // Mark conversation as read for customer
        $this->chatService->markConversationAsRead($conversation, $customer->user?->id ?? Auth::id(), 'customer');

        $messages = $this->chatService->getConversationMessages($conversation);

        return response()->json([
            'success' => true,
            'data' => ChatMessageResource::collection($messages),
        ]);
    }

    /**
     * Start a new conversation.
     */
    public function startConversation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'in:low,medium,high,urgent',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = $this->getAuthenticatedCustomer($request);

        $conversation = $this->chatService->createConversation(
            $customer,
            $request->subject,
            $request->message
        );

        $conversation->load(['customer', 'messages']);

        return response()->json([
            'success' => true,
            'message' => 'Conversation started successfully',
            'data' => new ChatConversationResource($conversation),
        ], 201);
    }

    /**
     * Send a message in an existing conversation.
     */
    public function sendMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx|max:5120', // 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = $this->getAuthenticatedCustomer($request);

        // Authorize: customer can only send messages in their own conversations
        if ($conversation->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this conversation.');
        }

        $attachmentPath = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('chat-attachments', 'public');
            $attachmentPath = Storage::url($path);
            $attachmentType = $file->getMimeType();
        }

        $senderId = $customer->user?->id ?? Auth::id();
        $message = $this->chatService->createMessage(
            $conversation,
            $senderId,
            'customer',
            $request->message,
            $attachmentPath,
            $attachmentType
        );

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => new ChatMessageResource($message),
        ], 201);
    }

    /**
     * Close a conversation.
     */
    public function closeConversation(Request $request, ChatConversation $conversation): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        // Authorize: customer can only close their own conversations
        if ($conversation->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this conversation.');
        }

        // Mark as read before closing
        $this->chatService->markConversationAsRead($conversation, $customer->user?->id ?? Auth::id(), 'customer');

        $this->chatService->closeConversation($conversation, $customer->user?->id ?? Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Conversation closed',
            'data' => new ChatConversationResource($conversation->fresh()),
        ]);
    }

    /**
     * Get unread message count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $this->chatService->getUnreadCount($customer),
            ],
        ]);
    }

    /**
     * Get online support agents.
     */
    public function onlineAgents(Request $request): JsonResponse
    {
        $agents = $this->chatService->getOnlineAgents();

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    /**
     * Get the authenticated customer from the request.
     */
    protected function getAuthenticatedCustomer(Request $request): Customer
    {
        $user = $request->user();

        if ($user->customer) {
            return $user->customer;
        }

        $customer = Customer::where('email', $user->email)->first();

        if (!$customer) {
            abort(403, 'Customer not found for authenticated user.');
        }

        return $customer;
    }
}
