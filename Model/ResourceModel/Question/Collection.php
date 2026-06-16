<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel\Question;

use ETechFlow\AdvancedProductReviews\Model\Question as QuestionModel;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Question as QuestionResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Q&A question collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'question_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(QuestionModel::class, QuestionResource::class);
    }

    /**
     * Approved questions for a product in a store, newest first.
     *
     * @param int $productId
     * @param int $storeId
     * @param int $status
     * @return $this
     */
    public function addProductFilter(int $productId, int $storeId, int $status = 2): self
    {
        $this->addFieldToFilter('product_id', $productId);
        $this->addFieldToFilter('store_id', ['in' => [$storeId, 0]]);
        $this->addFieldToFilter('status', $status);
        $this->setOrder('created_at', self::SORT_ORDER_DESC);
        return $this;
    }
}
