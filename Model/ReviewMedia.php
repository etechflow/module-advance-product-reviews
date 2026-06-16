<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model;

use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewMedia as ReviewMediaResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Review media (image or video) attached to a review.
 *
 * @method int getReviewId()
 * @method $this setReviewId(int $reviewId)
 * @method string getFilePath()
 * @method $this setFilePath(string $path)
 * @method string|null getThumbnailPath()
 * @method $this setThumbnailPath(?string $path)
 * @method string|null getMimeType()
 * @method $this setMimeType(?string $mime)
 * @method int getSortOrder()
 * @method $this setSortOrder(int $order)
 */
class ReviewMedia extends AbstractModel
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';

    /**
     * @var string
     */
    protected $_eventPrefix = 'etechflow_review_media';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReviewMediaResource::class);
    }

    /**
     * @return string
     */
    public function getMediaType(): string
    {
        return (string) ($this->getData('media_type') ?: self::TYPE_IMAGE);
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setMediaType(string $type): self
    {
        return $this->setData('media_type', $type);
    }

    /**
     * @return bool
     */
    public function isVideo(): bool
    {
        return $this->getMediaType() === self::TYPE_VIDEO;
    }
}
