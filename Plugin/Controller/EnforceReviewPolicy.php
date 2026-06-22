<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Plugin\Controller;

use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\Service\VerifiedBuyer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Session\Generic as ReviewSession;
use Magento\Review\Controller\Product\Post;

/**
 * Server-side enforcement of the storefront review-submission policy.
 *
 * Wraps the core review Post controller so that the admin "Allow Guest Reviews"
 * and "Require Purchase to Review" toggles genuinely gate submissions instead of
 * relying on the form being hidden (which a determined client can bypass).
 *
 * Rejected submissions are bounced back to the originating page with an error
 * message; the shopper's input is stashed in the review session so the core
 * form repopulates (mirrors how core handles its own validation failures).
 */
class EnforceReviewPolicy
{
    /** Guest-supplied email used to verify a guest purchase. */
    private const FIELD_EMAIL = 'etf_email';

    /**
     * @param Config $config
     * @param CustomerSession $customerSession
     * @param VerifiedBuyer $verifiedBuyer
     * @param MessageManager $messageManager
     * @param RedirectFactory $redirectFactory
     * @param ReviewSession $reviewSession
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly VerifiedBuyer $verifiedBuyer,
        private readonly MessageManager $messageManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly ReviewSession $reviewSession,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Gate the review submission before core processes it.
     *
     * @param Post $subject
     * @param callable $proceed
     * @return mixed
     */
    public function aroundExecute(Post $subject, callable $proceed)
    {
        // Module disabled (or unlicensed) -> impose no policy; let core behave normally.
        if (!$this->config->isEnabled()) {
            return $proceed();
        }

        // Only police actual submissions; ignore empty/non-POST hits.
        $data = $this->request->getPostValue();
        if (empty($data)) {
            return $proceed();
        }

        $isLoggedIn = $this->customerSession->isLoggedIn();
        $productId  = (int) $this->request->getParam('id');

        // 1) Guest gate.
        if (!$isLoggedIn && !$this->config->isGuestReviewsAllowed()) {
            return $this->reject(
                (string) __('Please sign in to your account to write a review.'),
                $data
            );
        }

        // 2) Purchase-required gate.
        if ($this->config->isPurchaseRequired() && $productId > 0
            && !$this->reviewerHasPurchased($isLoggedIn, $productId, $data)
        ) {
            return $this->reject(
                $isLoggedIn
                    ? (string) __('Only customers who purchased this product can write a review.')
                    : (string) __('Only verified buyers can review this product. Please enter the email address you used at checkout.'),
                $data
            );
        }

        // Submission passes policy → let core process it, then correct the
        // "submitted for moderation" message when the review was auto-approved.
        $result = $proceed();
        $this->adjustModerationMessage();
        return $result;
    }

    /**
     * When reviews are auto-approved, core's "submitted for moderation" notice is
     * misleading (the review is already published). Rewrite it to a published
     * notice. When auto-approve is off, the moderation notice is left as-is.
     *
     * @return void
     */
    private function adjustModerationMessage(): void
    {
        if (!$this->config->isFlag(Config::XML_PATH_AUTO_APPROVE)) {
            return;
        }
        try {
            foreach ($this->messageManager->getMessages()->getItems() as $message) {
                if ($message->getType() === MessageInterface::TYPE_SUCCESS
                    && stripos((string) $message->getText(), 'moderation') !== false
                ) {
                    $message->setText((string) __('Thank you! Your review has been published.'));
                }
            }
        } catch (\Throwable $e) {
            // Cosmetic only — never disrupt the submission over a message tweak.
        }
    }

    /**
     * Whether the current reviewer is a verified purchaser of the product.
     *
     * @param bool $isLoggedIn
     * @param int $productId
     * @param array $data
     * @return bool
     */
    private function reviewerHasPurchased(bool $isLoggedIn, int $productId, array $data): bool
    {
        if ($isLoggedIn) {
            return $this->verifiedBuyer->hasPurchased(
                (int) $this->customerSession->getCustomerId(),
                $productId
            );
        }

        $email = trim((string) ($data[self::FIELD_EMAIL] ?? ''));
        if ($email === '') {
            return false;
        }
        return $this->verifiedBuyer->hasPurchasedByEmail($email, $productId);
    }

    /**
     * Bounce the submission back with a message, preserving the shopper's input.
     *
     * @param string $message
     * @param array $data
     * @return Redirect
     */
    private function reject(string $message, array $data): Redirect
    {
        $this->messageManager->addErrorMessage($message);
        // Core reads this back via getFormData(true) to repopulate the form.
        $this->reviewSession->setFormData($data);

        $redirect = $this->redirectFactory->create();
        $redirect->setRefererUrl();
        return $redirect;
    }
}
