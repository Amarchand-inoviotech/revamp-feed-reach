<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $to,
        protected string $subject,
        protected string|Mailable $view,
        public $data = null,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (is_string($this->view)) {

            Mail::send($this->view, [
                'data' => $this->data,
            ], function ($message) {
                $message->to($this->to)
                    ->subject($this->subject);
            });
        } elseif ($this->view instanceof Mailable) {
            Mail::to($this->to)->send($this->view);
        }
    }
}
