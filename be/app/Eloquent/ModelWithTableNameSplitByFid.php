<?php

namespace App\Eloquent;

use Illuminate\Database\Eloquent\Model;

abstract class ModelWithTableNameSplitByFid extends Model
{
    protected int $fid = 0;

    public function setFid(int $fid): static
    {
        $this->fid = $fid;
        $this->setTable($this->getTableNameWithFid($fid));
        return $this;
    }

    abstract protected function getTableNameWithFid(int $fid): string;

    /**
     * Override the parent relation instance method for passing valid fid to new related model
     * @param class-string $class
     */
    protected function newRelatedInstance(string $class)
    {
        return tap((new $class())->setFid($this->fid), function ($instance) {
            if (!$instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }

    /**
     * Override the parent newInstance method for passing valid fid to model's query builder
     */
    public function newInstance($attributes = [], $exists = false): static
    {
        return parent::newInstance($attributes, $exists)->setFid($this->fid);
    }
}
