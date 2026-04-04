<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmLog extends Model
{
    protected $fillable = [
        'user_id',
        'chat_message_id',
        'model',
        'prompt_version',
        'tokens_in',
        'tokens_out',
        'cost_estimate',
        'latency_ms',
        'ok',
        'error_message',
        'feedback',
    ];

    protected $casts = [
        'ok'       => 'boolean',
        'feedback' => 'boolean',
        'cost_estimate' => 'decimal:6',
    ];
}
