<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Controller\Adminhtml\Question;

/**
 * Delete selected questions.
 */
class MassDelete extends AbstractMassAction
{
    /**
     * @inheritDoc
     */
    protected function applyToCollection($collection): int
    {
        $count = 0;
        foreach ($collection as $question) {
            $question->delete();
            $count++;
        }
        return $count;
    }
}
