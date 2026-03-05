<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSalary extends Model
{
    protected $fillable = [
        'user_id',
        'salary',
        'effective_from',
        'effective_to',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
