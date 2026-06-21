<?php

namespace Golem15\Translate\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Golem15\Translate\Models\Message;

#[AsCommand(name: 'translate:export', description: 'Export all translations to a JSON or CSV file.')]
class ExportCommand extends Command
{
    /**
     * @var string The name and signature of this command.
     */
    protected $signature = 'translate:export
        {--path= : Path to export file (default: translations.json in project root)}
        {--format=json : Export format: json or csv}';

    /**
     * @var string The console command description.
     */
    protected $description = 'Export all translations to a JSON or CSV file.';

    public function handle()
    {
        $format = $this->option('format');
        $defaultFile = $format === 'csv' ? 'translations.csv' : 'translations.json';
        $path = $this->option('path') ?: base_path($defaultFile);

        $messages = Message::all();

        if ($messages->isEmpty()) {
            $this->output->warning('No translations found in database.');
            return 1;
        }

        if ($format === 'csv') {
            $this->exportCsv($messages, $path);
        } else {
            $this->exportJson($messages, $path);
        }

        $this->output->success("Exported {$messages->count()} translations to: {$path}");
        return 0;
    }

    /**
     * Export translations to JSON format
     */
    protected function exportJson($messages, $path)
    {
        $export = [];

        foreach ($messages as $msg) {
            $export[] = [
                'code' => $msg->code,
                'message_data' => $msg->message_data,
            ];
        }

        file_put_contents(
            $path,
            json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Export translations to CSV format
     */
    protected function exportCsv($messages, $path)
    {
        // Collect all locales used
        $locales = ['x']; // Default locale first
        foreach ($messages as $msg) {
            if (is_array($msg->message_data)) {
                foreach (array_keys($msg->message_data) as $locale) {
                    if (!in_array($locale, $locales)) {
                        $locales[] = $locale;
                    }
                }
            }
        }

        $output = fopen($path, 'w');

        // BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");

        // Header row
        fputcsv($output, array_merge(['code'], $locales));

        // Data rows
        foreach ($messages as $msg) {
            $row = [$msg->code];
            foreach ($locales as $locale) {
                $row[] = $msg->message_data[$locale] ?? '';
            }
            fputcsv($output, $row);
        }

        fclose($output);
    }
}
