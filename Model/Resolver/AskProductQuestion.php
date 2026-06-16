<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Resolver;

use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\QuestionFactory;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Question as QuestionResource;
use ETechFlow\AdvancedProductReviews\Model\Source\Status;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Mutation `etfAskProductQuestion` — ask a question about a product.
 */
class AskProductQuestion implements ResolverInterface
{
    /**
     * @param Config $config
     * @param ProductLocator $productLocator
     * @param QuestionFactory $questionFactory
     * @param QuestionResource $questionResource
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly ProductLocator $productLocator,
        private readonly QuestionFactory $questionFactory,
        private readonly QuestionResource $questionResource,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $input = $args['input'] ?? [];
        $text = trim((string) ($input['question'] ?? ''));

        $customerId = (int) $context->getUserId();
        $isCustomer = $context->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER && $customerId > 0;

        try {
            if (!$this->config->isEnabled()) {
                throw new LocalizedException(__('Reviews are not available.'));
            }
            if (!$this->config->isFlag(Config::XML_PATH_ENABLE_QA)) {
                throw new LocalizedException(__('Questions are disabled.'));
            }
            $productId = $this->productLocator->resolveId($input);
            if ($text === '') {
                throw new GraphQlInputException(__('Please enter your question.'));
            }

            $store = $context->getExtensionAttributes()->getStore();
            $storeId = $store instanceof StoreInterface ? (int) $store->getId() : 0;

            $authorName = $this->resolveAuthorName($isCustomer, $customerId, (string) ($input['nickname'] ?? ''));
            $autoApprove = $this->config->isFlag(Config::XML_PATH_AUTO_APPROVE);

            $question = $this->questionFactory->create();
            $question->setProductId($productId)
                ->setStoreId($storeId)
                ->setCustomerId($isCustomer ? $customerId : null)
                ->setAuthorName($authorName)
                ->setQuestion($text)
                ->setHelpfulCount(0)
                ->setStatus($autoApprove ? Status::APPROVED : Status::PENDING);
            $this->questionResource->save($question);

            return [
                'success' => true,
                'message' => $autoApprove
                    ? (string) __('Your question has been posted.')
                    : (string) __('Thank you! Your question is awaiting moderation.'),
            ];
        } catch (GraphQlInputException $e) {
            throw $e;
        } catch (LocalizedException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => (string) __('Could not submit your question.')];
        }
    }

    /**
     * @param bool $isCustomer
     * @param int $customerId
     * @param string $nickname
     * @return string
     */
    private function resolveAuthorName(bool $isCustomer, int $customerId, string $nickname): string
    {
        if ($isCustomer) {
            try {
                $customer = $this->customerRepository->getById($customerId);
                $name = trim($customer->getFirstname() . ' ' . $customer->getLastname());
                if ($name !== '') {
                    return $name;
                }
            } catch (\Exception $e) {
                // Customer record not loadable — fall back to the supplied nickname below.
                $name = '';
            }
        }
        $nickname = trim($nickname);
        return $nickname !== '' ? $nickname : (string) __('Guest');
    }
}
