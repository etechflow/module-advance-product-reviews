<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Controller\Adminhtml\Comment;

use ETechFlow\AdvancedProductReviews\Model\Source\Status;

/**
 * Approve selected comments.
 */
class MassApprove extends AbstractMassAction
{
    /**
     * @inheritDoc
     */
    protected function applyToCollection($collection): int
    {
        $count = 0;
        foreach ($collection as $comment) {
            $comment->setData('status', Status::APPROVED)->save();
            $count++;
        }
        return $count;
    }
}
