<?php
declare(strict_types=1);

namespace ETechFlow\AdvancedProductReviews\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DebugReviewMedia extends Command
{
    private const REVIEW_ID = 'review-id';
    
    public function __construct(
        private readonly ResourceConnection $resource,
        ?string $name = null
    ) {
        parent::__construct($name);
    }
    
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
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reviewId = $input->getOption(self::REVIEW_ID);
        $connection = $this->resource->getConnection();
        
        $output->writeln('<info>ETechFlow Review Media Debugger</info>');
        $output->writeln(str_repeat('=', 60));
        
        $mediaTable = $this->resource->getTableName('etechflow_review_media');
        if (!$connection->isTableExists($mediaTable)) {
            $output->writeln('<error>Table etechflow_review_media does NOT exist!</error>');
            return Command::FAILURE;
        }
        
        $output->writeln('<info>✓ Media table exists</info>');
        $output->writeln('');
        
        $select = $connection->select()
            ->from(['rm' => $mediaTable])
            ->joinLeft(
                ['r' => $this->resource->getTableName('review')],
                'rm.review_id = r.review_id',
                ['status_id' => 'r.status_id']
            )
            ->joinLeft(
                ['rd' => $this->resource->getTableName('review_detail')],
                'rm.review_id = rd.review_id',
                ['title' => 'rd.title', 'nickname' => 'rd.nickname']
            )
            ->order('rm.review_id DESC')
            ->limit(20);
        
        if ($reviewId) {
            $select->where('rm.review_id = ?', (int) $reviewId);
        }
        
        $mediaRecords = $connection->fetchAll($select);
        
        if (empty($mediaRecords)) {
            $output->writeln('<error>No media records found in database!</error>');
            $output->writeln('<comment>This means images/videos are NOT being saved to database.</comment>');
            return Command::SUCCESS;
        }
        
        $output->writeln(sprintf('<info>Found %d media record(s)</info>', count($mediaRecords)));
        $output->writeln('');
        
        foreach ($mediaRecords as $record) {
            $output->writeln(sprintf('<info>Review ID: %d</info> - "%s" by %s', 
                $record['review_id'], 
                $record['title'] ?? 'N/A',
                $record['nickname'] ?? 'Unknown'
            ));
            $output->writeln(sprintf('  Media ID: %d', $record['media_id']));
            $output->writeln(sprintf('  Type: %s', $record['media_type']));
            $output->writeln(sprintf('  File Path: %s', $record['file_path'] ?: '<empty>'));
            $output->writeln(sprintf('  Review Status: %s', $this->getStatusLabel($record['status_id'])));
            
            if ($record['file_path']) {
                $absolutePath = '/var/www/html/pub/media/' . ltrim($record['file_path'], '/');
                $exists = file_exists($absolutePath);
                $output->writeln(sprintf('  File Exists: %s', $exists ? '<info>YES</info>' : '<error>NO</error>'));
                if (!$exists) {
                    $output->writeln(sprintf('    Expected at: %s', $absolutePath));
                }
            } else {
                $output->writeln('  <error>File path is EMPTY in database!</error>');
            }
            $output->writeln('');
        }
        
        return Command::SUCCESS;
    }
    
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
