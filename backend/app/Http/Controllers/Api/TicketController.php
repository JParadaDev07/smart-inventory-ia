<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tickets = Ticket::where('user_id', $user->id)
            ->orderByRaw('CASE priority WHEN "high" THEN 3 WHEN "medium" THEN 2 WHEN "low" THEN 1 ELSE 0 END DESC')
            ->orderByRaw('CASE status WHEN "open" THEN 3 WHEN "in_progress" THEN 2 WHEN "resolved" THEN 1 ELSE 0 END DESC')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($tickets);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
        ]);

        // Soporte prioritario (Enterprise):
        // Si el negocio está en Enterprise activo, aseguramos que los tickets tengan prioridad alta mínima.
        $requestedPriority = $data['priority'] ?? 'medium';
        $subscription = $user->business?->subscription;
        $isActiveEnterprise = $subscription?->plan === 'enterprise' && $subscription->isActive();
        if ($isActiveEnterprise && in_array($requestedPriority, ['low', 'medium'], true)) {
            $requestedPriority = 'high';
        }

        $ticket = Ticket::create([
            'business_id' => $user->business_id,
            'user_id' => $user->id,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $requestedPriority,
            'status' => 'open',
        ]);

        return response()->json([
            'ticket' => $ticket,
        ], 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($ticket->user_id !== $user->id) {
            abort(404);
        }

        $ticket->load([
            'messages' => function ($query) {
                $query->orderBy('created_at');
            },
        ]);

        return response()->json([
            'ticket' => $ticket,
        ]);
    }

    public function addMessage(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($ticket->user_id !== $user->id) {
            abort(404);
        }

        $data = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'sender_type' => 'user',
            'message' => $data['message'],
        ]);

        return response()->json([
            'message' => $message,
        ], 201);
    }
}

