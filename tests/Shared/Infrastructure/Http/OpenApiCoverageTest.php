<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Http;

use App\Tests\Support\HurlRequests;
use App\Tests\Support\OpenApiOperations;
use App\Tests\Support\OpenApiSuccessAnswers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * The description on /api/v1/docs.json keeps pace with the API: every route is in it with its method, nothing else
 * is, every operation names its successful answer and the problem answers it can give, and every request of the
 * Hurl files exists.
 */
#[Group('functional')]
final class OpenApiCoverageTest extends WebTestCase
{
    private const string HURL_FILES = __DIR__ . '/../../../../hurl';
    private const string PUBLIC_PATH = '#^/api/v1/(health|config|manifest\.webmanifest|media/brand/|auth/)#';

    #[Test]
    public function it_describes_every_route_of_the_api_and_nothing_else(): void
    {
        $operations = self::operations()->keys();

        self::assertSame(self::routes(), $operations);
    }

    #[Test]
    public function it_names_the_problem_answers_of_every_operation(): void
    {
        $missing = [];
        foreach (self::operations()->operations as $operation => $described) {
            $protected = 1 !== \preg_match(self::PUBLIC_PATH, $described['path']);
            $expected = \array_keys(\array_filter([
                401 => $protected,
                403 => $protected,
                404 => \str_contains($described['path'], '{'),
                422 => $described['takesInput'],
            ]));
            foreach ($expected as $status) {
                if (!OpenApiOperations::isProblem($described['responses'][$status] ?? null)) {
                    $missing[] = $operation . ' ' . $status;
                }
            }
        }

        self::assertSame([], $missing);
    }

    /**
     * A 204 has no body; any other success names the schema of its body, so a client knows what it gets.
     */
    #[Test]
    public function it_names_the_successful_answer_of_every_operation(): void
    {
        $missing = OpenApiSuccessAnswers::undescribedIn(self::operations());

        self::assertSame([], $missing);
    }

    /**
     * The last walker who withdraws takes the round with them, so the frontend treats this 404 as withdrawn.
     */
    #[Test]
    public function it_tells_that_a_repeated_withdrawal_can_find_no_round(): void
    {
        $operations = self::operations()->operations;

        foreach (['DELETE /api/v1/rounds/{roundId}/walkers/me', 'DELETE /api/v1/rounds/{roundId}/walkers/{memberId}'] as $operation) {
            /** @var array{description?: string} $notFound */
            $notFound = $operations[$operation]['responses'][404] ?? [];
            self::assertStringContainsString('last walker', $notFound['description'] ?? '', $operation);
        }
    }

    /**
     * The Hurl files are written by hand, not generated from the spec, and run only against a live app outside CI; a
     * request they hold that the API lacks would fail only when someone runs them. No request at all means the files
     * moved, not that they all fit.
     */
    #[Test]
    public function it_describes_every_request_of_the_hurl_files(): void
    {
        $operations = self::operations();
        $requests = HurlRequests::fromDirectory(self::HURL_FILES);

        $unknown = \array_values(\array_filter($requests, static fn(string $request): bool => !$operations->has($request)));

        self::assertSame([false, []], [[] === $requests, $unknown]);
    }

    private static function operations(): OpenApiOperations
    {
        $client = self::createClient();
        $client->request('GET', '/api/v1/docs.json');
        self::assertResponseIsSuccessful();

        return OpenApiOperations::fromJson((string) $client->getResponse()->getContent());
    }

    /**
     * Reads the router of the kernel the description booted.
     *
     * @return list<string> METHOD path of every route under /api/v1 but the description itself, sorted
     */
    private static function routes(): array
    {
        $router = self::getContainer()->get('router');
        // @phpstan-ignore staticMethod.alreadyNarrowedType (PHPStan reads the type from the container, Mago does not)
        self::assertInstanceOf(RouterInterface::class, $router);
        $routes = [];
        foreach ($router->getRouteCollection()->all() as $route) {
            $path = $route->getPath();
            if (!\str_starts_with($path, '/api/v1/') || '/api/v1/docs.json' === $path) {
                continue;
            }

            foreach ($route->getMethods() as $method) {
                $routes[] = $method . ' ' . $path;
            }
        }

        $routes = \array_values(\array_unique($routes));
        \sort($routes);

        return $routes;
    }
}
