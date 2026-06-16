<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Review translation resource model.
 */
class ReviewTranslation extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('etechflow_review_translation', 'translation_id');
    }

    /**
     * Fetch a cached translation row for a review in a given language.
     *
     * @param int $reviewId
     * @param string $language Target language code (e.g. "de")
     * @return array<string,mixed>|null Row data, or null when not cached
     */
    public function getTranslationData(int $reviewId, string $language): ?array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('review_id = ?', $reviewId)
            ->where('language = ?', $language)
            ->limit(1);

        $row = $connection->fetchRow($select);
        return $row ?: null;
    }
}
