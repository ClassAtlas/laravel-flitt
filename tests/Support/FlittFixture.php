<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

use Saloon\Data\RecordedResponse;
use Saloon\Http\Faking\Fixture;
use Saloon\Http\Faking\MockResponse;

/**
 * A recorded answer from the test merchant. In record mode it always records again; before saving, the answer is
 * scrubbed and signed again with the public key, and only its Content-Type header is kept.
 */
final class FlittFixture extends Fixture
{
    public function getMockResponse(): ?MockResponse
    {
        return Live::mode() === 'record' ? null : parent::getMockResponse();
    }

    protected function beforeSave(RecordedResponse $recordedResponse): RecordedResponse
    {
        $contentType = $recordedResponse->headers['Content-Type'] ?? 'application/json; charset=UTF-8';

        $recordedResponse->headers = ['Content-Type' => $contentType];
        $recordedResponse->data = Scrubber::answer((string) $recordedResponse->data, Live::orderIds());

        return $recordedResponse;
    }
}
