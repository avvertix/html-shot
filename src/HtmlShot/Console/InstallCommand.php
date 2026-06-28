<?php

declare(strict_types=1);

namespace HtmlShot\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'install',
    description: 'Download the native Takumi library for the installed package version',
)]
final class InstallCommand extends Command
{
    public function __construct(
        private readonly string $packageRoot,
        private readonly string $projectRoot,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'Release version to download (defaults to the installed package version)',
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Re-download the library even if it is already present',
            )
            ->setHelp(
                "Downloads the compiled Takumi Rust library with FFI bindings that powers\n".
                "html-shot's ability to render images from HTML, matching the current\n".
                'platform and the installed package version.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $version */
        $version = $input->getArgument('version');

        try {
            (new NativeLibraryInstaller($this->packageRoot, $this->projectRoot))->install(
                $io,
                force: (bool) $input->getOption('force'),
                version: $version,
            );
        } catch (Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
