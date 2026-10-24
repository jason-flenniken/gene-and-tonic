<?php

namespace Modules\Intelligence\Contracts;

use Modules\Intelligence\Data\SearchQuery;
use Modules\Intelligence\Data\SourceDocument;
use Modules\Intelligence\Exceptions\ResearchProviderException;

interface WebResearcher
{
    /**
     * @return list<SourceDocument>
     *
     * @throws ResearchProviderException
     */
    public function search(SearchQuery $query): array;
}
