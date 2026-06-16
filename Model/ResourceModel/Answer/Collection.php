<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel\Answer;

use ETechFlow\AdvancedProductReviews\Model\Answer as AnswerModel;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Answer as AnswerResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Q&A answer collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'answer_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(AnswerModel::class, AnswerResource::class);
    }

    /**
     * Approved answers for a question; admin answers first, then newest.
     *
     * @param int $questionId
     * @param int $status
     * @return $this
     */
    public function addQuestionFilter(int $questionId, int $status = 2): self
    {
        $this->addFieldToFilter('question_id', $questionId);
        $this->addFieldToFilter('status', $status);
        $this->setOrder('is_admin', self::SORT_ORDER_DESC);
        $this->setOrder('created_at', self::SORT_ORDER_DESC);
        return $this;
    }
}
