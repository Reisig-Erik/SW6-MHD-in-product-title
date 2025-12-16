<?php declare(strict_types=1);

namespace LebensmittelMhdManager\Command;

use LebensmittelMhdManager\Service\MhdCustomFieldSynchronizer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mhd:sync-dates',
    description: 'Synchronize MHD dates from manufacturerNumber to custom_product_mhd_date field'
)]
class SyncMhdDatesCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly MhdCustomFieldSynchronizer $synchronizer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Number of products to process per batch',
                self::BATCH_SIZE
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Show what would be changed without making changes'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Update even if custom field already has a value'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('MHD Date Synchronization');

        if ($dryRun) {
            $io->note('Running in DRY-RUN mode - no changes will be made');
        }

        // Count products with manufacturer number
        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('manufacturerNumber', null),
                    new EqualsFilter('manufacturerNumber', ''),
                ]
            )
        );

        $totalProducts = $this->productRepository->searchIds($criteria, $context)->getTotal();
        $io->info(sprintf('Found %d products with manufacturer number to process', $totalProducts));

        if ($totalProducts === 0) {
            $io->success('No products to process');
            return Command::SUCCESS;
        }

        $progressBar = new ProgressBar($output, $totalProducts);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        $offset = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        while ($offset < $totalProducts) {
            $criteria = new Criteria();
            $criteria->addFilter(
                new NotFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('manufacturerNumber', null),
                        new EqualsFilter('manufacturerNumber', ''),
                    ]
                )
            );
            $criteria->setOffset($offset);
            $criteria->setLimit($batchSize);

            $products = $this->productRepository->search($criteria, $context);

            $updates = [];

            foreach ($products as $product) {
                $manufacturerNumber = $product->getManufacturerNumber();
                $existingCustomFields = $product->getCustomFields() ?? [];
                $existingMhdDate = $existingCustomFields['custom_product_mhd_date'] ?? null;

                // Skip if already has value and not forcing
                if ($existingMhdDate !== null && !$force) {
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                $mhdDateValue = $this->synchronizer->convertToCustomFieldValue($manufacturerNumber);
                $mhdDaysValue = $this->synchronizer->calculateDaysUntilMhd($manufacturerNumber);

                if ($mhdDateValue === null) {
                    // Invalid MHD format, skip
                    $skipped++;
                    $progressBar->advance();
                    continue;
                }

                $newCustomFields = $existingCustomFields;
                $newCustomFields['custom_product_mhd_date'] = $mhdDateValue;
                $newCustomFields['custom_product_mhd_days'] = $mhdDaysValue;

                $updates[] = [
                    'id' => $product->getId(),
                    'customFields' => $newCustomFields,
                ];

                $updated++;
                $progressBar->setMessage(sprintf('Processing: %s', $product->getProductNumber()));
                $progressBar->advance();
            }

            if (!$dryRun && !empty($updates)) {
                try {
                    $this->productRepository->update($updates, $context);
                } catch (\Exception $e) {
                    $errors += count($updates);
                    $io->error(sprintf('Batch update failed: %s', $e->getMessage()));
                }
            }

            $offset += $batchSize;
        }

        $progressBar->setMessage('Done!');
        $progressBar->finish();

        $io->newLine(2);
        $io->success(sprintf(
            'Synchronization complete: %d updated, %d skipped, %d errors',
            $updated,
            $skipped,
            $errors
        ));

        if ($dryRun) {
            $io->note('This was a dry run - run without --dry-run to apply changes');
        }

        return Command::SUCCESS;
    }
}
