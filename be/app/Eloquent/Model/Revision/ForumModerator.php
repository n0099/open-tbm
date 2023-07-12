<?php

namespace App\Eloquent\Model\Revision;

use App\Eloquent\ModelHasPublicField;
use Illuminate\Database\Eloquent\Model;

class ForumModerator extends Model
{
    use ModelHasPublicField;

    protected $table = 'tbmcr_forumModerator';

    protected $hidden = ['portrait']; // for relationship in User::currentForumModerator()

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->publicFields = [
            'discoveredAt',
            'portrait',
            'moderatorTypes'
        ];
    }
}
