<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Resolver;

use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewComment as ReviewCommentResource;
use ETechFlow\AdvancedProductReviews\Model\ReviewCommentFactory;
use ETechFlow\AdvancedProductReviews\Model\Source\Status;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Mutation `etfPostReviewComment` — post a comment on a review.
 */
class PostReviewComment implements ResolverInterface
{
    /**
     * @param Config $config
     * @param ReviewCommentFactory $commentFactory
     * @param ReviewCommentResource $commentResource
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly ReviewCommentFactory $commentFactory,
        private readonly ReviewCommentResource $commentResource,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $input = $args['input'] ?? [];
        $reviewId = (int) ($input['review_id'] ?? 0);
        $text = trim((string) ($input['comment'] ?? ''));

        $customerId = (int) $context->getUserId();
        $isCustomer = $context->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER && $customerId > 0;

        try {
            if (!$this->config->isEnabled()) {
                throw new LocalizedException(__('Reviews are not available.'));
            }
            if (!$this->config->isFlag(Config::XML_PATH_ENABLE_COMMENTS)) {
                throw new LocalizedException(__('Comments are disabled.'));
            }
            if (!$isCustomer && !$this->config->isFlag(Config::XML_PATH_GUEST_COMMENTS)) {
                throw new LocalizedException(__('Please sign in to leave a comment.'));
            }
            if ($reviewId <= 0 || $text === '') {
                throw new GraphQlInputException(__('Please provide "review_id" and a "comment".'));
            }

            $authorName = $this->resolveAuthorName($isCustomer, $customerId, (string) ($input['nickname'] ?? ''));
            $autoApprove = $this->config->isFlag(Config::XML_PATH_AUTO_APPROVE);

            $comment = $this->commentFactory->create();
            $comment->setReviewId($reviewId)
                ->setCustomerId($isCustomer ? $customerId : null)
                ->setAuthorName($authorName)
                ->setComment($text)
                ->setIsAdminReply(false)
                ->setStatus($autoApprove ? Status::APPROVED : Status::PENDING);
            $this->commentResource->save($comment);

            return [
                'success' => true,
                'message' => $autoApprove
                    ? (string) __('Your comment has been posted.')
                    : (string) __('Thank you! Your comment is awaiting moderation.'),
            ];
        } catch (GraphQlInputException $e) {
            throw $e;
        } catch (LocalizedException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => (string) __('Could not save your comment.')];
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
