<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Controller\Adminhtml\Question;

use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Question\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Shared logic for question mass actions.
 */
abstract class AbstractMassAction extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ETechFlow_AdvancedProductReviews::questions';

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
                __('%1 question(s) were updated.', $count)
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $resultRedirect->setPath('*/*/index');
    }

    /**
     * @param \ETechFlow\AdvancedProductReviews\Model\ResourceModel\Question\Collection $collection
     * @return int
     */
    abstract protected function applyToCollection($collection): int;
}
