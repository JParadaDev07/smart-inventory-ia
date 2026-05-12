<?php

namespace App\Jobs;

use App\Models\Business;
use App\Models\Product;
use App\Models\WhatsappNotification;
use App\Services\Notifications\LowStockNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsappNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(LowStockNotifier $notifier): void
    {
        $notifications = WhatsappNotification::query()
            ->where('type', 'low_stock')
            ->where('status', 'scheduled')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->limit(50)
            ->get();

        foreach ($notifications as $notification) {
            /** @var WhatsappNotification $notification */
            $business = Business::find($notification->business_id);
            $product = $notification->product_id ? Product::find($notification->product_id) : null;
            if (!$business || !$product) {
                $notification->update(['status' => 'completed']);
                continue;
            }

            $notifier->notify($business, $product);

            $notification->update([
                'status' => 'completed',
                'next_run_at' => null,
            ]);
        }
    }
}

