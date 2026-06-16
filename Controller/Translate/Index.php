<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Controller\Translate;

use ETechFlow\AdvancedProductReviews\Model\Service\TranslationService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * AJAX: translate a single review into the current storefront language.
 *
 * Returns cached translations instantly; otherwise calls Claude on demand.
 */
class Index implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param TranslationService $translationService
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly TranslationService $translationService,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        try {
            $reviewId = (int) $this->request->getParam('review_id');
            $storeId = (int) $this->storeManager->getStore()->getId();

            $translation = $this->translationService->translateReview($reviewId, $storeId);

            return $result->setData([
                'success' => true,
                'language' => $translation['language'],
                'title' => $translation['title'],
                'detail' => $translation['detail'],
                'pros' => $translation['pros'],
                'cons' => $translation['cons'],
            ]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->logger->error('[ETechFlow Reviews] Translate controller error: ' . $e->getMessage());
            return $result->setData(['success' => false, 'message' => __('Translation failed. Please try again.')]);
        }
    }
}
