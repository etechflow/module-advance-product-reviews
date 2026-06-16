<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Controller\Adminhtml\Reminder;

use ETechFlow\AdvancedProductReviews\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Review reminders grid.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'ETechFlow_AdvancedProductReviews::reminders';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param LicenseValidator $licenseValidator
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly LicenseValidator $licenseValidator
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if (!$this->licenseValidator->isValid()) {
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)
                ->setPath('etechflow_reviews/license/gate');
        }
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ETechFlow_AdvancedProductReviews::reminders');
        $resultPage->getConfig()->getTitle()->prepend(__('Review Reminders'));
        return $resultPage;
    }
}
