<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Controller\Adminhtml\Comment;

use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewComment\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Shared logic for comment mass actions (approve / reject / delete).
 */
abstract class AbstractMassAction extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ETechFlow_AdvancedProductReviews::comments';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Context $context,
        protected readonly Filter $filter,
        protected readonly CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Apply the action to each selected comment.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = $this->applyToCollection($collection);
            $this->messageManager->addSuccessMessage(
                __('%1 comment(s) were updated.', $count)
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $resultRedirect->setPath('*/*/index');
    }

    /**
     * @param \ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewComment\Collection $collection
     * @return int Number of affected rows
     */
    abstract protected function applyToCollection($collection): int;
}
