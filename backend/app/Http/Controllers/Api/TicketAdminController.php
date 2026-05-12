<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketAdminController extends Controller
{
    protected function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $query = Ticket::query()->with(['user', 'business']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($businessId = $request->query('business_id')) {
            $query->where('business_id', $businessId);
        }

        $tickets = $query
            ->orderByRaw('CASE priority WHEN "high" THEN 3 WHEN "medium" THEN 2 WHEN "low" THEN 1 ELSE 0 END DESC')
            ->orderByRaw('CASE status WHEN "open" THEN 3 WHEN "in_progress" THEN 2 WHEN "resolved" THEN 1 ELSE 0 END DESC')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($tickets);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->ensureAdmin($request);

        $ticket->load([
            'user',
            'business',
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
        $this->ensureAdmin($request);

        $data = $request->validate([
            'message' => ['required', 'string'],
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved'],
        ]);

        $admin = $request->user();

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'sender_type' => 'admin',
            'message' => $data['message'],
        ]);

        if (!empty($data['status'])) {
            $ticket->status = $data['status'];
            if ($data['status'] === 'resolved' && !$ticket->closed_at) {
                $ticket->closed_at = now();
            }
            $ticket->save();
        }

        return response()->json([
            'message' => $message,
        ], 201);
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
        ]);

        if (isset($data['status'])) {
            $ticket->status = $data['status'];
            if ($data['status'] === 'resolved' && !$ticket->closed_at) {
                $ticket->closed_at = now();
            }
        }

        if (isset($data['priority'])) {
            $ticket->priority = $data['priority'];
        }

        $ticket->save();

        return response()->json([
            'ticket' => $ticket->fresh(),
        ]);
    }
}

