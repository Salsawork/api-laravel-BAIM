<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorTopic extends Model
{
  
    protected $table = 'mentor_topics';

    protected $primaryKey = 'id';
    protected $fillable = [
        'mentor_id', 
        'topic_category_id'
    ];

    public function mentor()
    {
        return $this->belongsTo(Mentor::class);
    }

    public function topic_category()
    {
        return $this->belongsTo(TopicCategory::class);
    }
}
