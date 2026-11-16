<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetWebManifest;

/**
 * The members of a web app manifest; the controller writes them under the names the manifest standard gives them.
 */
final readonly class GetWebManifestOutput
{
    /**
     * @param string                                                 $startUrl a path from the root of the host
     * @param string                                                 $scope    a path from the root of the host
     * @param list<array{src: string, sizes: string, type: string}> $icons    the sizes a browser needs to install the app
     */
    public function __construct(
        public string $name,
        public string $shortName,
        public string $language,
        public string $startUrl,
        public string $scope,
        public string $display,
        public string $themeColor,
        public string $backgroundColor,
        public array $icons,
    ) {
    }
}
