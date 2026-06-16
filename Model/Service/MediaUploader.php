<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Service;

use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\ReviewMedia;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Mime;
use Magento\Framework\File\Uploader;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;

/**
 * Validates and stores review images/videos under pub/media.
 *
 * Files live under pub/media/etechflow/reviews/ which is writable on
 * Magento Cloud (only var/, pub/media/, etc. are writable there).
 */
class MediaUploader
{
    private const BASE_PATH = 'etechflow/reviews';
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Expected MIME top-level category ("image" | "video") for the file
     * currently being saved, consulted by the upload validation callback.
     *
     * @var string
     */
    private string $expectedCategory = '';

    /**
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     * @param Config $config
     * @param Mime $mime
     * @param Request $request
     */
    public function __construct(
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem,
        private readonly Config $config,
        private readonly Mime $mime,
        private readonly Request $request
    ) {
    }

    /**
     * Upload a single file from the $_FILES array under the given input id.
     *
     * @param string|array $fileId Key in $_FILES, or [name, index] for multi-file inputs
     * @param int|string|null $storeId
     * @return array{type:string,file:string,mime:string}|null Null when no file present
     * @throws LocalizedException
     */
    public function upload($fileId, $storeId = null): ?array
    {
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        } catch (\Exception $e) {
            // No file uploaded for this id.
            return null;
        }

        $name = (string) $uploader->getFileExtension();
        $extension = strtolower($name);
        $isImage = in_array($extension, self::IMAGE_EXTENSIONS, true);
        $isVideo = in_array($extension, $this->config->getAllowedVideoTypes($storeId), true);

        if (!$isImage && !$isVideo) {
            throw new LocalizedException(
                __(
                    'Unsupported file type ".%1". Allowed: images and %2.',
                    $extension,
                    implode(', ', $this->config->getAllowedVideoTypes($storeId))
                )
            );
        }
        if ($isVideo && !$this->config->isVideoEnabled($storeId)) {
            throw new LocalizedException(__('Video uploads are disabled.'));
        }

        $this->assertSize($this->resolveSize($fileId), $isVideo, $storeId);

        $allowed = $isImage
            ? self::IMAGE_EXTENSIONS
            : $this->config->getAllowedVideoTypes($storeId);

        $uploader->setAllowedExtensions($allowed);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(true);
        $uploader->setAllowCreateFolders(true);

        // Sniff the real content type so a script can't masquerade as media
        // by simply wearing a .mp4 / .jpg extension.
        $this->expectedCategory = $isVideo ? 'video' : 'image';
        $uploader->addValidateCallback('etf_mime_guard', $this, 'validateRealMimeType');

        $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $absolutePath = $mediaDir->getAbsolutePath(self::BASE_PATH);

        $result = $uploader->save($absolutePath);
        if (empty($result['file'])) {
            throw new LocalizedException(__('Could not save the uploaded file.'));
        }

        $relative = self::BASE_PATH . $result['file'];

        return [
            'type' => $isVideo ? ReviewMedia::TYPE_VIDEO : ReviewMedia::TYPE_IMAGE,
            'file' => $relative,
            'mime' => (string) ($result['type'] ?? ''),
        ];
    }

    /**
     * Resolve the uploaded size in bytes for a string or [name, index] fileId.
     *
     * @param string|array $fileId
     * @return int
     */
    private function resolveSize($fileId): int
    {
        $files = $this->request->getFiles()->toArray();
        if (is_array($fileId)) {
            [$name, $index] = [$fileId[0] ?? '', $fileId[1] ?? 0];
            return (int) ($files[$name]['size'][$index] ?? 0);
        }
        return (int) ($files[$fileId]['size'] ?? 0);
    }

    /**
     * Enforce the configured max size for image/video uploads.
     *
     * @param int $bytes
     * @param bool $isVideo
     * @param int|string|null $storeId
     * @return void
     * @throws LocalizedException
     */
    private function assertSize(int $bytes, bool $isVideo, $storeId): void
    {
        $maxMb = $isVideo
            ? $this->config->getMaxVideoSizeMb($storeId)
            : (int) ($this->config->getValue(Config::XML_PATH_MAX_IMAGE_SIZE, $storeId) ?: 5);
        $maxBytes = $maxMb * 1024 * 1024;
        if ($maxBytes > 0 && $bytes > $maxBytes) {
            throw new LocalizedException(
                __('File is too large. Maximum allowed size is %1 MB.', $maxMb)
            );
        }
    }

    /**
     * Uploader validation callback: confirm the file's real MIME category
     * matches what its extension claims. Rejects spoofed/script uploads.
     *
     * @param string $filePath Absolute path to the uploaded temp file
     * @return void
     * @throws LocalizedException
     */
    public function validateRealMimeType(string $filePath): void
    {
        $detected = strtolower($this->mime->getMimeType($filePath));
        $category = explode('/', $detected)[0] ?? '';

        if ($this->expectedCategory !== '' && $category !== $this->expectedCategory) {
            throw new LocalizedException(
                __('The uploaded file is not a valid %1 (detected: %2).', $this->expectedCategory, $detected)
            );
        }
    }
}
