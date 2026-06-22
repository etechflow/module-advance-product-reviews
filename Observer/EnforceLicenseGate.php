<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Observer;

use ETechFlow\AdvancedProductReviews\Model\LicenseValidator;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Admin license gate: when the license is invalid, redirect this module's admin
 * pages to the "License Required" gate. The license pages themselves
 * (gate/checkout/activated) and the system-config section stay reachable so the
 * merchant can still activate.
 */
class EnforceLicenseGate implements ObserverInterface
{
    /**
     * @param LicenseValidator $licenseValidator
     * @param BackendUrl $backendUrl
     * @param ActionFlag $actionFlag
     */
    public function __construct(
        private readonly LicenseValidator $licenseValidator,
        private readonly BackendUrl $backendUrl,
        private readonly ActionFlag $actionFlag
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $request = $observer->getEvent()->getRequest();
        if ($request === null) {
            return;
        }
        $fullActionName = (string) $request->getFullActionName();

        // Only gate this module's own admin controllers.
        if (strpos($fullActionName, 'etechflow_reviews_') !== 0) {
            return;
        }
        // Never gate the license pages themselves (would loop) so activation stays reachable.
        if (strpos($fullActionName, 'etechflow_reviews_license_') === 0) {
            return;
        }
        if ($this->licenseValidator->isValid()) {
            return;
        }

        $controllerAction = $observer->getEvent()->getControllerAction();
        if ($controllerAction === null) {
            return;
        }
        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
        $controllerAction->getResponse()->setRedirect(
            $this->backendUrl->getUrl('etechflow_reviews/license/gate')
        );
    }
}
