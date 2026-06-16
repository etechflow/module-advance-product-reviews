<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model;

use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Answer as AnswerResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Answer to a product question.
 *
 * @method int getQuestionId()
 * @method $this setQuestionId(int $questionId)
 * @method int|null getCustomerId()
 * @method $this setCustomerId(?int $customerId)
 * @method string getAuthorName()
 * @method $this setAuthorName(string $name)
 * @method string getAnswer()
 * @method $this setAnswer(string $answer)
 * @method int getHelpfulCount()
 * @method $this setHelpfulCount(int $count)
 * @method int getStatus()
 * @method $this setStatus(int $status)
 */
class Answer extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'etechflow_qa_answer';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(AnswerResource::class);
    }

    /**
     * @return bool
     */
    public function isAdmin(): bool
    {
        return (bool) $this->getData('is_admin');
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setIsAdmin(bool $value): self
    {
        return $this->setData('is_admin', $value ? 1 : 0);
    }
}
