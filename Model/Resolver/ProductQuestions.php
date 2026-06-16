<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Model\Resolver;

use ETechFlow\AdvancedProductReviews\Model\Config;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Answer\CollectionFactory as AnswerCollectionFactory;
use ETechFlow\AdvancedProductReviews\Model\ResourceModel\Question\CollectionFactory as QuestionCollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Resolves `etfProductQuestions` — approved Q&A (with answers) for a product.
 */
class ProductQuestions implements ResolverInterface
{
    /**
     * @param ProductLocator $productLocator
     * @param QuestionCollectionFactory $questionCollectionFactory
     * @param AnswerCollectionFactory $answerCollectionFactory
     * @param Config $config
     */
    public function __construct(
        private readonly ProductLocator $productLocator,
        private readonly QuestionCollectionFactory $questionCollectionFactory,
        private readonly AnswerCollectionFactory $answerCollectionFactory,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $args = $args ?? [];
        if (!$this->config->isEnabled()) {
            return [
                'items' => [],
                'total_count' => 0,
                'page_info' => ['current_page' => 1, 'page_size' => 0, 'total_pages' => 0],
            ];
        }
        $productId = $this->productLocator->resolveId($args);
        $store = $context->getExtensionAttributes()->getStore();
        $storeId = $store instanceof StoreInterface ? (int) $store->getId() : 0;

        $pageSize = max(1, min((int) ($args['pageSize'] ?? 10), 100));
        $currentPage = max(1, (int) ($args['currentPage'] ?? 1));

        $collection = $this->questionCollectionFactory->create()
            ->addProductFilter($productId, $storeId);
        $totalCount = (int) $collection->getSize();
        $collection->setPageSize($pageSize)->setCurPage($currentPage);

        $questions = array_values($collection->getItems());
        $answersByQuestion = $this->loadAnswers(
            array_map(static fn ($q) => (int) $q->getQuestionId(), $questions)
        );

        $items = [];
        foreach ($questions as $question) {
            $qid = (int) $question->getQuestionId();
            $items[] = [
                'question_id' => $qid,
                'author_name' => (string) $question->getAuthorName(),
                'question' => (string) $question->getQuestion(),
                'helpful_count' => (int) $question->getHelpfulCount(),
                'created_at' => (string) $question->getCreatedAt(),
                'answers' => $answersByQuestion[$qid] ?? [],
            ];
        }

        return [
            'items' => $items,
            'total_count' => $totalCount,
            'page_info' => [
                'current_page' => $currentPage,
                'page_size' => $pageSize,
                'total_pages' => (int) ceil($totalCount / $pageSize),
            ],
        ];
    }

    /**
     * Approved answers grouped by question id (admin first, then newest).
     *
     * @param int[] $questionIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function loadAnswers(array $questionIds): array
    {
        if (!$questionIds) {
            return [];
        }
        $collection = $this->answerCollectionFactory->create();
        $collection->addFieldToFilter('question_id', ['in' => $questionIds])
            ->addFieldToFilter('status', 2)
            ->setOrder('is_admin', 'DESC')
            ->setOrder('created_at', 'DESC');

        $out = [];
        foreach ($collection->getItems() as $answer) {
            $qid = (int) $answer->getQuestionId();
            $out[$qid][] = [
                'answer_id' => (int) $answer->getAnswerId(),
                'author_name' => (string) $answer->getAuthorName(),
                'answer' => (string) $answer->getAnswer(),
                'is_admin' => (bool) $answer->getIsAdmin(),
                'helpful_count' => (int) $answer->getHelpfulCount(),
                'created_at' => (string) $answer->getCreatedAt(),
            ];
        }
        return $out;
    }
}
