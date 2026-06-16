<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Resolver;

use ETechFlow\AdvancedProductReviews\Api\ReviewExtraRepositoryInterface;
use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewVote as ReviewVoteResource;
use ETechFlow\AdvancedProductReviews\Model\ReviewVoteFactory;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\HTTP\Header as HttpHeader;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * Mutation `etfVoteReviewHelpful` — record a helpful / not-helpful vote.
 */
class VoteReviewHelpful implements ResolverInterface
{
    /**
     * @param ReviewVoteFactory $voteFactory
     * @param ReviewVoteResource $voteResource
     * @param ReviewExtraRepositoryInterface $extraRepository
     * @param RemoteAddress $remoteAddress
     * @param HttpHeader $httpHeader
     * @param Config $config
     */
    public function __construct(
        private readonly ReviewVoteFactory $voteFactory,
        private readonly ReviewVoteResource $voteResource,
        private readonly ReviewExtraRepositoryInterface $extraRepository,
        private readonly RemoteAddress $remoteAddress,
        private readonly HttpHeader $httpHeader,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!$this->config->isEnabled()) {
            return ['success' => false, 'message' => (string) __('Reviews are not available.')];
        }
        $input = $args['input'] ?? [];
        $reviewId = (int) ($input['review_id'] ?? 0);
        if ($reviewId <= 0) {
            throw new GraphQlInputException(__('A valid "review_id" is required.'));
        }
        $isHelpful = (bool) ($input['helpful'] ?? false);

        $customerId = (int) $context->getUserId();
        $isCustomer = $context->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER && $customerId > 0;
        $visitorHash = $this->buildVisitorHash();

        try {
            if ($isCustomer && $this->voteResource->hasCustomerVoted($reviewId, $customerId)) {
                throw new LocalizedException(__('You have already voted on this review.'));
            }
            if (!$isCustomer && $this->voteResource->hasGuestVoted($reviewId, $visitorHash)) {
                throw new LocalizedException(__('You have already voted on this review.'));
            }

            $vote = $this->voteFactory->create();
            $vote->setReviewId($reviewId)
                ->setCustomerId($isCustomer ? $customerId : null)
                ->setVisitorHash($visitorHash)
                ->setIsHelpful($isHelpful);
            $this->voteResource->save($vote);

            $extra = $this->extraRepository->getByReviewId($reviewId);
            if ($isHelpful) {
                $extra->setHelpfulCount($extra->getHelpfulCount() + 1);
            } else {
                $extra->setNotHelpfulCount($extra->getNotHelpfulCount() + 1);
            }
            $this->extraRepository->save($extra);

            return [
                'success' => true,
                'message' => (string) __('Thanks for your feedback.'),
                'helpful_count' => $extra->getHelpfulCount(),
                'not_helpful_count' => $extra->getNotHelpfulCount(),
            ];
        } catch (LocalizedException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => (string) __('Could not record your vote.')];
        }
    }

    /**
     * Privacy-preserving guest dedupe hash.
     *
     * @return string
     */
    private function buildVisitorHash(): string
    {
        $ip = (string) $this->remoteAddress->getRemoteAddress();
        $ua = (string) $this->httpHeader->getHttpUserAgent();
        return hash('sha256', $ip . '|' . $ua);
    }
}
