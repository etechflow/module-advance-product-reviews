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
 * Reject selected questions.
 */
class MassReject extends AbstractMassAction
{
    /**
     * @inheritDoc
     */
    protected function applyToCollection($collection): int
    {
        $count = 0;
        foreach ($collection as $question) {
            $question->setData('status', Status::REJECTED)->save();
            $count++;
        }
        return $count;
    }
}
