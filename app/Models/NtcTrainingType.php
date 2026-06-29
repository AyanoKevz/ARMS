<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NtcTrainingType extends Model
{
    protected $fillable = ['name', 'code'];

    public function ntcReports()
    {
        return $this->hasMany(NtcReport::class);
    }
}
