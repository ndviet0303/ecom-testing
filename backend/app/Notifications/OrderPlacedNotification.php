<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Đơn hàng #'.$this->order->id.' đã được tạo')
            ->line('Cảm ơn bạn đã đặt hàng.')
            ->line('Mã đơn: #'.$this->order->id)
            ->line('Tổng thanh toán: '.number_format($this->order->total_cents / 100, 0, ',', '.').' đ');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'total_cents' => $this->order->total_cents,
            'status' => $this->order->status,
        ];
    }
}
