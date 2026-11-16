<?php

declare(strict_types=1);

namespace App\Settings\UseCase\GetBrandImage;

use App\Settings\Domain\BrandImageFile;
use App\Settings\Domain\BrandImages;
use App\Settings\Domain\DefaultBrandIcon;
use App\Settings\Domain\SettingRepository;
use App\Shared\Domain\NotFound;
use App\Shared\Domain\ReadAction;

/**
 * One size of the logo or icon the settings name, or of the default icon while none is uploaded. Public, like the
 * rest of the configuration the frontend reads; a file the settings do not name is not found, whether it is stored
 * or not.
 */
#[ReadAction]
final readonly class GetBrandImageHandler
{
    public function __construct(
        private SettingRepository $settings,
        private BrandImages $images,
        private DefaultBrandIcon $defaultIcon,
    ) {
    }

    /**
     * @throws NotFound
     */
    public function __invoke(GetBrandImage $input): GetBrandImageOutput
    {
        $file = BrandImageFile::fromName($input->file) ?? throw new NotFound('No brand image by this name.');
        $uploaded = $this->settings->find($file->kind->setting()->value)?->value();
        if (null === $uploaded && $file->version === $file->kind->defaultVersion()) {
            return new GetBrandImageOutput($this->defaultIcon->png($file->size));
        }

        if ($file->version !== $uploaded) {
            throw new NotFound('No brand image by this name.');
        }

        $content = $this->images->content($file) ?? throw new NotFound(\sprintf('The file %s is missing.', $file->name()));

        return new GetBrandImageOutput($content);
    }
}
