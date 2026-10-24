<?php

namespace Modules\Intelligence\Testing;

use Modules\Intelligence\Contracts\WebResearcher;
use Modules\Intelligence\Data\SearchQuery;
use Modules\Intelligence\Data\SourceDocument;
use Modules\Intelligence\Exceptions\ResearchProviderException;

/**
 * In-memory WebResearcher for other modules' tests.
 */
class FakeWebResearcher implements WebResearcher
{
    /** @var list<SourceDocument> */
    private array $documents = [];

    private ?string $failure = null;

    /** @var list<SearchQuery> */
    private array $queries = [];

    public static function install(): self
    {
        $fake = new self;

        app()->instance(WebResearcher::class, $fake);

        return $fake;
    }

    /**
     * @param  list<SourceDocument>  $documents
     */
    public function returning(array $documents): self
    {
        $this->documents = $documents;

        return $this;
    }

    /**
     * Makes the next search throw ResearchProviderException.
     */
    public function failWith(string $message): self
    {
        $this->failure = $message;

        return $this;
    }

    /**
     * @return list<SearchQuery>
     */
    public function queries(): array
    {
        return $this->queries;
    }

    public function search(SearchQuery $query): array
    {
        $this->queries[] = $query;

        if ($this->failure !== null) {
            $message = $this->failure;
            $this->failure = null;

            throw ResearchProviderException::because($message);
        }

        return $this->documents;
    }
}
