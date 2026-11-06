<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use SQLite3;

/**
 * A backup you have not restored is a hope. This opens one, read only, and proves it: the file is sound, the tables are
 * there, the work is there, and this server's key can read it.
 */
class VerifyRestore extends Command
{
    private const REQUIRED = ['migrations', 'users', 'organizations', 'agent_deployments', 'agent_config_versions', 'agent_runs', 'agent_run_steps', 'agent_effects', 'agent_approvals', 'audit_logs'];

    protected $signature = 'agents:verify-restore {file? : A backup. The newest in storage/backups if left out}';

    protected $description = 'Prove a database backup can be restored and read';

    public function handle(): int
    {
        $file = $this->argument('file') ?? collect(glob(storage_path('backups/agents-*.sqlite')) ?: [])->sort()->last();

        if (! is_string($file) || ! is_file($file)) {
            $this->error('There is no backup to check.');

            return self::FAILURE;
        }

        $database = new SQLite3($file, SQLITE3_OPEN_READONLY);
        $problems = [...$this->soundness($database), ...$this->tables($database), ...$this->readable($database)];
        $database->close();

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        if ($problems === []) {
            $this->info(basename($file).' can be restored.');
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    private function soundness(SQLite3 $database): array
    {
        $check = @$database->querySingle('PRAGMA integrity_check');

        return $check === 'ok' ? [] : ['The file failed its integrity check.'];
    }

    /** @return list<string> */
    private function tables(SQLite3 $database): array
    {
        $problems = [];

        foreach (self::REQUIRED as $table) {
            if ((int) @$database->querySingle("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='{$table}'") === 0) {
                $problems[] = "The table {$table} is missing.";
            } else {
                $this->line("{$table}: ".@$database->querySingle("SELECT count(*) FROM {$table}"));
            }
        }

        return $problems;
    }

    /** @return list<string> */
    private function readable(SQLite3 $database): array
    {
        $table = (int) @$database->querySingle("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='agent_runs'");
        $sample = $table === 0 ? null : @$database->querySingle('SELECT input FROM agent_runs WHERE input IS NOT NULL ORDER BY id DESC LIMIT 1');

        if (! is_string($sample)) {
            return [];
        }

        try {
            Crypt::decryptString($sample);
        } catch (DecryptException) {
            return ['This server\'s APP_KEY cannot read the data in this backup.'];
        }

        return [];
    }
}
