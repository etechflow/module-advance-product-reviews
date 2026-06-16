<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Controller\Question;

use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\QuestionFactory;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Question as QuestionResource;
use ETechFlow\AdvancedProductReviews\Model\Source\Status;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * AJAX: submit a product question.
 */
class Submit implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param QuestionFactory $questionFactory
     * @param QuestionResource $questionResource
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly QuestionFactory $questionFactory,
        private readonly QuestionResource $questionResource
    ) {
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        try {
            if (!$this->config->isFlag(Config::XML_PATH_ENABLE_QA)) {
                throw new LocalizedException(__('Questions are disabled.'));
            }

            $productId = (int) $this->request->getParam('product_id');
            $text = trim((string) $this->request->getParam('question'));
            if ($productId <= 0 || $text === '') {
                throw new LocalizedException(__('Please enter your question.'));
            }

            $isLoggedIn = $this->customerSession->isLoggedIn();
            $authorName = $isLoggedIn
                ? trim($this->customerSession->getCustomer()->getName())
                : trim((string) $this->request->getParam('nickname'));
            if ($authorName === '') {
                $authorName = (string) __('Guest');
            }

            $autoApprove = $this->config->isFlag(Config::XML_PATH_AUTO_APPROVE);
            $storeId = (int) $this->storeManager->getStore()->getId();

            $question = $this->questionFactory->create();
            $question->setProductId($productId)
                ->setStoreId($storeId)
                ->setCustomerId($isLoggedIn ? (int) $this->customerSession->getCustomerId() : null)
                ->setAuthorName($authorName)
                ->setQuestion($text)
                ->setHelpfulCount(0)
                ->setStatus($autoApprove ? Status::APPROVED : Status::PENDING);
            $this->questionResource->save($question);

            $message = $autoApprove
                ? __('Your question has been posted.')
                : __('Thank you! Your question is awaiting moderation.');

            return $result->setData(['success' => true, 'message' => $message]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => __('Could not submit your question.')]);
        }
    }
}
