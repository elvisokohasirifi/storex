<?php

namespace App\Models;

use Database\Factories\ModerationLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModerationLog extends Model
{
    /** @use HasFactory<ModerationLogFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['id'];
}
