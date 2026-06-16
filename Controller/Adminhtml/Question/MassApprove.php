<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Controller\Adminhtml\Question;

use ETechFlow\AdvancedProductReviews\Model\Source\Status;

/**
 * Approve selected questions.
 */
class MassApprove extends AbstractMassAction
{
    /**
     * @inheritDoc
     */
    protected function applyToCollection($collection): int
    {
        $count = 0;
        foreach ($collection as $question) {
            $question->setData('status', Status::APPROVED)->save();
            $count++;
        }
        return $count;
    }
}
