<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model;

use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Question as QuestionResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Customer question about a product.
 *
 * @method int getProductId()
 * @method $this setProductId(int $productId)
 * @method int getStoreId()
 * @method $this setStoreId(int $storeId)
 * @method int|null getCustomerId()
 * @method $this setCustomerId(?int $customerId)
 * @method string getAuthorName()
 * @method $this setAuthorName(string $name)
 * @method string getQuestion()
 * @method $this setQuestion(string $question)
 * @method int getHelpfulCount()
 * @method $this setHelpfulCount(int $count)
 * @method int getStatus()
 * @method $this setStatus(int $status)
 */
class Question extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'etechflow_qa_question';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(QuestionResource::class);
    }
}
