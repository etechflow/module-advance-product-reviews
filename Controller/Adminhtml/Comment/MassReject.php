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
 * Reject selected comments.
 */
class MassReject extends AbstractMassAction
{
    /**
     * @inheritDoc
     */
    protected function applyToCollection($collection): int
    {
        $count = 0;
        foreach ($collection as $comment) {
            $comment->setData('status', Status::REJECTED)->save();
            $count++;
        }
        return $count;
    }
}
