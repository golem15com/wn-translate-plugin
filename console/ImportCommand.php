<?php

namespace Golem15\Translate\Console;

use Illuminate\Console\Command;
use Golem15\Translate\Models\Message;

class ImportCommand extends Command
{
    /**
     * @var string|null The default command name for lazy loading.
     */
    protected static $defaultName = 'translate:import';

    /**
     * @var string The name and signature of this command.
     */
    protected $signature = 'translate:import
        {--path= : Path to import file (default: translations.json in project root)}
        {--format=json : Import format: json or csv}
        {--overwrite : Overwrite existing translations}';

    /**
     * @var string The console command description.
     */
    protected $description = 'Import translations from a JSON or CSV file.';

    public function handle()
    {
        $format = $this->option('format');
        $defaultFile = $format === 'csv' ? 'translations.csv' : 'translations.json';
        $path = $this->option('path') ?: base_path($defaultFile);
        $overwrite = $this->option('overwrite');

        if (!file_exists($path)) {
            $this->output->error("File not found: {$path}");
            return 1;
        }

        if ($format === 'csv') {
            $data = $this->parseCsv($path);
        } else {
            $data = $this->parseJson($path);
        }

        if (empty($data)) {
            $this->output->warning('No translations found in file.');
            return 1;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($data as $item) {
            $code = $item['code'] ?? null;
            $messageData = $item['message_data'] ?? [];

            if (!$code || empty($messageData)) {
                $skipped++;
                continue;
            }

            $message = Message::where('code', $code)->first();

            if ($message) {
                if ($overwrite) {
                    $message->message_data = $messageData;
                    $message->save();
                    $updated++;
                } else {
                    // Merge: only add missing locales
                    $existingData = $message->message_data ?: [];
                    $merged = false;

                    foreach ($messageData as $locale => $text) {
                        if (!isset($existingData[$locale]) || empty($existingData[$locale])) {
                            $existingData[$locale] = $text;
                            $merged = true;
                        }
                    }

                    if ($merged) {
                        $message->message_data = $existingData;
                        $message->save();
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }
            } else {
                Message::create([
                    'code' => $code,
                    'message_data' => $messageData,
                    'found' => true,
                ]);
                $created++;
            }
        }

        $this->output->success("Import complete: {$created} created, {$updated} updated, {$skipped} skipped.");

        if ($created > 0 || $updated > 0) {
            $this->output->note('You may need to run cache:clear for updated messages to take effect.');
        }

        return 0;
    }

    /**
     * Parse JSON file
     */
    protected function parseJson($path)
    {
        $content = file_get_contents($path);
        return json_decode($content, true) ?: [];
    }

    /**
     * Parse CSV file
     */
    protected function parseCsv($path)
    {
        $data = [];
        $handle = fopen($path, 'r');

        if (!$handle) {
            return [];
        }

        // Remove BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers || !in_array('code', $headers)) {
            fclose($handle);
            return [];
        }

        // Find column indices
        $codeIndex = array_search('code', $headers);
        $localeIndices = [];
        foreach ($headers as $index => $header) {
            if ($header !== 'code') {
                $localeIndices[$header] = $index;
            }
        }

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            $code = $row[$codeIndex] ?? null;
            if (!$code) {
                continue;
            }

            $messageData = [];
            foreach ($localeIndices as $locale => $index) {
                if (isset($row[$index]) && $row[$index] !== '') {
                    $messageData[$locale] = $row[$index];
                }
            }

            if (!empty($messageData)) {
                $data[] = [
                    'code' => $code,
                    'message_data' => $messageData,
                ];
            }
        }

        fclose($handle);
        return $data;
    }
}
