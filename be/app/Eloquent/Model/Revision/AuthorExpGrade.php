<?php

namespace App\Eloquent\Model\Revision;

use App\Eloquent\ModelHasPublicField;
use Illuminate\Database\Eloquent\Model;

class AuthorExpGrade extends Model
{
    use ModelHasPublicField;

    protected $table = 'tbmcr_authorExpGrade';

    protected $hidden = ['fid', 'uid']; // for relationship in UserModel::currentForumModerator()

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->publicFields = [
            'discoveredAt',
            'fid',
            'uid',
            'authorExpGrade'
        ];
    }
}
