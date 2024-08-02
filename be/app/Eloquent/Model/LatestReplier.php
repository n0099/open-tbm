<?php

namespace App\Eloquent\Model;

use App\Eloquent\ModelHasPublicField;
use Illuminate\Database\Eloquent\Model;

class LatestReplier extends Model
{
    use ModelHasPublicField;

    protected $table = 'tbmc_latestReplier';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->publicFields = [
            'id',
            'uid',
            'createdAt',
            'updatedAt'
        ];
    }
}
