<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HurlRequestsTest extends TestCase
{
    #[Test]
    public function it_gives_method_and_path_of_each_request_to_the_api_with_the_variables_left_in(): void
    {
        $text = <<<'HURL'
            # Log in first.
            POST {{baseUrl}}/api/v1/auth/login
            Content-Type: application/json
            {"email": "{{email}}"}
            HTTP 204

            GET {{baseUrl}}/api/v1/admin/members/{{memberId}}
            HTTP 200
            HURL;

        $requests = HurlRequests::fromText($text);

        $this->assertSame(['POST /api/v1/auth/login', 'GET /api/v1/admin/members/{{memberId}}'], $requests);
    }

    #[Test]
    public function it_leaves_the_query_string_out_of_the_path(): void
    {
        $requests = HurlRequests::fromText("GET {{baseUrl}}/api/v1/rounds?from={{periodFrom}}&to={{periodTo}}\nHTTP 200\n");

        $this->assertSame(['GET /api/v1/rounds'], $requests);
    }

    /**
     * The description is not an operation of itself; another host, such as Mailpit, and a path that comes from a
     * variable, such as the icon from /config, are no paths of the API to look up.
     */
    #[Test]
    public function it_leaves_out_the_description_other_hosts_and_a_path_from_a_variable(): void
    {
        $text = <<<'HURL'
            GET {{baseUrl}}/api/v1/docs.json
            HTTP 200

            GET {{mailpitUrl}}/api/v1/message/latest
            HTTP 200

            GET {{baseUrl}}{{iconUrl}}
            HTTP 200

            GET {{baseUrl}}/api/v1/health
            HTTP 200
            HURL;

        $requests = HurlRequests::fromText($text);

        $this->assertSame(['GET /api/v1/health'], $requests);
    }

    /**
     * A line in a body or a comment that happens to start like a request is no request.
     */
    #[Test]
    public function it_reads_only_lines_that_start_with_a_method(): void
    {
        $text = <<<'HURL'
            # GET {{baseUrl}}/api/v1/commented
            POST {{baseUrl}}/api/v1/admin/outcomes
            ```
              GET {{baseUrl}}/api/v1/indented
            ```
            HTTP 201
            HURL;

        $requests = HurlRequests::fromText($text);

        $this->assertSame(['POST /api/v1/admin/outcomes'], $requests);
    }
}
