<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCertificateJob;
use App\Models\FileManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessExistingCertificatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:process-with-ai 
                            {--limit=10 : Number of files to process}
                            {--days=30 : Process files from the last N days}
                            {--force : Force processing even if already processed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process existing certificate images with AI services';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $days = $this->option('days');
        $force = $this->option('force');

        $this->info("Starting AI processing of existing certificate files...");
        $this->info("Limit: {$limit} files");
        $this->info("Days: Last {$days} days");
        $this->info("Force: " . ($force ? 'Yes' : 'No'));

        // Get supported formats
        $supportedFormats = config('ai.processing.supported_formats', ['jpg', 'jpeg', 'png', 'pdf']);
        
        // Query files to process
        $query = FileManager::whereIn('extension_file', $supportedFormats)
            ->where('document_type', 'ATTACHED')
            ->where('created_at', '>=', now()->subDays($days));

        if (!$force) {
            // Only process files that haven't been processed yet
            // You might want to add a column to track AI processing status
            $query->whereNull('ai_processed_at');
        }

        $files = $query->limit($limit)->get();

        if ($files->isEmpty()) {
            $this->warn("No files found to process.");
            return 0;
        }

        $this->info("Found {$files->count()} files to process.");

        $bar = $this->output->createProgressBar($files->count());
        $bar->start();

        $processed = 0;
        $errors = 0;

        foreach ($files as $file) {
            try {
                // Check if file exists
                $disk = Storage::disk('attachment');
                if (!$disk->exists($file->file_path)) {
                    $this->error("\nFile not found: {$file->file_path}");
                    $errors++;
                    $bar->advance();
                    continue;
                }

                // Dispatch AI processing job
                ProcessCertificateJob::dispatch(
                    $file->file_path,
                    1, // System user ID, adjust as needed
                    $file->certificate_request_id,
                    [
                        'file_id' => $file->id,
                        'generate_email' => false,
                        'auto_populate_data' => true,
                        'command_processing' => true
                    ]
                );

                $processed++;
                $bar->advance();

            } catch (\Exception $e) {
                $this->error("\nError processing file {$file->id}: " . $e->getMessage());
                $errors++;
                $bar->advance();
            }
        }

        $bar->finish();

        $this->newLine(2);
        $this->info("Processing completed!");
        $this->info("Files processed: {$processed}");
        
        if ($errors > 0) {
            $this->error("Files with errors: {$errors}");
        }

        $this->info("AI processing jobs have been dispatched. Check the queue status for progress.");

        return 0;
    }
}
