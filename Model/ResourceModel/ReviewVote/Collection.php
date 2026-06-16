<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewVote;

use ETechFlow\AdvancedProductReviews\Model\ReviewVote as ReviewVoteModel;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewVote as ReviewVoteResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Review vote collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'vote_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReviewVoteModel::class, ReviewVoteResource::class);
    }
}
