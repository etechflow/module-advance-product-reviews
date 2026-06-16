<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model;

use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewVote as ReviewVoteResource;
use Magento\Framework\Model\AbstractModel;

/**
 * A single "was this helpful?" vote.
 *
 * @method int getReviewId()
 * @method $this setReviewId(int $reviewId)
 * @method int|null getCustomerId()
 * @method $this setCustomerId(?int $customerId)
 * @method string|null getVisitorHash()
 * @method $this setVisitorHash(?string $hash)
 */
class ReviewVote extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'etechflow_review_vote';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReviewVoteResource::class);
    }

    /**
     * @return bool
     */
    public function getIsHelpful(): bool
    {
        return (bool) $this->getData('is_helpful');
    }

    /**
     * @param bool $isHelpful
     * @return $this
     */
    public function setIsHelpful(bool $isHelpful): self
    {
        return $this->setData('is_helpful', $isHelpful ? 1 : 0);
    }
}
