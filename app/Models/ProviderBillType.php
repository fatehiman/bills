<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'bill_type', 'priority', 'enabled'])]
class ProviderBillType extends Model
{
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
