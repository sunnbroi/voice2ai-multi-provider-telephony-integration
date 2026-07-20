<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    public const BINOTEL = 'binotel';
    public const ZADARMA = 'Zadarma';
    public const UNITALK = 'Unitalk';
    public const PHONET = 'Phonet';

    protected $fillable = ['name'];

    public function integrations()
    {
        return $this->hasMany(Integration::class);
    }
}
