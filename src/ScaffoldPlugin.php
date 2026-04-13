<?php

declare(strict_types=1);

namespace Scafera\Scaffold;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class ScaffoldPlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'scaffold',
            ScriptEvents::POST_UPDATE_CMD => 'scaffold',
            ScriptEvents::POST_CREATE_PROJECT_CMD => 'onCreateProject',
        ];
    }

    public function scaffold(Event $event): void
    {
        $this->io->write('<info>Scafera: scaffolding project files...</info>');

        $projectDir = getcwd();
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $disabledFiles = $this->getDisabledFiles();

        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();

        /** @var array<string, array{source: string, package: string}> */
        $fileMap = [];

        /** @var array<string, array{source: string, package: string}> */
        $initialFileMap = [];

        /** @var array<string, string> */
        $targetMap = [];

        foreach ($localRepo->getPackages() as $package) {
            $extra = $package->getExtra();
            $packagePath = $vendorDir . '/' . $package->getName();

            if (isset($extra['scafera-scaffold']['files'])) {
                foreach ($extra['scafera-scaffold']['files'] as $logicalKey => $source) {
                    $sourceFile = $packagePath . '/' . $source;

                    if (!is_file($sourceFile)) {
                        $this->io->write(sprintf(
                            '  <warning>Source not found: %s (%s)</warning>',
                            $source,
                            $package->getName(),
                        ));
                        continue;
                    }

                    $fileMap[$logicalKey] = [
                        'source' => $sourceFile,
                        'package' => $package->getName(),
                    ];
                }
            }

            if (isset($extra['scafera-scaffold']['initial-files'])) {
                foreach ($extra['scafera-scaffold']['initial-files'] as $target => $source) {
                    $sourceFile = $packagePath . '/' . $source;

                    if (!is_file($sourceFile)) {
                        $this->io->write(sprintf(
                            '  <warning>Source not found: %s (%s)</warning>',
                            $source,
                            $package->getName(),
                        ));
                        continue;
                    }

                    $initialFileMap[$target] = [
                        'source' => $sourceFile,
                        'package' => $package->getName(),
                    ];
                }
            }

            if (isset($extra['scafera-scaffold']['target-map'])) {
                foreach ($extra['scafera-scaffold']['target-map'] as $logicalKey => $target) {
                    $targetMap[$logicalKey] = $target;
                }
            }
        }

        ksort($fileMap);

        $scaffolded = 0;

        foreach ($fileMap as $logicalKey => $entry) {
            $target = $targetMap[$logicalKey] ?? $logicalKey;

            if (isset($disabledFiles[$target])) {
                $this->io->write(sprintf('  <comment>Skipped: %s (disabled)</comment>', $target));
                continue;
            }

            $targetFile = $projectDir . '/' . $target;
            $targetDir = dirname($targetFile);

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            copy($entry['source'], $targetFile);
            $scaffolded++;

            $this->io->write(sprintf(
                '  <info>%s</info> → %s (from %s)',
                $logicalKey,
                $target,
                $entry['package'],
            ));
        }

        ksort($initialFileMap);

        foreach ($initialFileMap as $target => $entry) {
            $targetFile = $projectDir . '/' . $target;

            if (is_file($targetFile)) {
                $this->io->write(sprintf('  <comment>Skipped: %s (already exists)</comment>', $target));
                continue;
            }

            $targetDir = dirname($targetFile);

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            copy($entry['source'], $targetFile);
            $scaffolded++;

            $this->io->write(sprintf(
                '  <info>%s</info> (initial, from %s)',
                $target,
                $entry['package'],
            ));
        }

        $this->io->write(sprintf('<info>Scafera: %d file(s) scaffolded.</info>', $scaffolded));
    }

    public function onCreateProject(Event $event): void
    {
        $this->generateConfigFiles();

        $templateName = $this->composer->getPackage()->getName();

        $this->io->write('');
        $this->io->write('  <bg=blue;fg=white>                                                           </>');
        $this->io->write('  <bg=blue;fg=white>  Congratulations, you have installed the Scafera framework  </>');
        $this->io->write(sprintf('  <bg=blue;fg=white>  from the %s template!%s</>', $templateName, str_repeat(' ', max(0, 37 - strlen($templateName)))));
        $this->io->write('  <bg=blue;fg=white>                                                           </>');
        $this->io->write('');
        $this->io->write('  Next steps:');
        $this->io->write('    * Run the validator:  <info>vendor/bin/scafera validate</info>');
        $this->io->write('    * Start coding:       Create controllers in <info>src/Controller/</info>');
        $this->io->write('    * Run tests:          <info>vendor/bin/phpunit</info>');
        $this->io->write('');
    }

    private function generateConfigFiles(): void
    {
        $projectDir = getcwd();
        $configDir = $projectDir . '/config';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $this->generateConfigYaml($configDir);
        $this->generateLocalConfigYaml($configDir);
    }

    private function generateConfigYaml(string $configDir): void
    {
        $configFile = $configDir . '/config.yaml';

        if (is_file($configFile)) {
            return;
        }

        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $exampleFile = $vendorDir . '/scafera/kernel/support/scaffold/config/config.example.yaml';

        if (!is_file($exampleFile)) {
            return;
        }

        $lines = file($exampleFile, FILE_IGNORE_NEW_LINES);
        $filtered = [];

        foreach ($lines as $line) {
            if (str_contains($line, '#local')) {
                continue;
            }

            $filtered[] = $line;
        }

        file_put_contents($configFile, implode("\n", $filtered) . "\n");

        $this->io->write('  <info>config/config.yaml</info> created');
    }

    private function generateLocalConfigYaml(string $configDir): void
    {
        $localConfigFile = $configDir . '/config.local.yaml';

        if (is_file($localConfigFile)) {
            return;
        }

        $secret = bin2hex(random_bytes(16));

        file_put_contents($localConfigFile, "env:\n    APP_SECRET: '$secret'\n    APP_DEBUG: '1'\n");

        $this->io->write('  <info>config/config.local.yaml</info> created with generated APP_SECRET');
    }

    /** @return array<string, true> */
    private function getDisabledFiles(): array
    {
        $rootExtra = $this->composer->getPackage()->getExtra();
        $mapping = $rootExtra['scafera-scaffold']['file-mapping'] ?? [];
        $disabled = [];

        foreach ($mapping as $file => $value) {
            if ($value === false) {
                $disabled[$file] = true;
            }
        }

        return $disabled;
    }
}
