<?php

namespace App\DTO\Post;

use Symfony\Component\Serializer\Attribute\Ignore;

interface SortablePost
{
    // https://stackoverflow.com/questions/79594613/boolean-fields-missing-in-api-platform-response/79596014#79596014
    // https://github.com/symfony/symfony/issues/37605
    public function getIsMatchQuery(): bool;

    public function setIsMatchQuery(bool $value): self;

    #[Ignore]
    public function getSortingKey(): mixed;

    public function setSortingKey(mixed $value): self;
}
