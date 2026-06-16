<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model;

use ETechFlow\AdvancedProductReviews\Model\ResourceModel\ReviewTranslation as ReviewTranslationResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Cached machine translation of a review for a target language.
 *
 * @method int getReviewId()
 * @method $this setReviewId(int $reviewId)
 * @method string getLanguage()
 * @method $this setLanguage(string $language)
 * @method string|null getTranslatedTitle()
 * @method $this setTranslatedTitle(?string $title)
 * @method string|null getTranslatedDetail()
 * @method $this setTranslatedDetail(?string $detail)
 * @method string|null getTranslatedPros()
 * @method $this setTranslatedPros(?string $pros)
 * @method string|null getTranslatedCons()
 * @method $this setTranslatedCons(?string $cons)
 * @method string|null getEngine()
 * @method $this setEngine(?string $engine)
 */
class ReviewTranslation extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'etechflow_review_translation';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReviewTranslationResource::class);
    }
}
