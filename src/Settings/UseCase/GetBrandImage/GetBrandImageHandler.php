<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetBrandImage;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImages;
use App\Settings\Domain\SettingRepository;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\ReadAction;

/**
 * One size of the logo or icon the settings name. Public, like the rest of the configuration the frontend reads; a
 * file the settings do not name is not found, whether it is stored or not.
 */
#[ReadAction]
final readonly class GetBrandImageHandler
{
    public function __construct(
        private SettingRepository $settings,
        private BrandImages $images,
    ) {
    }

    /**
     * @throws NotFound
     */
    public function __invoke(GetBrandImage $input): GetBrandImageOutput
    {
        $file = BrandImageFile::fromName($input->file);
        if (null === $file || $file->version !== $this->settings->find($file->kind->setting()->value)?->value()) {
            throw new NotFound('No brand image by this name.');
        }

        $content = $this->images->content($file) ?? throw new NotFound(\sprintf('The file %s is missing.', $file->name()));

        return new GetBrandImageOutput($content);
    }
}
