<?php

namespace App\Eloquent\Model;

use App\Eloquent\ModelHasPublicField;
use Illuminate\Database\Eloquent\Model;

class ForumModeratorModel extends Model
{
    use ModelHasPublicField;

    protected $table = 'tbmcr_forumModerator';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->publicFields = [
            'discoveredAt',
            'moderatorType'
        ];
    }
}
