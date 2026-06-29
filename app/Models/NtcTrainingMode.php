<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NtcTrainingMode extends Model
{
    protected $fillable = ['name', 'code'];

    public function ntcReports()
    {
        return $this->hasMany(NtcReport::class);
    }
}
