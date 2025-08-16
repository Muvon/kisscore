<?php declare(strict_types=1);

namespace KC;

use Composer\Script\Event;
use Composer\IO\IOInterface;

/**
 * KC (KissCore) Composer installer and project initializer
 */
class Installer
{
    /**
     * Initialize KC after composer install/update
     * This runs automatically after composer install/update
     */
    public static function init(Event $event): void
    {
        $io = $event->getIO();
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $packageDir = dirname($vendorDir) . '/vendor/muvon/kisscore';

        if (!is_dir($packageDir)) {
            $io->write('<info>KissCore package directory not found, skipping initialization</info>');
            return;
        }

        $io->write('<info>KC initialized successfully</info>');
    }

    /**
     * Create new KC project with skeleton files
     * Run with: composer run-script kisscore-create-project
     */
    public static function createProject(Event $event): void
    {
        $io = $event->getIO();
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $rootDir = dirname($vendorDir);
        $packageDir = $vendorDir . '/muvon/kisscore';

        if (!is_dir($packageDir)) {
            $io->writeError('<error>KissCore package not found</error>');
            return;
        }

        $skelDir = $packageDir . '/skel';
        $binDir = $packageDir . '/bin';

        if (!is_dir($skelDir)) {
            $io->writeError('<error>Skeleton directory not found in KissCore package</error>');
            return;
        }

        // Ask for confirmation
        if (!$io->askConfirmation('<question>This will copy KC skeleton files to your project. Continue? (y/N)</question> ', false)) {
            $io->write('<comment>Project creation cancelled</comment>');
            return;
        }

        // Copy skeleton files
        $io->write('<info>Copying skeleton files...</info>');
        self::copyDirectory($skelDir, $rootDir, $io);

        // Copy bin files to project root bin directory
        $projectBinDir = $rootDir . '/bin';
        if (is_dir($binDir)) {
            $io->write('<info>Copying bin scripts...</info>');
            if (!is_dir($projectBinDir)) {
                mkdir($projectBinDir, 0755, true);
            }
            self::copyDirectory($binDir, $projectBinDir, $io);

            // Make bin files executable
            foreach (glob($projectBinDir . '/*') as $binFile) {
                if (is_file($binFile)) {
                    chmod($binFile, 0755);
                }
            }
        }

        // Create basic directory structure
        $dirs = ['env/backup', 'env/etc', 'env/log', 'env/run', 'env/tmp', 'env/var'];
        foreach ($dirs as $dir) {
            $fullPath = $rootDir . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                $io->write("<info>Created directory: {$dir}</info>");
            }
        }

        // Create .env file if it doesn't exist
        $envFile = $rootDir . '/.env';
        if (!file_exists($envFile)) {
            $envContent = "# KC Environment Configuration\n";
            $envContent .= "APP_DIR=" . $rootDir . "/app\n";
            $envContent .= "CONFIG_DIR=" . $rootDir . "/app/config\n";
            $envContent .= "STATIC_DIR=" . $rootDir . "/app/static\n";
            $envContent .= "LOG_DIR=" . $rootDir . "/env/log\n";
            $envContent .= "TMP_DIR=" . $rootDir . "/env/tmp\n";
            $envContent .= "VAR_DIR=" . $rootDir . "/env/var\n";

            file_put_contents($envFile, $envContent);
            $io->write('<info>Created .env file</info>');
        }

        $io->write('<success>KC project created successfully!</success>');
        $io->write('<comment>Next steps:</comment>');
        $io->write('<comment>1. Configure your app/config files</comment>');
        $io->write('<comment>2. Run: php app/main.php to start your application</comment>');
        $io->write('<comment>3. Check bin/ directory for available tools</comment>');
    }

    /**
     * Recursively copy directory contents
     */
    private static function copyDirectory(string $source, string $destination, IOInterface $io): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                // Don't overwrite existing files
                if (!file_exists($destPath)) {
                    copy($item->getPathname(), $destPath);
                    $io->write("<info>Copied: {$iterator->getSubPathName()}</info>");
                } else {
                    $io->write("<comment>Skipped existing file: {$iterator->getSubPathName()}</comment>");
                }
            }
        }
    }
}
