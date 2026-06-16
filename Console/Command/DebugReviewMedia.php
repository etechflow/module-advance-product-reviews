<?php
/**
 * ETechFlow_AdvancedProductReviews
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Debug command to check review media storage and display issues.
 */
class DebugReviewMedia extends Command
{
    private const REVIEW_ID = 'review-id';

    /**
     * @param ResourceConnection $resource
     * @param string|null $name
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('etechflow:debug:review-media')
            ->setDescription('Debug review media (images/videos) storage and display')
            ->addOption(
                self::REVIEW_ID,
                'r',
                InputOption::VALUE_OPTIONAL,
                'Specific review ID to check (default: all recent)'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reviewId = $input->getOption(self::REVIEW_ID);
        $connection = $this->resource->getConnection();

        $output->writeln('<info>ETechFlow Review Media Debugger</info>');
        $output->writeln(str_repeat('=', 60));

        // Check if media table exists
        $mediaTable = $this->resource->getTableName('etechflow_review_media');
        if (!$connection->isTableExists($mediaTable)) {
            $output->writeln('<error>Table etechflow_review_media does NOT exist!</error>');
            $output->writeln('<comment>Run: php bin/magento setup:upgrade</comment>');
            return Command::FAILURE;
        }

        $output->writeln('<info>✓ Media table exists</info>');
        $output->writeln('');

        // Get reviews with media
        $select = $connection->select()
            ->from(['rm' => $mediaTable])
            ->joinLeft(
                ['r' => $this->resource->getTableName('review')],
                'rm.review_id = r.review_id',
                ['title' => 'r.title', 'nickname' => 'r.nickname', 'status_id' => 'r.status_id']
            )
            ->order('rm.review_id DESC');

        if ($reviewId) {
            $select->where('rm.review_id = ?', (int) $reviewId);
        } else {
            $select->limit(20);
        }

        $mediaRecords = $connection->fetchAll($select);

        if (empty($mediaRecords)) {
            $output->writeln('<error>No media records found in database!</error>');
            $output->writeln('');
            $output->writeln('<comment>This means images/videos are not being saved.</comment>');
            $output->writeln('<comment>Check:</comment>');
            $output->writeln('<comment>1. File upload field name in form: etf_media[]</comment>');
            $output->writeln('<comment>2. SaveReviewExtra observer is firing</comment>');
            $output->writeln('<comment>3. MediaUploader is working</comment>');
            $output->writeln('<comment>4. pub/media/etechflow/reviews/ folder permissions</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Found %d media record(s)</info>', count($mediaRecords)));
        $output->writeln('');

        foreach ($mediaRecords as $record) {
            $reviewId = $record['review_id'];
            $mediaId = $record['media_id'];
            $mediaType = $record['media_type'];
            $filePath = $record['file_path'];
            $statusId = $record['status_id'];
            $title = $record['title'] ?? 'N/A';

            $output->writeln(sprintf('<info>Review ID: %d</info> - "%s"', $reviewId, $title));
            $output->writeln(sprintf('  Media ID: %d', $mediaId));
            $output->writeln(sprintf('  Type: %s', $mediaType));
            $output->writeln(sprintf('  File Path: %s', $filePath ?: '<empty>'));
            $output->writeln(sprintf('  Review Status: %s', $this->getStatusLabel($statusId)));

            // Check if file exists
            if ($filePath) {
                $absolutePath = BP . '/pub/media/' . ltrim($filePath, '/');
                $exists = file_exists($absolutePath);
                $output->writeln(sprintf('  File Exists: %s', $exists ? '<info>YES</info>' : '<error>NO</error>'));
                if (!$exists) {
                    $output->writeln(sprintf('  <comment>Expected at: %s</comment>', $absolutePath));
                }
            } else {
                $output->writeln('  <error>File path is EMPTY in database!</error>');
            }

            $output->writeln('');
        }

        // Count total media by status
        $output->writeln('<info>Summary by Review Status:</info>');
        $statusSelect = $connection->select()
            ->from(['rm' => $mediaTable], ['count' => 'COUNT(*)'])
            ->joinLeft(
                ['r' => $this->resource->getTableName('review')],
                'rm.review_id = r.review_id',
                ['status_id' => 'r.status_id']
            )
            ->group('r.status_id');

        $statusCounts = $connection->fetchAll($statusSelect);
        foreach ($statusCounts as $stat) {
            $output->writeln(sprintf(
                '  %s: %d media items',
                $this->getStatusLabel($stat['status_id']),
                $stat['count']
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * @param int|null $statusId
     * @return string
     */
    private function getStatusLabel(?int $statusId): string
    {
        return match ((int) $statusId) {
            1 => 'Approved',
            2 => 'Pending',
            3 => 'Not Approved',
            default => 'Unknown',
        };
    }
}
