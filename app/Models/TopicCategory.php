<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicCategory extends Model
{
    protected $table = 'topic_categories';

    protected $fillable = [
        'user_type_id', 
        'name'
    ];

    public function user_type()
    {
        return $this->belongsTo(UserType::class);
    }
    public function topics()
    {
        return $this->hasMany(Topic::class);
    }
}
